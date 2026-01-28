@extends('performance-guard::dashboard.layout')

@section('content')
<div class="header">
    <h1><span>Performance</span> Guard</h1>
    <div class="nav">
        <a href="{{ route('performance-guard.dashboard') }}">Overview</a>
        <a href="{{ route('performance-guard.n-plus-one') }}">N+1 Issues</a>
        <a href="{{ route('performance-guard.slow-queries') }}" class="active">Slow Queries</a>
        <a href="{{ route('performance-guard.routes') }}">Routes</a>
    </div>
</div>

<div class="table-container">
    <h2>Requests with Slow Queries</h2>
    @if($records->isEmpty())
        <div class="empty-state">
            <p>No slow queries detected. Your database is performing well!</p>
        </div>
    @else
        <table>
            <thead>
                <tr>
                    <th>Method</th>
                    <th>URI</th>
                    <th>Slow Queries</th>
                    <th>Total Queries</th>
                    <th>Duration</th>
                    <th>Memory</th>
                    <th>Grade</th>
                    <th>Time</th>
                </tr>
            </thead>
            <tbody>
                @foreach($records as $record)
                    <tr>
                        <td><span class="method method-{{ strtolower($record->method) }}">{{ $record->method }}</span></td>
                        <td style="max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">{{ $record->uri }}</td>
                        <td><span class="badge badge-warning">{{ $record->slow_query_count }}</span></td>
                        <td>{{ $record->query_count }}</td>
                        <td>{{ number_format($record->duration_ms, 0) }}ms</td>
                        <td>{{ number_format($record->memory_mb, 1) }}MB</td>
                        <td><span class="badge badge-{{ strtolower($record->grade) }}">{{ $record->grade }}</span></td>
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
