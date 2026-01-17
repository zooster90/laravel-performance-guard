<?php

declare(strict_types=1);

namespace Zufarmarwah\PerformanceGuard\Analyzers;

class PerformanceScorer
{
    /**
     * Grade a request based on its duration.
     *
     * @param  array<string, int>  $grading
     */
    public function grade(float $durationMs, array $grading = []): string
    {
        if ($grading === []) {
            $grading = config('performance-guard.grading', [
                'A' => 200,
                'B' => 500,
                'C' => 1000,
                'D' => 3000,
            ]);
        }

        foreach (['A', 'B', 'C', 'D'] as $letter) {
            if (isset($grading[$letter]) && $durationMs <= $grading[$letter]) {
                return $letter;
            }
        }

        return 'F';
    }
}
