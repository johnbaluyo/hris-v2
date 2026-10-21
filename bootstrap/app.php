<?php

use App\Http\Middleware\EnsureAccountEligible;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [EnsureAccountEligible::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (QueryException $exception, Request $request) {
            $conflict = in_array((string) $exception->getCode(), ['23000', '23505'], true);
            $message = $conflict ? 'The record conflicts with another change. Refresh the affected records and try again.' : 'The requested data is temporarily unavailable. Please try again or contact HR.';

            return $request->expectsJson() || $request->is('api/*')
                ? response()->json(['message' => $message], $conflict ? 409 : 503)
                : response($message, $conflict ? 409 : 503)->header('Content-Type', 'text/plain');
        });
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
