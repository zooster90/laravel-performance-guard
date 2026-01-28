@extends('performance-guard::dashboard.layout')

@section('content')
<div class="header">
    <h1><span>Performance</span> Guard</h1>
    <div class="nav">
        <a href="{{ route('performance-guard.dashboard') }}" class="active">Overview</a>
        <a href="{{ route('performance-guard.n-plus-one') }}">N+1 Issues</a>
        <a href="{{ route('performance-guard.slow-queries') }}">Slow Queries</a>
        <a href="{{ route('performance-guard.routes') }}">Routes</a>
    </div>
</div>

<div class="period-selector" style="margin-bottom: 1.5rem;">
    @foreach(['1h' => '1 Hour', '24h' => '24 Hours', '7d' => '7 Days', '30d' => '30 Days'] as $key => $label)
        <a href="{{ route('performance-guard.dashboard', ['period' => $key]) }}"
           class="{{ $period === $key ? 'active' : '' }}">{{ $label }}</a>
    @endforeach
</div>

@php
    $trendFn = function($current, $previous, $lowerIsBetter = false) {
        if ($previous == 0) return '';
        $change = (($current - $previous) / $previous) * 100;
        if (abs($change) < 1) return '<span style="color: #64748b; font-size: 0.75rem;">unchanged</span>';
        $arrow = $change > 0 ? '&uarr;' : '&darr;';
        $isGood = $lowerIsBetter ? $change < 0 : $change > 0;
        $color = $isGood ? '#34d399' : '#f87171';
        return '<span style="color: ' . $color . '; font-size: 0.75rem;">' . $arrow . ' ' . number_format(abs($change), 0) . '%</span>';
    };
@endphp

<div class="stats-grid">
    <div class="stat-card">
        <div class="label">Total Requests</div>
        <div class="value">{{ number_format($stats['total_requests']) }}</div>
        <div class="trend">{!! $trendFn($stats['total_requests'], $previousStats['total_requests'] ?? 0) !!}</div>
    </div>
    <div class="stat-card">
        <div class="label">Avg Duration</div>
        <div class="value {{ $stats['avg_duration_ms'] > 1000 ? 'danger' : ($stats['avg_duration_ms'] > 500 ? 'warning' : 'success') }}">
            {{ number_format($stats['avg_duration_ms'], 0) }}ms
        </div>
        <div class="trend">{!! $trendFn($stats['avg_duration_ms'], $previousStats['avg_duration_ms'] ?? 0, true) !!}</div>
    </div>
    <div class="stat-card">
        <div class="label">Avg Queries</div>
        <div class="value {{ $stats['avg_queries'] > 30 ? 'danger' : ($stats['avg_queries'] > 15 ? 'warning' : '') }}">
            {{ $stats['avg_queries'] }}
        </div>
        <div class="trend">{!! $trendFn($stats['avg_queries'], $previousStats['avg_queries'] ?? 0, true) !!}</div>
    </div>
    <div class="stat-card">
        <div class="label">N+1 Issues</div>
        <div class="value {{ $stats['n_plus_one_count'] > 0 ? 'danger' : 'success' }}">
            {{ $stats['n_plus_one_count'] }}
        </div>
        <div class="trend">{!! $trendFn($stats['n_plus_one_count'], $previousStats['n_plus_one_count'] ?? 0, true) !!}</div>
    </div>
    <div class="stat-card">
        <div class="label">Slow Queries</div>
        <div class="value {{ $stats['slow_query_count'] > 0 ? 'warning' : 'success' }}">
            {{ $stats['slow_query_count'] }}
        </div>
        <div class="trend">{!! $trendFn($stats['slow_query_count'], $previousStats['slow_query_count'] ?? 0, true) !!}</div>
    </div>
    <div class="stat-card">
        <div class="label">Avg Memory</div>
        <div class="value">{{ number_format($stats['avg_memory_mb'], 1) }}MB</div>
        <div class="trend">{!! $trendFn($stats['avg_memory_mb'], $previousStats['avg_memory_mb'] ?? 0, true) !!}</div>
    </div>
</div>

<div class="grade-bar">
    @foreach(['A' => 'grade-a', 'B' => 'grade-b', 'C' => 'grade-c', 'D' => 'grade-d', 'F' => 'grade-f'] as $grade => $class)
        <div class="grade-item">
            <div class="letter {{ $class }}">{{ $grade }}</div>
            <div class="count">{{ $gradeDistribution[$grade] ?? 0 }}</div>
        </div>
    @endforeach
</div>

<div class="table-container">
    <h2>Recent Requests</h2>
    @if($records->isEmpty())
        <div class="empty-state">
            <p>No performance records yet. Add the middleware to start monitoring.</p>
        </div>
    @else
        <table>
            <thead>
                <tr>
                    <th>Method</th>
                    <th>URI</th>
                    <th>Duration</th>
                    <th>Queries</th>
                    <th>Memory</th>
                    <th>Grade</th>
                    <th>Issues</th>
                    <th>Time</th>
                </tr>
            </thead>
            <tbody>
                @foreach($records as $record)
                    <tr>
                        <td><span class="method method-{{ strtolower($record->method) }}">{{ $record->method }}</span></td>
                        <td style="max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">{{ $record->uri }}</td>
                        <td>{{ number_format($record->duration_ms, 0) }}ms</td>
                        <td>{{ $record->query_count }}</td>
                        <td>{{ number_format($record->memory_mb, 1) }}MB</td>
                        <td><span class="badge badge-{{ strtolower($record->grade) }}">{{ $record->grade }}</span></td>
                        <td>
                            @if($record->has_n_plus_one)
                                <span class="badge badge-danger">N+1</span>
                            @endif
                            @if($record->has_slow_queries)
                                <span class="badge badge-warning">Slow</span>
                            @endif
                        </td>
                        <td style="white-space: nowrap; color: #64748b; font-size: 0.75rem;">
                            {{ $record->created_at?->diffForHumans() }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
@endsection
