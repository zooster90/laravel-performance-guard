<?php

declare(strict_types=1);

namespace Zufarmarwah\PerformanceGuard\Commands;

use Illuminate\Console\Command;
use Zufarmarwah\PerformanceGuard\Models\PerformanceQuery;
use Zufarmarwah\PerformanceGuard\Models\PerformanceRecord;

class CleanupCommand extends Command
{
    protected $signature = 'performance-guard:cleanup
                            {--days= : Number of days to retain (overrides config)}
                            {--force : Skip confirmation prompt}';

    protected $description = 'Delete old performance monitoring records';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?? config('performance-guard.storage.retention_days', 30));
        $cutoff = now()->subDays($days);

        $count = PerformanceRecord::where('created_at', '<', $cutoff)->count();

        if ($count === 0) {
            $this->info('No records older than ' . $days . ' days found.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm("Delete {$count} records older than {$days} days?")) {
            $this->info('Cleanup cancelled.');

            return self::SUCCESS;
        }

        $chunkSize = 1000;
        $totalDeleted = 0;

        do {
            $recordIds = PerformanceRecord::where('created_at', '<', $cutoff)
                ->limit($chunkSize)
                ->pluck('id');

            if ($recordIds->isEmpty()) {
                break;
            }

            PerformanceQuery::whereIn('performance_record_id', $recordIds)->delete();
            $deleted = PerformanceRecord::whereIn('id', $recordIds)->delete();
            $totalDeleted += $deleted;

            $this->output->write("\rDeleted {$totalDeleted} of ~{$count} records...");
        } while ($deleted >= $chunkSize);

        $this->newLine();
        $this->info("Deleted {$totalDeleted} performance records older than {$days} days.");

        return self::SUCCESS;
    }
}
