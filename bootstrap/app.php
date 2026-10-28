<?php

use App\Http\Middleware\EnsureUserNotBlocked;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(append: [EnsureUserNotBlocked::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->respond(function (Response $response) {
            if (request()->is('api/*') && $response->getStatusCode() === 500) {
                return response()->json(['message' => 'Произошла ошибка сервера. Повторите попытку позже.'], 500);
            }

            return $response;
        });
        $exceptions->render(function (QueryException $exception, Request $request) {
            if ($request->is('api/*') && in_array($exception->getCode(), ['23503', '23505'], true)) {
                return response()->json(['message' => 'Изменение невозможно: запись используется или конфликтует с существующими данными.'], 409);
            }
        });
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
