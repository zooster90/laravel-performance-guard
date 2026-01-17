<?php

declare(strict_types=1);

namespace Zufarmarwah\PerformanceGuard\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
use Zufarmarwah\PerformanceGuard\Models\PerformanceQuery;
use Zufarmarwah\PerformanceGuard\Models\PerformanceRecord;

class StorePerformanceRecordJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @param  array{method: string, uri: string, controller: string|null, action: string|null, query_count: int, slow_query_count: int, duration_ms: float, memory_mb: float, grade: string, has_n_plus_one: bool, has_slow_queries: bool, user_id: int|null, ip_address: string|null}  $recordData
     * @param  array<int, array{sql: string, normalized: string, duration: float, is_slow: bool, is_duplicate: bool, file: string|null, line: int|null}>  $queryData
     */
    public function __construct(
        private readonly array $recordData,
        private readonly array $queryData,
    ) {
        $this->onQueue(config('performance-guard.storage.queue', 'default'));
    }

    public function handle(): void
    {
        $record = PerformanceRecord::create([
            ...$this->recordData,
            'uuid' => Str::uuid()->toString(),
            'created_at' => now(),
        ]);

        foreach ($this->queryData as $query) {
            PerformanceQuery::create([
                'performance_record_id' => $record->id,
                'sql' => $query['sql'],
                'normalized_sql' => $query['normalized'],
                'duration_ms' => $query['duration'],
                'is_slow' => $query['is_slow'],
                'is_duplicate' => $query['is_duplicate'],
                'file' => $query['file'],
                'line' => $query['line'],
                'created_at' => now(),
            ]);
        }
    }
}
