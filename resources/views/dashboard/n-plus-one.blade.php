@extends('performance-guard::dashboard.layout')

@section('content')
<div class="header">
    <h1><span>Performance</span> Guard</h1>
    <div class="nav">
        <a href="{{ route('performance-guard.dashboard') }}">Overview</a>
        <a href="{{ route('performance-guard.n-plus-one') }}" class="active">N+1 Issues</a>
        <a href="{{ route('performance-guard.slow-queries') }}">Slow Queries</a>
        <a href="{{ route('performance-guard.routes') }}">Routes</a>
    </div>
</div>

<div class="table-container">
    <h2>Requests with N+1 Query Issues</h2>
    @if($records->isEmpty())
        <div class="empty-state">
            <p>No N+1 query issues detected. Great job!</p>
        </div>
    @else
        <table>
            <thead>
                <tr>
                    <th>Method</th>
                    <th>URI</th>
                    <th>Controller</th>
                    <th>Queries</th>
                    <th>Duration</th>
                    <th>Grade</th>
                    <th>Time</th>
                </tr>
            </thead>
            <tbody>
                @foreach($records as $record)
                    <tr class="clickable" onclick="window.location='{{ route('performance-guard.request.show', $record->uuid) }}'">
                        <td><span class="method method-{{ strtolower($record->method) }}">{{ $record->method }}</span></td>
                        <td style="max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">{{ $record->uri }}</td>
                        <td style="max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; color: #64748b; font-size: 0.75rem;">
                            {{ $record->action ?? '-' }}
                        </td>
                        <td>{{ $record->query_count }}</td>
                        <td>{{ number_format($record->duration_ms, 0) }}ms</td>
                        <td><span class="badge badge-{{ strtolower($record->grade) }}">{{ $record->grade }}</span></td>
                        <td style="white-space: nowrap; color: #64748b; font-size: 0.75rem;">
                            {{ $record->created_at?->diffForHumans() }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        @if($records->hasPages())
            <div style="padding: 1rem; display: flex; justify-content: center; gap: 0.5rem;">
                @if($records->previousPageUrl())
                    <a href="{{ $records->previousPageUrl() }}" style="color: #94a3b8; text-decoration: none;">&larr; Previous</a>
                @endif
                <span style="color: #64748b;">Page {{ $records->currentPage() }} of {{ $records->lastPage() }}</span>
                @if($records->nextPageUrl())
                    <a href="{{ $records->nextPageUrl() }}" style="color: #94a3b8; text-decoration: none;">Next &rarr;</a>
                @endif
            </div>
        @endif
    @endif
</div>
@endsection
