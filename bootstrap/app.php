<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Force all API routes to expect JSON so errors never return HTML
        $middleware->api(prepend: [
            \App\Http\Middleware\ForceJsonResponse::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Always return JSON for API requests — never HTML error pages
        $exceptions->render(function (\Throwable $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                $status = $e instanceof HttpException ? $e->getStatusCode() : 500;

                $response = [
                    'error'   => class_basename($e),
                    'message' => $e->getMessage() ?: 'Internal server error.',
                ];

                // Only include trace in non-production
                if (config('app.debug')) {
                    $response['file'] = $e->getFile() . ':' . $e->getLine();
                    $response['trace'] = array_slice(explode("\n", $e->getTraceAsString()), 0, 10);
                }

                \Illuminate\Support\Facades\Log::error("KYC EXCEPTION: {$e->getMessage()}", [
                    'status'    => $status,
                    'file'      => $e->getFile() . ':' . $e->getLine(),
                    'url'       => $request->fullUrl(),
                    'method'    => $request->method(),
                    'ip'        => $request->ip(),
                ]);

                return response()->json($response, $status);
            }
        });
    })->create();
