<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class MobileTransport
{
    public function handle(Request $request, Closure $next)
    {
        abort_if(app()->environment('production') && ! $request->secure(), 400, 'La API móvil requiere HTTPS.');
        $response = $next($request);
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }
}
