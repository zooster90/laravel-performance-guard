<?php

declare(strict_types=1);

namespace Zufarmarwah\PerformanceGuard\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class AuthorizeDashboard
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->isAuthorized($request)) {
            abort(403, 'Unauthorized access to Performance Guard dashboard.');
        }

        return $next($request);
    }

    private function isAuthorized(Request $request): bool
    {
        if (! config('performance-guard.dashboard.auth', true)) {
            return true;
        }

        if ($this->isAllowedIp($request)) {
            return true;
        }

        if ($this->isAllowedEmail($request)) {
            return true;
        }

        if ($this->passesGate($request)) {
            return true;
        }

        return false;
    }

    private function isAllowedIp(Request $request): bool
    {
        $allowedIps = config('performance-guard.dashboard.allowed_ips', []);

        if (empty($allowedIps)) {
            return false;
        }

        return in_array($request->ip(), $allowedIps, true);
    }

    private function isAllowedEmail(Request $request): bool
    {
        $allowedEmails = config('performance-guard.dashboard.allowed_emails', []);

        if (empty($allowedEmails)) {
            return false;
        }

        $user = $request->user();

        if ($user === null || ! isset($user->email)) {
            return false;
        }

        return in_array($user->email, $allowedEmails, true);
    }

    private function passesGate(Request $request): bool
    {
        $user = $request->user();

        if ($user === null) {
            return false;
        }

        $gate = config('performance-guard.dashboard.gate', 'viewPerformanceGuard');

        if (Gate::has($gate)) {
            return Gate::forUser($user)->allows($gate);
        }

        return false;
    }
}
