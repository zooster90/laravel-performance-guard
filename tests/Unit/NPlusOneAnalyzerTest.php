<?php

declare(strict_types=1);

use Zufarmarwah\PerformanceGuard\Analyzers\NPlusOneAnalyzer;

beforeEach(function () {
    $this->analyzer = new NPlusOneAnalyzer;
});

it('detects N+1 queries', function () {
    $queries = [
        ['sql' => 'select * from users where id = 1', 'normalized' => 'select * from users where id = ?', 'duration' => 1.5, 'file' => 'app/Http/Controllers/UserController.php', 'line' => 25],
        ['sql' => 'select * from users where id = 2', 'normalized' => 'select * from users where id = ?', 'duration' => 1.3, 'file' => 'app/Http/Controllers/UserController.php', 'line' => 25],
        ['sql' => 'select * from users where id = 3', 'normalized' => 'select * from users where id = ?', 'duration' => 1.1, 'file' => 'app/Http/Controllers/UserController.php', 'line' => 25],
    ];

    $result = $this->analyzer->analyze($queries);

    expect($result['hasNPlusOne'])->toBeTrue();
    expect($result['duplicates'])->toHaveCount(1);
    expect($result['suggestions'])->toHaveCount(1);
});

it('returns false when no duplicates exist', function () {
    $queries = [
        ['sql' => 'select * from users where id = 1', 'normalized' => 'select * from users where id = ?', 'duration' => 1.5, 'file' => null, 'line' => null],
        ['sql' => 'select * from posts where id = 1', 'normalized' => 'select * from posts where id = ?', 'duration' => 2.0, 'file' => null, 'line' => null],
        ['sql' => 'select * from comments where id = 1', 'normalized' => 'select * from comments where id = ?', 'duration' => 0.8, 'file' => null, 'line' => null],
    ];

    $result = $this->analyzer->analyze($queries);

    expect($result['hasNPlusOne'])->toBeFalse();
    expect($result['duplicates'])->toBeEmpty();
    expect($result['suggestions'])->toBeEmpty();
});

it('suggests eager loading', function () {
    $queries = [
        ['sql' => 'select * from posts where user_id = 1', 'normalized' => 'select * from posts where user_id = ?', 'duration' => 1.0, 'file' => null, 'line' => null],
        ['sql' => 'select * from posts where user_id = 2', 'normalized' => 'select * from posts where user_id = ?', 'duration' => 1.0, 'file' => null, 'line' => null],
    ];

    $result = $this->analyzer->analyze($queries);

    expect($result['hasNPlusOne'])->toBeTrue();
    expect($result['suggestions'][0])->toContain('posts');
    expect($result['suggestions'][0])->toContain('with');
});

it('ignores different query patterns', function () {
    $queries = [
        ['sql' => 'select * from users where id = 1', 'normalized' => 'select * from users where id = ?', 'duration' => 1.0, 'file' => null, 'line' => null],
        ['sql' => 'select * from posts where user_id = 1', 'normalized' => 'select * from posts where user_id = ?', 'duration' => 1.0, 'file' => null, 'line' => null],
    ];

    $result = $this->analyzer->analyze($queries);

    expect($result['hasNPlusOne'])->toBeFalse();
});

it('respects the threshold parameter', function () {
    $queries = [
        ['sql' => 'select * from users where id = 1', 'normalized' => 'select * from users where id = ?', 'duration' => 1.0, 'file' => null, 'line' => null],
        ['sql' => 'select * from users where id = 2', 'normalized' => 'select * from users where id = ?', 'duration' => 1.0, 'file' => null, 'line' => null],
        ['sql' => 'select * from users where id = 3', 'normalized' => 'select * from users where id = ?', 'duration' => 1.0, 'file' => null, 'line' => null],
    ];

    $resultLow = $this->analyzer->analyze($queries, 2);
    expect($resultLow['hasNPlusOne'])->toBeTrue();

    $resultHigh = $this->analyzer->analyze($queries, 5);
    expect($resultHigh['hasNPlusOne'])->toBeFalse();
});

it('ignores non-select queries', function () {
    $queries = [
        ['sql' => 'insert into logs (message) values ("test")', 'normalized' => 'insert into logs (message) values (?)', 'duration' => 1.0, 'file' => null, 'line' => null],
        ['sql' => 'insert into logs (message) values ("test2")', 'normalized' => 'insert into logs (message) values (?)', 'duration' => 1.0, 'file' => null, 'line' => null],
    ];

    $result = $this->analyzer->analyze($queries);

    expect($result['hasNPlusOne'])->toBeFalse();
});

it('handles empty query list', function () {
    $result = $this->analyzer->analyze([]);

    expect($result['hasNPlusOne'])->toBeFalse();
    expect($result['duplicates'])->toBeEmpty();
    expect($result['suggestions'])->toBeEmpty();
});

it('suggests parent model for eager loading', function () {
    $queries = [
        ['sql' => 'select * from comments where post_id = 1', 'normalized' => 'select * from comments where post_id = ?', 'duration' => 1.0, 'file' => null, 'line' => null],
        ['sql' => 'select * from comments where post_id = 2', 'normalized' => 'select * from comments where post_id = ?', 'duration' => 1.0, 'file' => null, 'line' => null],
    ];

    $result = $this->analyzer->analyze($queries);

    expect($result['hasNPlusOne'])->toBeTrue();
    expect($result['suggestions'][0])->toContain('Post');
    expect($result['suggestions'][0])->toContain('comment');
});

it('includes file location in suggestions', function () {
    $queries = [
        ['sql' => 'select * from users where id = 1', 'normalized' => 'select * from users where id = ?', 'duration' => 1.0, 'file' => 'app/Http/Controllers/UserController.php', 'line' => 42],
        ['sql' => 'select * from users where id = 2', 'normalized' => 'select * from users where id = ?', 'duration' => 1.0, 'file' => 'app/Http/Controllers/UserController.php', 'line' => 42],
    ];

    $result = $this->analyzer->analyze($queries);

    expect($result['suggestions'][0])->toContain('UserController.php');
    expect($result['suggestions'][0])->toContain('42');
});

it('ignores queries on configured ignored tables', function () {
    config(['performance-guard.ignored_tables' => ['cache', 'sessions']]);

    $queries = [
        ['sql' => 'select * from "cache" where "key" in (?)', 'normalized' => 'select * from "cache" where "key" in (?)', 'duration' => 0.1, 'file' => null, 'line' => null],
        ['sql' => 'select * from "cache" where "key" in (?)', 'normalized' => 'select * from "cache" where "key" in (?)', 'duration' => 0.1, 'file' => null, 'line' => null],
        ['sql' => 'select * from "cache" where "key" in (?)', 'normalized' => 'select * from "cache" where "key" in (?)', 'duration' => 0.1, 'file' => null, 'line' => null],
    ];

    $result = $this->analyzer->analyze($queries);

    expect($result['hasNPlusOne'])->toBeFalse();
    expect($result['duplicates'])->toBeEmpty();
});

it('suggests subquery for aggregate queries', function () {
    $queries = [
        ['sql' => 'select sum("total_amount") as aggregate from "bookings" where "status" = ? and strftime(?, "created_at") = cast(? as text)', 'normalized' => 'select sum(?) as aggregate from "bookings" where "status" = ? and strftime(?, ?) = cast(? as text)', 'duration' => 0.2, 'file' => null, 'line' => null],
        ['sql' => 'select sum("total_amount") as aggregate from "bookings" where "status" = ? and strftime(?, "created_at") = cast(? as text)', 'normalized' => 'select sum(?) as aggregate from "bookings" where "status" = ? and strftime(?, ?) = cast(? as text)', 'duration' => 0.2, 'file' => null, 'line' => null],
    ];

    $result = $this->analyzer->analyze($queries);

    expect($result['hasNPlusOne'])->toBeTrue();
    expect($result['suggestions'][0])->toContain('Aggregate query');
    expect($result['suggestions'][0])->toContain('subquery');
    expect($result['suggestions'][0])->not->toContain('eager load');
});
