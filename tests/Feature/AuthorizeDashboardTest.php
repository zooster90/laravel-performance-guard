<?php

declare(strict_types=1);

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Gate;

beforeEach(function () {
    $this->artisan('migrate');
});

it('blocks unauthenticated users when auth is enabled', function () {
    config([
        'performance-guard.dashboard.auth' => true,
        'performance-guard.dashboard.allowed_ips' => [],
        'performance-guard.dashboard.allowed_emails' => [],
    ]);

    $response = $this->get('/performance-guard');

    $response->assertStatus(403);
});

it('allows access when auth is disabled', function () {
    config([
        'performance-guard.dashboard.auth' => false,
        'performance-guard.dashboard.cache_ttl' => 0,
    ]);

    $response = $this->get('/performance-guard');

    $response->assertStatus(200);
});

it('allows access from whitelisted IPs', function () {
    config([
        'performance-guard.dashboard.auth' => true,
        'performance-guard.dashboard.allowed_ips' => ['127.0.0.1'],
        'performance-guard.dashboard.allowed_emails' => [],
        'performance-guard.dashboard.cache_ttl' => 0,
    ]);

    $response = $this->get('/performance-guard', ['REMOTE_ADDR' => '127.0.0.1']);

    $response->assertStatus(200);
});

it('blocks access from non-whitelisted IPs', function () {
    config([
        'performance-guard.dashboard.auth' => true,
        'performance-guard.dashboard.allowed_ips' => ['10.0.0.1'],
        'performance-guard.dashboard.allowed_emails' => [],
    ]);

    $response = $this->get('/performance-guard');

    $response->assertStatus(403);
});

it('allows access via gate authorization', function () {
    config([
        'performance-guard.dashboard.auth' => true,
        'performance-guard.dashboard.allowed_ips' => [],
        'performance-guard.dashboard.allowed_emails' => [],
        'performance-guard.dashboard.gate' => 'viewPerformanceGuard',
        'performance-guard.dashboard.cache_ttl' => 0,
    ]);

    Gate::define('viewPerformanceGuard', function () {
        return true;
    });

    $user = new class extends Authenticatable
    {
        protected $guarded = [];

        public function getAuthIdentifierName(): string
        {
            return 'id';
        }

        public function getAuthIdentifier(): mixed
        {
            return 1;
        }
    };

    $response = $this->actingAs($user)->get('/performance-guard');

    $response->assertStatus(200);
});
