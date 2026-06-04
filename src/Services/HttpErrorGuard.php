<?php

namespace Cotiga\SpamGuard\Services;

use Cotiga\SpamGuard\Models\BannedIp;
use Cotiga\SpamGuard\Models\ErrorIgnored;
use Cotiga\SpamGuard\Models\HttpError;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Journalise les erreurs HTTP, bannit automatiquement les IP abusives et rend
 * la vue d'erreur adaptée. Branché via SpamGuardExceptions dans bootstrap/app.php.
 */
class HttpErrorGuard
{
    public function handle(Throwable $e, Request $request): ?Response
    {
        // Validation et authentification : laissés au comportement natif de Laravel 13.
        if ($e instanceof ValidationException || $e instanceof AuthenticationException) {
            return null;
        }

        $ip = $request->ip();

        // 1) IP déjà bannie → 403 immédiat, sans journalisation.
        if ($ip && $this->isBanned($ip)) {
            return response('Forbidden', Response::HTTP_FORBIDDEN);
        }

        // 2) URL dans la liste d'exclusion → réponse neutre, pas de bruit.
        if ($this->isIgnored($request->path())) {
            return response('Ignored', Response::HTTP_OK);
        }

        $status = $e instanceof HttpExceptionInterface
            ? $e->getStatusCode()
            : Response::HTTP_INTERNAL_SERVER_ERROR;

        // 3) Journalisation (insert ou incrément).
        $count = $this->log($status, $request, $e, $ip);

        // 4) Alerte mail si l'erreur devient récurrente.
        $this->maybeAlert($status, $count, $request);

        // 5) Ban automatique si trop d'erreurs depuis cette IP aujourd'hui.
        if ($ip && $this->errorsToday($ip) >= (int) config('spam-guard.ban_threshold', 30)) {
            BannedIp::firstOrCreate(['ip' => $ip]);

            return $this->view(
                Response::HTTP_FORBIDDEN,
                "Votre adresse IP a été bloquée suite à trop d'erreurs détectées."
            );
        }

        // 6) Vue d'erreur adaptée au statut.
        return $this->view($status);
    }

    protected function isBanned(string $ip): bool
    {
        try {
            return BannedIp::where('ip', $ip)->exists();
        } catch (Throwable) {
            return false;
        }
    }

    protected function isIgnored(string $path): bool
    {
        try {
            foreach (ErrorIgnored::pluck('pattern') as $pattern) {
                $regex = '#^'.str_replace('\*', '.*', preg_quote($pattern, '#')).'$#i';
                if (preg_match($regex, $path)) {
                    return true;
                }
            }
        } catch (Throwable) {
        }

        return false;
    }

    protected function log(int $status, Request $request, Throwable $e, ?string $ip): int
    {
        try {
            $error = HttpError::firstOrNew([
                'status_code' => $status,
                'url' => Str::limit($request->fullUrl(), 255, ''),
            ]);

            $error->ip = $ip;
            $error->user_agent = $request->userAgent();
            $error->error_message = $e->getMessage() ?: 'Erreur inconnue';
            $error->count = ($error->count ?? 0) + 1;
            $error->save();

            return (int) $error->count;
        } catch (Throwable $ex) {
            Log::error('SpamGuard — journalisation impossible : '.$ex->getMessage(), [
                'url' => $request->fullUrl(),
                'status' => $status,
            ]);

            return 0;
        }
    }

    protected function maybeAlert(int $status, int $count, Request $request): void
    {
        $threshold = (int) config('spam-guard.alert_threshold', 10);
        $email = config('spam-guard.alert_email');

        if (! $email || $threshold < 1 || $count < $threshold || $count % $threshold !== 0) {
            return;
        }

        try {
            $site = config('app.name', 'Site');
            Mail::raw(
                "Erreur {$status} détectée {$count} fois sur : ".$request->fullUrl(),
                fn ($m) => $m->to($email)->subject("{$site} — Erreur récurrente")
            );
        } catch (Throwable) {
        }
    }

    protected function errorsToday(string $ip): int
    {
        try {
            return (int) HttpError::where('ip', $ip)
                ->whereDate('created_at', now()->toDateString())
                ->sum('count');
        } catch (Throwable) {
            return 0;
        }
    }

    protected function view(int $status, ?string $reason = null): Response
    {
        return response()->view($this->resolveView($status), [
            'status' => $status,
            'reason' => $reason,
        ], $status);
    }

    protected function resolveView(int $status): string
    {
        if (view()->exists("errors.{$status}")) {
            return "errors.{$status}";
        }

        if (view()->exists('errors.generic')) {
            return 'errors.generic';
        }

        // Fallback packagé (vue standalone sans layout).
        return 'spam-guard::errors.generic';
    }
}
