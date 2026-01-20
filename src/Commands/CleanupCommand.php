<?php

declare(strict_types=1);

namespace Zufarmarwah\PerformanceGuard\Commands;

use Illuminate\Console\Command;
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

        $deleted = PerformanceRecord::where('created_at', '<', $cutoff)->delete();

        $this->info("Deleted {$deleted} performance records older than {$days} days.");

        return self::SUCCESS;
    }
}
