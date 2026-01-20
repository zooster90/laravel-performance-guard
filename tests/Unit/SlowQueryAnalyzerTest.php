<?php

declare(strict_types=1);

use Zufarmarwah\PerformanceGuard\Analyzers\SlowQueryAnalyzer;

beforeEach(function () {
    $this->analyzer = new SlowQueryAnalyzer;
});

it('detects slow queries above threshold', function () {
    $queries = [
        ['sql' => 'select * from users', 'normalized' => 'select * from users', 'duration' => 50.0, 'file' => null, 'line' => null],
        ['sql' => 'select * from posts where id = 1', 'normalized' => 'select * from posts where id = ?', 'duration' => 500.0, 'file' => 'app/Models/Post.php', 'line' => 15],
        ['sql' => 'select * from comments', 'normalized' => 'select * from comments', 'duration' => 10.0, 'file' => null, 'line' => null],
    ];

    $result = $this->analyzer->analyze($queries, 300.0);

    expect($result['hasSlowQueries'])->toBeTrue();
    expect($result['slowQueries'])->toHaveCount(1);
    expect($result['slowQueries'][0]['sql'])->toBe('select * from posts where id = 1');
    expect($result['totalSlowTime'])->toBe(500.0);
});

it('returns false when no slow queries exist', function () {
    $queries = [
        ['sql' => 'select * from users', 'normalized' => 'select * from users', 'duration' => 5.0, 'file' => null, 'line' => null],
        ['sql' => 'select * from posts', 'normalized' => 'select * from posts', 'duration' => 10.0, 'file' => null, 'line' => null],
    ];

    $result = $this->analyzer->analyze($queries, 300.0);

    expect($result['hasSlowQueries'])->toBeFalse();
    expect($result['slowQueries'])->toBeEmpty();
    expect($result['totalSlowTime'])->toBe(0.0);
});

it('sorts slow queries by duration descending', function () {
    $queries = [
        ['sql' => 'select * from users', 'normalized' => 'select * from users', 'duration' => 400.0, 'file' => null, 'line' => null],
        ['sql' => 'select * from posts', 'normalized' => 'select * from posts', 'duration' => 800.0, 'file' => null, 'line' => null],
        ['sql' => 'select * from comments', 'normalized' => 'select * from comments', 'duration' => 600.0, 'file' => null, 'line' => null],
    ];

    $result = $this->analyzer->analyze($queries, 300.0);

    expect($result['slowQueries'])->toHaveCount(3);
    expect($result['slowQueries'][0]['duration'])->toBe(800.0);
    expect($result['slowQueries'][1]['duration'])->toBe(600.0);
    expect($result['slowQueries'][2]['duration'])->toBe(400.0);
});

it('generates suggestions for slow queries', function () {
    $queries = [
        ['sql' => 'select * from users where email = "test@example.com"', 'normalized' => 'select * from users where email = ?', 'duration' => 500.0, 'file' => null, 'line' => null],
    ];

    $result = $this->analyzer->analyze($queries, 300.0);

    expect($result['slowQueries'][0]['suggestion'])->not->toBeEmpty();
});

it('handles empty query list', function () {
    $result = $this->analyzer->analyze([], 300.0);

    expect($result['hasSlowQueries'])->toBeFalse();
    expect($result['slowQueries'])->toBeEmpty();
    expect($result['totalSlowTime'])->toBe(0.0);
});

it('detects wildcard LIKE queries', function () {
    $queries = [
        ['sql' => "select * from users where name like '%john%'", 'normalized' => "select * from users where name like ?", 'duration' => 500.0, 'file' => null, 'line' => null],
    ];

    $result = $this->analyzer->analyze($queries, 300.0);

    expect($result['slowQueries'][0]['suggestion'])->toContain('wildcard');
});
