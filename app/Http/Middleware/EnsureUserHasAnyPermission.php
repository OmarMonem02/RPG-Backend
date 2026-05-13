<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasAnyPermission
{
    /**
     * @param  string  ...$pageActionPairs  Pairs: page, action, page, action, ...
     */
    public function handle(Request $request, Closure $next, string ...$pageActionPairs): Response
    {
        $user = $request->user();

        if (count($pageActionPairs) % 2 !== 0) {
            abort(500, 'any_permission middleware requires an even number of page,action arguments.');
        }

        if (! $user) {
            abort(403, 'You are not authorized to access this resource.');
        }

        for ($i = 0; $i < count($pageActionPairs); $i += 2) {
            $page = $pageActionPairs[$i];
            $action = $pageActionPairs[$i + 1];
            if ($user->hasPermission($page, $action)) {
                return $next($request);
            }
        }

        abort(403, 'You are not authorized to access this resource.');
    }
}
