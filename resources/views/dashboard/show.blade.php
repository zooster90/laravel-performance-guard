@extends('performance-guard::dashboard.layout')

@section('content')
<div class="header">
    <h1><span>Performance</span> Guard</h1>
    <div class="nav">
        <a href="{{ route('performance-guard.dashboard') }}">Overview</a>
        <a href="{{ route('performance-guard.n-plus-one') }}">N+1 Issues</a>
        <a href="{{ route('performance-guard.slow-queries') }}">Slow Queries</a>
        <a href="{{ route('performance-guard.routes') }}">Routes</a>
    </div>
</div>

<div style="margin-bottom: 1rem;">
    <a href="javascript:history.back()" style="color: #94a3b8; text-decoration: none; font-size: 0.875rem;">&larr; Back</a>
</div>

<div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));">
    <div class="stat-card">
        <div class="label">Method</div>
        <div class="value" style="font-size: 1.25rem;"><span class="method method-{{ strtolower($record->method) }}">{{ $record->method }}</span></div>
    </div>
    <div class="stat-card">
        <div class="label">Duration</div>
        <div class="value {{ $record->duration_ms > 1000 ? 'danger' : ($record->duration_ms > 500 ? 'warning' : 'success') }}" style="font-size: 1.25rem;">
            {{ number_format($record->duration_ms, 0) }}ms
        </div>
    </div>
    <div class="stat-card">
        <div class="label">Queries</div>
        <div class="value {{ $record->query_count > 30 ? 'danger' : ($record->query_count > 15 ? 'warning' : '') }}" style="font-size: 1.25rem;">
            {{ $record->query_count }}
        </div>
    </div>
    <div class="stat-card">
        <div class="label">Memory</div>
        <div class="value" style="font-size: 1.25rem;">{{ number_format($record->memory_mb, 1) }}MB</div>
    </div>
    <div class="stat-card">
        <div class="label">Grade</div>
        <div class="value" style="font-size: 1.25rem;"><span class="badge badge-{{ strtolower($record->grade) }}">{{ $record->grade }}</span></div>
    </div>
    <div class="stat-card">
        <div class="label">Status</div>
        <div class="value" style="font-size: 1.25rem;">{{ $record->status_code }}</div>
    </div>
</div>

<div class="table-container" style="margin-bottom: 1.5rem;">
    <div style="padding: 1rem 1.25rem; border-bottom: 1px solid #334155;">
        <div style="font-size: 0.75rem; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.05em;">Request URI</div>
        <div style="font-size: 1rem; color: #f8fafc; margin-top: 0.25rem; word-break: break-all;">{{ $record->uri }}</div>
    </div>
    @if($record->controller || $record->action)
    <div style="padding: 1rem 1.25rem; border-bottom: 1px solid #334155;">
        <div style="font-size: 0.75rem; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.05em;">Controller</div>
        <div style="font-size: 0.875rem; color: #e2e8f0; margin-top: 0.25rem; font-family: monospace;">{{ $record->controller }}{{ $record->action ? '@' . $record->action : '' }}</div>
    </div>
    @endif
    <div style="padding: 1rem 1.25rem; display: flex; gap: 2rem; flex-wrap: wrap;">
        <div>
            <span style="font-size: 0.75rem; color: #94a3b8;">Time:</span>
            <span style="font-size: 0.875rem; color: #e2e8f0;">{{ $record->created_at?->format('Y-m-d H:i:s') }}</span>
        </div>
        @if($record->ip_address)
        <div>
            <span style="font-size: 0.75rem; color: #94a3b8;">IP:</span>
            <span style="font-size: 0.875rem; color: #e2e8f0;">{{ $record->ip_address }}</span>
        </div>
        @endif
        @if($record->user_id)
        <div>
            <span style="font-size: 0.75rem; color: #94a3b8;">User ID:</span>
            <span style="font-size: 0.875rem; color: #e2e8f0;">{{ $record->user_id }}</span>
        </div>
        @endif
        <div>
            <span style="font-size: 0.75rem; color: #94a3b8;">Issues:</span>
            @if($record->has_n_plus_one)
                <span class="badge badge-danger">N+1</span>
            @endif
            @if($record->has_slow_queries)
                <span class="badge badge-warning">Slow Queries</span>
            @endif
            @if(!$record->has_n_plus_one && !$record->has_slow_queries)
                <span style="font-size: 0.875rem; color: #34d399;">None</span>
            @endif
        </div>
    </div>
</div>

@if($duplicateGroups->isNotEmpty())
<div class="table-container" style="margin-bottom: 1.5rem;">
    <h2 style="display: flex; align-items: center; gap: 0.5rem;">
        <span class="badge badge-danger">N+1</span> Duplicate Query Patterns
    </h2>
    @foreach($duplicateGroups as $group)
        <div class="suggestion-card">
            <div class="suggestion-header">
                <span class="badge badge-danger">{{ $group['count'] }}x</span>
                <span style="color: #fbbf24; font-size: 0.75rem;">{{ number_format($group['total_duration'], 1) }}ms total</span>
            </div>
            <div class="suggestion-text">{{ $group['suggestion'] }}</div>
            <div class="suggestion-sql">{{ $group['normalized_sql'] }}</div>
        </div>
    @endforeach
</div>
@endif

@if($slowQueries->isNotEmpty())
<div class="table-container" style="margin-bottom: 1.5rem;">
    <h2 style="display: flex; align-items: center; gap: 0.5rem;">
        <span class="badge badge-warning">Slow</span> Slow Queries
    </h2>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>SQL</th>
                <th>Duration</th>
                <th>Source</th>
            </tr>
        </thead>
        <tbody>
            @foreach($slowQueries as $i => $query)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td><code class="sql-code">{{ $query->sql }}</code></td>
                    <td style="color: #fbbf24; white-space: nowrap;">{{ number_format($query->duration_ms, 1) }}ms</td>
                    <td style="white-space: nowrap; color: #64748b; font-size: 0.75rem;">
                        @if($query->file)
                            {{ $query->file }}{{ $query->line ? ':' . $query->line : '' }}
                        @else
                            -
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif

<div class="table-container">
    <h2>All Queries ({{ $queries->count() }})</h2>
    @if($queries->isEmpty())
        <div class="empty-state">
            <p>No queries recorded for this request.</p>
        </div>
    @else
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>SQL</th>
                    <th>Duration</th>
                    <th>Flags</th>
                    <th>Source</th>
                </tr>
            </thead>
            <tbody>
                @foreach($queries as $i => $query)
                    <tr class="{{ $query->is_duplicate ? 'row-duplicate' : ($query->is_slow ? 'row-slow' : '') }}">
                        <td>{{ $i + 1 }}</td>
                        <td><code class="sql-code">{{ $query->sql }}</code></td>
                        <td style="white-space: nowrap; {{ $query->is_slow ? 'color: #fbbf24;' : '' }}">{{ number_format($query->duration_ms, 1) }}ms</td>
                        <td style="white-space: nowrap;">
                            @if($query->is_duplicate)
                                <span class="badge badge-danger" style="font-size: 0.625rem;">DUP</span>
                            @endif
                            @if($query->is_slow)
                                <span class="badge badge-warning" style="font-size: 0.625rem;">SLOW</span>
                            @endif
                        </td>
                        <td style="white-space: nowrap; color: #64748b; font-size: 0.75rem;">
                            @if($query->file)
                                {{ $query->file }}{{ $query->line ? ':' . $query->line : '' }}
                            @else
                                -
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
@endsection
