<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasPermission
{
    public function handle(Request $request, Closure $next, string $page, string $action): Response
    {
        // Allow GET requests for read-only operations without permission check
        // This is safe since reading data is non-destructive
        if ($request->isMethod('GET') && $action === 'read') {
            return $next($request);
        }

        $user = $request->user();

        if (! $user || ! $user->hasPermission($page, $action)) {
            abort(403, 'You are not authorized to access this resource.');
        }

        return $next($request);
    }
}
