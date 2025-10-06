<?php

declare(strict_types=1);

use App\Exceptions\TelegramException;
use Illuminate\Foundation\Application;
use App\Http\Middleware\HandleAppearance;
use App\Services\TelegramExceptionService;
use Illuminate\Console\Scheduling\Schedule;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('telescope:prune')->daily();
        $schedule->command('assistant-files:sync')->everyTwoHours();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontReportWhen(function (Throwable $e) {
            return $e instanceof TelegramException;
        })
            ->dontReportDuplicates()
            ->report(function (Throwable $e) {
                TelegramExceptionService::make($e)->send();
            });
    })->create();
