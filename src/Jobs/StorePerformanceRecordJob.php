<?php

declare(strict_types=1);

namespace Zufarmarwah\PerformanceGuard\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Zufarmarwah\PerformanceGuard\Models\PerformanceQuery;
use Zufarmarwah\PerformanceGuard\Models\PerformanceRecord;

class StorePerformanceRecordJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public int $maxExceptions = 2;

    /** @var array<int, int> */
    public array $backoff = [5, 30];

    /**
     * @param  array<string, mixed>  $recordData
     * @param  array<int, array<string, mixed>>  $queryData
     */
    public function __construct(
        private readonly array $recordData,
        private readonly array $queryData,
    ) {
        $this->onQueue(config('performance-guard.storage.queue', 'default'));
    }

    public function handle(): void
    {
        $connection = config('performance-guard.storage.connection')
            ?? config('database.default');

        DB::connection($connection)->transaction(function () {
            $record = PerformanceRecord::create([
                ...$this->recordData,
                'uuid' => Str::uuid()->toString(),
                'created_at' => now(),
            ]);

            if (empty($this->queryData)) {
                return;
            }

            $rows = [];
            $now = now();

            foreach ($this->queryData as $query) {
                $rows[] = [
                    'performance_record_id' => $record->id,
                    'sql' => $query['sql'] ?? '',
                    'normalized_sql' => $query['normalized'] ?? '',
                    'duration_ms' => $query['duration'] ?? 0,
                    'is_slow' => $query['is_slow'] ?? false,
                    'is_duplicate' => $query['is_duplicate'] ?? false,
                    'file' => $query['file'] ?? null,
                    'line' => $query['line'] ?? null,
                    'created_at' => $now,
                ];
            }

            foreach (array_chunk($rows, 100) as $chunk) {
                PerformanceQuery::insert($chunk);
            }
        });
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Performance Guard: failed to store performance record', [
            'uri' => $this->recordData['uri'] ?? 'unknown',
            'error' => $exception->getMessage(),
        ]);
    }
}
