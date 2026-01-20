<?php

declare(strict_types=1);

use Zufarmarwah\PerformanceGuard\PerformanceGuardManager;

beforeEach(function () {
    $this->manager = new PerformanceGuardManager;
});

it('reports enabled status from config', function () {
    config(['performance-guard.enabled' => true]);
    expect($this->manager->isEnabled())->toBeTrue();

    config(['performance-guard.enabled' => false]);
    expect($this->manager->isEnabled())->toBeFalse();
});

it('can enable monitoring', function () {
    config(['performance-guard.enabled' => false]);
    $this->manager->enable();

    expect(config('performance-guard.enabled'))->toBeTrue();
});

it('can disable monitoring', function () {
    config(['performance-guard.enabled' => true]);
    $this->manager->disable();

    expect(config('performance-guard.enabled'))->toBeFalse();
});
