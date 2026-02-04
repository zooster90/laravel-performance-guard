@extends('performance-guard::dashboard.layout')

@section('content')
<div class="header">
    <h1><span>Performance</span> Guard</h1>
    <div class="nav">
        <a href="{{ route('performance-guard.dashboard') }}">Overview</a>
        <a href="{{ route('performance-guard.n-plus-one') }}">N+1 Issues</a>
        <a href="{{ route('performance-guard.slow-queries') }}">Slow Queries</a>
        <a href="{{ route('performance-guard.routes') }}" class="active">Routes</a>
    </div>
</div>

<div class="period-selector" style="margin-bottom: 1.5rem;">
    @foreach(['1h' => '1 Hour', '24h' => '24 Hours', '7d' => '7 Days', '30d' => '30 Days'] as $key => $label)
        <a href="{{ route('performance-guard.routes', ['period' => $key]) }}"
           class="{{ $period === $key ? 'active' : '' }}">{{ $label }}</a>
    @endforeach
</div>

<div class="table-container">
    <h2>Route Performance (sorted by impact: requests &times; avg duration)</h2>
    @if($routes->isEmpty())
        <div class="empty-state">
            <p>No route data available for this period.</p>
        </div>
    @else
        <table>
            <thead>
                <tr>
                    <th>Method</th>
                    <th>URI</th>
                    <th>Requests</th>
                    <th>Avg Duration</th>
                    <th>Avg Queries</th>
                    <th>Avg Memory</th>
                    <th>Impact</th>
                    <th>Worst Grade</th>
                    <th>Issues</th>
                </tr>
            </thead>
            <tbody>
                @foreach($routes as $route)
                    <tr>
                        <td><span class="method method-{{ strtolower($route->method) }}">{{ $route->method }}</span></td>
                        <td style="max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">{{ $route->uri }}</td>
                        <td>{{ number_format($route->request_count) }}</td>
                        <td class="{{ $route->avg_duration > 1000 ? 'danger' : ($route->avg_duration > 500 ? 'warning' : '') }}">
                            {{ number_format($route->avg_duration, 0) }}ms
                        </td>
                        <td>{{ number_format($route->avg_queries, 0) }}</td>
                        <td>{{ number_format($route->avg_memory, 1) }}MB</td>
                        <td style="color: {{ $route->impact_score > 10000 ? '#f87171' : ($route->impact_score > 5000 ? '#fbbf24' : '#94a3b8') }};">{{ number_format($route->impact_score, 0) }}</td>
                        <td><span class="badge badge-{{ strtolower($route->worst_grade) }}">{{ $route->worst_grade }}</span></td>
                        <td>
                            @if($route->n_plus_one_hits > 0)
                                <span class="badge badge-danger">N+1: {{ $route->n_plus_one_hits }}</span>
                            @endif
                            @if($route->slow_query_hits > 0)
                                <span class="badge badge-warning">Slow: {{ $route->slow_query_hits }}</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        @if($routes->hasPages())
            <div style="padding: 1rem; display: flex; justify-content: center; gap: 0.5rem;">
                @if($routes->previousPageUrl())
                    <a href="{{ $routes->previousPageUrl() }}&period={{ $period }}" style="color: #94a3b8; text-decoration: none;">&larr; Previous</a>
                @endif
                <span style="color: #64748b;">Page {{ $routes->currentPage() }} of {{ $routes->lastPage() }}</span>
                @if($routes->nextPageUrl())
                    <a href="{{ $routes->nextPageUrl() }}&period={{ $period }}" style="color: #94a3b8; text-decoration: none;">Next &rarr;</a>
                @endif
            </div>
        @endif
    @endif
</div>
@endsection
