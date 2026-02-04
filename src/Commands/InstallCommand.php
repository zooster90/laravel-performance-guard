<?php

declare(strict_types=1);

namespace Zufarmarwah\PerformanceGuard\Commands;

use Illuminate\Console\Command;

class InstallCommand extends Command
{
    protected $signature = 'performance-guard:install';

    protected $description = 'Install Performance Guard: publish config and run migrations';

    public function handle(): int
    {
        $this->info('Installing Performance Guard...');
        $this->newLine();

        $this->components->task('Publishing configuration', function () {
            $this->callSilently('vendor:publish', [
                '--tag' => 'performance-guard-config',
            ]);
        });

        $this->components->task('Running migrations', function () {
            $this->callSilently('migrate');
        });

        $this->newLine();
        $this->components->info('Performance Guard installed successfully.');
        $this->newLine();

        $this->line('  <fg=white;options=bold>Next steps:</>');
        $this->newLine();
        $this->line('  1. Add the middleware to your routes:');
        $this->newLine();
        $this->line("     <fg=gray>Route::middleware(['performance-guard'])->group(function () {</>");
        $this->line('     <fg=gray>    // your routes</fg>');
        $this->line('     <fg=gray>});</>');
        $this->newLine();
        $this->line('  2. Visit <fg=cyan>/performance-guard</> to see the dashboard');
        $this->newLine();
        $this->line('  3. For production, set <fg=yellow>PERFORMANCE_GUARD_ASYNC=true</> in your .env');
        $this->newLine();

        return self::SUCCESS;
    }
}
