<?php

declare(strict_types=1);

namespace Zufarmarwah\PerformanceGuard\Facades;

use Illuminate\Support\Facades\Facade;
use Zufarmarwah\PerformanceGuard\PerformanceGuardManager;

/**
 * @method static array getStats(string $period = '24h')
 * @method static array getGradeDistribution(string $period = '24h')
 * @method static bool isEnabled()
 * @method static void enable()
 * @method static void disable()
 *
 * @see \Zufarmarwah\PerformanceGuard\PerformanceGuardManager
 */
class PerformanceGuard extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return PerformanceGuardManager::class;
    }
}
