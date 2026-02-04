<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Performance Guard - Setup Required</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #0f172a; color: #e2e8f0; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .card { max-width: 640px; margin: 2rem; padding: 2.5rem; background: #1e293b; border-radius: 1rem; border: 1px solid #334155; }
        h1 { font-size: 1.5rem; margin-bottom: 0.5rem; color: #f8fafc; }
        h1 span { color: #38bdf8; }
        .subtitle { color: #94a3b8; margin-bottom: 2rem; font-size: 0.875rem; }
        h2 { font-size: 1rem; color: #fbbf24; margin-bottom: 1rem; }
        .step { margin-bottom: 1.5rem; }
        .step-label { font-size: 0.75rem; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.5rem; }
        pre { background: #0f172a; border: 1px solid #334155; border-radius: 0.5rem; padding: 1rem; overflow-x: auto; font-size: 0.8125rem; line-height: 1.7; color: #e2e8f0; }
        .keyword { color: #c084fc; }
        .string { color: #34d399; }
        .comment { color: #64748b; }
        .func { color: #38bdf8; }
        .divider { border-top: 1px solid #334155; margin: 1.5rem 0; }
        .alt { color: #94a3b8; font-size: 0.8125rem; line-height: 1.6; }
        .alt code { background: #0f172a; padding: 0.125rem 0.375rem; border-radius: 0.25rem; font-size: 0.8125rem; color: #fbbf24; }
    </style>
</head>
<body>
    <div class="card">
        <h1><span>Performance</span> Guard</h1>
        <p class="subtitle">Dashboard access requires authorization. Follow the steps below to set it up.</p>

        <h2>Setup Authorization</h2>

        <div class="step">
            <div class="step-label">Option 1 &mdash; Define a Gate</div>
            <pre><span class="comment">// In AppServiceProvider::boot() or AuthServiceProvider</span>
<span class="keyword">use</span> Illuminate\Support\Facades\<span class="func">Gate</span>;

<span class="func">Gate</span>::<span class="func">define</span>(<span class="string">'viewPerformanceGuard'</span>, <span class="keyword">function</span> ($user) {
    <span class="keyword">return</span> <span class="func">in_array</span>($user->email, [
        <span class="string">'your-email@example.com'</span>,
    ]);
});</pre>
        </div>

        <div class="step">
            <div class="step-label">Option 2 &mdash; Whitelist your IP</div>
            <pre><span class="comment">// In config/performance-guard.php</span>
<span class="string">'dashboard'</span> => [
    <span class="string">'allowed_ips'</span> => [<span class="string">'{{ $ip }}'</span>],
],</pre>
        </div>

        <div class="divider"></div>

        <p class="alt">
            Or set <code>auth => false</code> in <code>config/performance-guard.php</code> to disable authorization entirely (not recommended for production).
        </p>
    </div>
</body>
</html>
