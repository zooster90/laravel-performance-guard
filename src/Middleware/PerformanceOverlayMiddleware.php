<?php

declare(strict_types=1);

namespace Zufarmarwah\PerformanceGuard\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Zufarmarwah\PerformanceGuard\Analyzers\NPlusOneAnalyzer;
use Zufarmarwah\PerformanceGuard\Analyzers\PerformanceScorer;
use Zufarmarwah\PerformanceGuard\Listeners\QueryListener;

class PerformanceOverlayMiddleware
{
    public function __construct(
        private readonly QueryListener $queryListener,
        private readonly NPlusOneAnalyzer $nPlusOneAnalyzer,
        private readonly PerformanceScorer $scorer,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! config('performance-guard.overlay.enabled', false)) {
            return $next($request);
        }

        $startTime = microtime(true);

        $response = $next($request);

        if (! $this->isHtmlResponse($response)) {
            return $response;
        }

        $durationMs = (microtime(true) - $startTime) * 1000;
        $queries = $this->queryListener->getQueries();
        $nPlusOneThreshold = (int) config('performance-guard.thresholds.n_plus_one', 10);
        $analysis = $this->nPlusOneAnalyzer->analyze($queries, $nPlusOneThreshold);
        $slowThreshold = (float) config('performance-guard.thresholds.slow_query_ms', 300);
        $slowQueries = $this->queryListener->getSlowQueries($slowThreshold);
        $grade = $this->scorer->grade($durationMs);

        $overlay = $this->buildOverlay(
            $durationMs,
            count($queries),
            $grade,
            $analysis['hasNPlusOne'],
            count($analysis['duplicates']),
            count($slowQueries),
            $analysis['suggestions'],
            $queries,
        );

        $content = $response->getContent();

        if ($content === false) {
            return $response;
        }

        $position = strripos($content, '</body>');

        if ($position !== false) {
            $content = substr($content, 0, $position) . $overlay . substr($content, $position);
            $response->setContent($content);
        }

        return $response;
    }

    private function isHtmlResponse(Response $response): bool
    {
        $contentType = $response->headers->get('Content-Type', '');

        return str_contains($contentType, 'text/html') || $contentType === '';
    }

    /**
     * @param  array<int, string>  $suggestions
     * @param  array<int, array{sql: string, duration: float, file: string|null, line: int|null, normalized: string}>  $queries
     */
    private function buildOverlay(
        float $durationMs,
        int $queryCount,
        string $grade,
        bool $hasNPlusOne,
        int $duplicateGroupCount,
        int $slowQueryCount,
        array $suggestions,
        array $queries,
    ): string {
        $position = config('performance-guard.overlay.position', 'bottom-right');
        $positionCss = $position === 'bottom-left' ? 'left: 12px;' : 'right: 12px;';
        $dashboardUrl = url(config('performance-guard.dashboard.path', 'performance-guard'));
        $webVitals = config('performance-guard.overlay.web_vitals', false);

        $gradeColor = match ($grade) {
            'A' => '#34d399',
            'B' => '#38bdf8',
            'C' => '#fbbf24',
            'D' => '#fb923c',
            default => '#f87171',
        };

        $pillColor = $hasNPlusOne || $slowQueryCount > 0 ? '#dc2626' : ($grade === 'A' ? '#059669' : '#d97706');

        $issues = [];
        if ($hasNPlusOne) {
            $issues[] = '<span style="color:#f87171;">N+1: ' . $duplicateGroupCount . '</span>';
        }
        if ($slowQueryCount > 0) {
            $issues[] = '<span style="color:#fbbf24;">Slow: ' . $slowQueryCount . '</span>';
        }
        $issuesHtml = count($issues) > 0
            ? ' | ' . implode(' ', $issues)
            : '';

        $queryListHtml = '';
        foreach (array_slice($queries, 0, 20) as $i => $q) {
            $slow = $q['duration'] >= (float) config('performance-guard.thresholds.slow_query_ms', 300);
            $sqlEscaped = htmlspecialchars(mb_substr($q['sql'], 0, 120), ENT_QUOTES, 'UTF-8');
            $durationFormatted = number_format($q['duration'], 1);
            $durColor = $slow ? '#fbbf24' : '#94a3b8';
            $queryListHtml .= '<div style="padding:3px 0;border-bottom:1px solid #334155;display:flex;justify-content:space-between;gap:8px;">';
            $queryListHtml .= '<span style="font-family:monospace;font-size:10px;color:#e2e8f0;word-break:break-all;flex:1;">' . ($i + 1) . '. ' . $sqlEscaped . '</span>';
            $queryListHtml .= '<span style="font-size:10px;color:' . $durColor . ';white-space:nowrap;">' . $durationFormatted . 'ms</span>';
            $queryListHtml .= '</div>';
        }
        if (count($queries) > 20) {
            $queryListHtml .= '<div style="padding:3px 0;color:#64748b;font-size:10px;">...and ' . (count($queries) - 20) . ' more queries</div>';
        }

        $suggestionsHtml = '';
        foreach ($suggestions as $s) {
            $suggestionsHtml .= '<div style="padding:4px 0;font-size:10px;color:#fbbf24;">• ' . htmlspecialchars($s, ENT_QUOTES, 'UTF-8') . '</div>';
        }

        $webVitalsHtml = '';
        $webVitalsJs = '';
        if ($webVitals) {
            $vitalsEndpoint = url(config('performance-guard.dashboard.path', 'performance-guard') . '/api/vitals');
            $webVitalsHtml = '<div id="pg-vitals" style="margin-top:6px;padding-top:6px;border-top:1px solid #334155;display:none;">'
                . '<div style="font-size:10px;color:#94a3b8;margin-bottom:2px;">Web Vitals</div>'
                . '<div style="display:flex;gap:8px;">'
                . '<span id="pg-lcp" style="font-size:10px;color:#64748b;">LCP: --</span>'
                . '<span id="pg-cls" style="font-size:10px;color:#64748b;">CLS: --</span>'
                . '<span id="pg-inp" style="font-size:10px;color:#64748b;">INP: --</span>'
                . '</div></div>';

            $webVitalsJs = <<<JS
(function(){
    var vitalsEl=document.getElementById('pg-vitals');
    var pageUrl=location.pathname;
    var vitals={lcp:null,cls:null,inp:null};
    function color(val,good,poor){return val<=good?'#34d399':val<=poor?'#fbbf24':'#f87171';}
    function send(){
        if(!vitals.lcp&&!vitals.cls&&!vitals.inp)return;
        var x=new XMLHttpRequest();
        x.open('POST','{$vitalsEndpoint}');
        x.setRequestHeader('Content-Type','application/json');
        x.setRequestHeader('X-Requested-With','XMLHttpRequest');
        x.send(JSON.stringify({url:pageUrl,lcp_ms:vitals.lcp,cls_score:vitals.cls,inp_ms:vitals.inp}));
    }
    try{
        new PerformanceObserver(function(l){
            var e=l.getEntries();
            if(e.length){vitals.lcp=Math.round(e[e.length-1].startTime);
            var el=document.getElementById('pg-lcp');
            if(el){el.textContent='LCP: '+vitals.lcp+'ms';el.style.color=color(vitals.lcp,2500,4000);vitalsEl.style.display='block';}
            }
        }).observe({type:'largest-contentful-paint',buffered:true});
    }catch(e){}
    try{
        var clsVal=0;
        new PerformanceObserver(function(l){
            l.getEntries().forEach(function(e){if(!e.hadRecentInput)clsVal+=e.value;});
            vitals.cls=Math.round(clsVal*1000)/1000;
            var el=document.getElementById('pg-cls');
            if(el){el.textContent='CLS: '+vitals.cls;el.style.color=color(vitals.cls,0.1,0.25);vitalsEl.style.display='block';}
        }).observe({type:'layout-shift',buffered:true});
    }catch(e){}
    try{
        new PerformanceObserver(function(l){
            var e=l.getEntries();
            if(e.length){vitals.inp=Math.round(e[e.length-1].duration);
            var el=document.getElementById('pg-inp');
            if(el){el.textContent='INP: '+vitals.inp+'ms';el.style.color=color(vitals.inp,200,500);vitalsEl.style.display='block';}
            }
        }).observe({type:'event',buffered:true,durationThreshold:16});
    }catch(e){}
    setTimeout(send,10000);
    document.addEventListener('visibilitychange',function(){if(document.visibilityState==='hidden')send();});
})();
JS;
        }

        return <<<HTML
<!-- Performance Guard Overlay -->
<div id="pg-overlay" style="position:fixed;bottom:12px;{$positionCss}z-index:2147483647;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;">
<div id="pg-pill" onclick="document.getElementById('pg-detail').style.display=document.getElementById('pg-detail').style.display==='none'?'block':'none'" style="cursor:pointer;background:{$pillColor};color:#fff;padding:6px 14px;border-radius:20px;font-size:12px;font-weight:600;box-shadow:0 2px 8px rgba(0,0,0,0.3);display:flex;align-items:center;gap:8px;user-select:none;">
<span style="background:{$gradeColor};color:#0f172a;width:20px;height:20px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:10px;font-weight:700;">{$grade}</span>
<span>{$queryCount}q</span>
<span>{$this->formatDuration($durationMs)}</span>
{$issuesHtml}
</div>
<div id="pg-detail" style="display:none;margin-top:8px;background:#0f172a;border:1px solid #334155;border-radius:8px;padding:12px;max-width:480px;max-height:400px;overflow-y:auto;box-shadow:0 4px 16px rgba(0,0,0,0.5);color:#e2e8f0;font-size:11px;">
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
<span style="font-weight:700;color:#f8fafc;">Performance Guard</span>
<a href="{$dashboardUrl}" target="_blank" style="font-size:10px;color:#38bdf8;text-decoration:none;">Open Dashboard →</a>
</div>
<div style="display:flex;gap:12px;margin-bottom:8px;flex-wrap:wrap;">
<div><span style="color:#94a3b8;font-size:10px;">Duration</span><br><span style="font-weight:600;">{$this->formatDuration($durationMs)}</span></div>
<div><span style="color:#94a3b8;font-size:10px;">Queries</span><br><span style="font-weight:600;">{$queryCount}</span></div>
<div><span style="color:#94a3b8;font-size:10px;">Grade</span><br><span style="font-weight:600;color:{$gradeColor};">{$grade}</span></div>
</div>
{$suggestionsHtml}
{$queryListHtml}
{$webVitalsHtml}
</div>
</div>
<script>{$webVitalsJs}</script>
<!-- /Performance Guard Overlay -->
HTML;
    }

    private function formatDuration(float $ms): string
    {
        if ($ms >= 1000) {
            return number_format($ms / 1000, 2) . 's';
        }

        return number_format($ms, 0) . 'ms';
    }
}
