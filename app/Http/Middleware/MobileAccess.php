<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class MobileAccess
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        abort_unless($user?->active && $user->currentAccessToken() instanceof PersonalAccessToken, 401);
        abort_unless($user->tokenCan('mobile:access') && in_array($user->role, ['cook', 'waiter', 'cashier', 'administrator', 'programmer'], true), 403);
        $response = $next($request);
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }
}
