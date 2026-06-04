<?php

namespace Cotiga\SpamGuard;

use Cotiga\SpamGuard\Services\HttpErrorGuard;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Http\Request;
use Throwable;

/**
 * Point d'entrée pour brancher la journalisation/ban d'erreurs HTTP dans
 * la configuration d'exceptions de Laravel 11+.
 *
 * Dans bootstrap/app.php :
 *
 *     ->withExceptions(function (Illuminate\Foundation\Configuration\Exceptions $exceptions) {
 *         \Cotiga\SpamGuard\SpamGuardExceptions::register($exceptions);
 *     })
 */
class SpamGuardExceptions
{
    public static function register(Exceptions $exceptions): void
    {
        $exceptions->render(function (Throwable $e, Request $request) {
            return app(HttpErrorGuard::class)->handle($e, $request);
        });
    }
}
