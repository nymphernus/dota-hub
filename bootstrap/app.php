<?php

use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            SetLocale::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->renderable(function (HttpException $exception) {
            if ($exception->getStatusCode() === 502) {
                return response()->view('errors.502', [], 502);
            }

            if ($exception->getStatusCode() === 504) {
                return response()->view('errors.504', [], 504);
            }
        });
    })->create();
