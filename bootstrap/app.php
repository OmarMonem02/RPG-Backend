<?php

use App\Http\Middleware\EnsureUserHasPermission;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'permission' => EnsureUserHasPermission::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\Throwable $e, Request $request) {
            if ($request->is('api/*') || $request->wantsJson()) {
                
                if ($e instanceof \Illuminate\Validation\ValidationException) {
                    return response()->json([
                        'status' => false,
                        'message' => 'Validation error',
                        'errors' => $e->errors()
                    ], 422);
                }

                if ($e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException || 
                    $e instanceof \Symfony\Component\HttpKernel\Exception\NotFoundHttpException) {
                    return response()->json([
                        'status' => false,
                        'message' => 'Resource not found'
                    ], 404);
                }

                if ($e instanceof \Illuminate\Auth\AuthenticationException) {
                    return response()->json([
                        'status' => false,
                        'message' => 'Unauthorized'
                    ], 401);
                }

                if ($e instanceof \Illuminate\Auth\Access\AuthorizationException) {
                    return response()->json([
                        'status' => false,
                        'message' => 'You do not have permission'
                    ], 403);
                }

                if ($e instanceof \App\Exceptions\BusinessLogicException || 
                    $e instanceof \App\Exceptions\StockException || 
                    $e instanceof \App\Exceptions\DiscountException) {
                    return response()->json([
                        'status' => false,
                        'message' => $e->getMessage() ?: 'Bad Request',
                        'errors' => (object)[]
                    ], 400);
                }

                // Final fallback for Server Errors and regular Exceptions
                $statusCode = $e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface 
                    ? $e->getStatusCode() 
                    : 500;
                $message = config('app.debug') ? $e->getMessage() : 'Something went wrong';

                return response()->json([
                    'status' => false,
                    'message' => $message,
                ], $statusCode);
            }
        });
    })->create();
