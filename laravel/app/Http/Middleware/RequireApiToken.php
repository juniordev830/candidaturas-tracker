<?php
declare(strict_types=1);
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/** One local owner, read-only API. The CLI keeps its existing storage contract. */
final class RequireApiToken
{
    public function handle(Request $request, Closure $next)
    {
        $expected = config('api.token');
        if (!is_string($expected) || strlen($expected) < 32) {
            return response()->json(['error' => 'Configure o token de acesso local.'], 503);
        }
        $provided = $request->bearerToken();
        if (!is_string($provided) || !hash_equals($expected, $provided)) {
            return response()->json(['error' => 'Acesso não autorizado.'], 401)->header('WWW-Authenticate', 'Bearer');
        }
        return $next($request)->header('Cache-Control', 'private, no-store')->header('X-Content-Type-Options', 'nosniff');
    }
}
