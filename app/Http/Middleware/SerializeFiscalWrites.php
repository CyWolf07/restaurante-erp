<?php

namespace App\Http\Middleware;

use App\Services\PosOperationService;
use Closure;
use Illuminate\Http\Request;

class SerializeFiscalWrites
{
    public function handle(Request $request, Closure $next)
    {
        return app(PosOperationService::class)->run(fn () => $next($request), false);
    }
}
