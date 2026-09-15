<?php

namespace Cotiga\SpamGuard\Services;

use Cotiga\SpamGuard\Models\BannedIp;
use Cotiga\SpamGuard\Models\ErrorIgnored;
use Cotiga\SpamGuard\Models\HttpError;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
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

        // 0) IP de confiance (loopback local par défaut, IP internes déclarées) :
        //    ni journalisation ni ban → comportement natif de Laravel. Évite
        //    l'auto-ban en développement où l'on génère beaucoup d'erreurs.
        if ($ip && in_array($ip, (array) config('spam-guard.trusted_ips', ['127.0.0.1', '::1']), true)) {
            return null;
        }

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
        $error = $this->log($status, $request, $e, $ip);

        // 4) Alerte mail — uniquement 5xx et liens morts réels, voir maybeAlert().
        if ($error) {
            $this->maybeAlert($error, $request);
        }

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

    protected function log(int $status, Request $request, Throwable $e, ?string $ip): ?HttpError
    {
        try {
            $error = HttpError::firstOrNew([
                'status_code' => $status,
                'url' => Str::limit($request->fullUrl(), 255, ''),
            ]);

            $referer = $request->headers->get('referer');

            $error->ip = $ip;
            // Un passage de robot (sans referer) n'efface pas la provenance
            // interne déjà relevée : c'est elle qui qualifie le lien mort.
            if ($referer) {
                $error->referer = Str::limit($referer, 255, '');
            }
            $error->user_agent = $request->userAgent();
            $error->error_message = $e->getMessage() ?: 'Erreur inconnue';
            $error->count = ($error->count ?? 0) + 1;
            $error->save();

            return $error;
        } catch (Throwable $ex) {
            Log::error('SpamGuard — journalisation impossible : '.$ex->getMessage(), [
                'url' => $request->fullUrl(),
                'status' => $status,
            ]);

            return null;
        }
    }

    /**
     * Deux situations seulement justifient un mail : une erreur serveur, et un
     * 404 atteint depuis une page du site — soit un lien mort, soit une page
     * qui a existé. Les 404 de robots et de scanners, qui forment l'essentiel
     * du volume, restent consultables dans l'écran Erreurs HTTP sans rien
     * envoyer. Chaque URL n'alerte qu'une fois par jour, et un plafond
     * journalier borne le total.
     */
    protected function maybeAlert(HttpError $error, Request $request): void
    {
        $email = config('spam-guard.alert_email');
        $status = (int) $error->status_code;
        $count = (int) $error->count;

        if (! $email) {
            return;
        }

        if ($status >= 500) {
            $threshold = (int) config('spam-guard.alert_5xx_threshold', 1);
        } elseif ($status === 404 && $this->isInternalReferer($error->referer, $request)) {
            $threshold = (int) config('spam-guard.alert_threshold', 10);
        } else {
            return;
        }

        if ($threshold < 1 || $count < $threshold) {
            return;
        }

        // Déjà signalée aujourd'hui : on n'y revient pas.
        if ($error->alerted_at && $error->alerted_at->isToday()) {
            return;
        }

        if (! $this->withinDailyQuota()) {
            return;
        }

        try {
            $site = config('app.name', 'Site');
            $corps = "Erreur {$status} — {$count} occurrence(s)\n"
                .'URL : '.$request->fullUrl()."\n"
                .'Provenance : '.($error->referer ?: 'directe / inconnue')."\n"
                .'IP : '.($error->ip ?: 'inconnue')."\n"
                .'Message : '.($error->error_message ?: 'aucun');

            Mail::raw(
                $corps,
                fn ($m) => $m->to($email)->subject("{$site} — Erreur {$status} récurrente")
            );

            $error->forceFill(['alerted_at' => now()])->save();
        } catch (Throwable) {
        }
    }

    /**
     * Le visiteur venait-il d'une page du site lui-même ? C'est ce qui sépare
     * un lien mort d'un scan de robot.
     */
    protected function isInternalReferer(?string $referer, Request $request): bool
    {
        if (! $referer) {
            return false;
        }

        $hote = parse_url($referer, PHP_URL_HOST);

        if (! $hote) {
            return false;
        }

        $normaliser = fn (string $h) => Str::lower(Str::replaceStart('www.', '', $h));

        return $normaliser($hote) === $normaliser($request->getHost());
    }

    /**
     * Plafond journalier, tous statuts confondus : un incident en rafale ne
     * doit jamais remplir une boîte ni faire passer l'expéditeur pour un
     * spammeur.
     */
    protected function withinDailyQuota(): bool
    {
        $max = (int) config('spam-guard.alert_max_per_day', 20);

        if ($max < 1) {
            return false;
        }

        try {
            $cle = 'spam-guard:alerts:'.now()->toDateString();
            Cache::add($cle, 0, now()->endOfDay());

            return (int) Cache::increment($cle) <= $max;
        } catch (Throwable) {
            // Cache indisponible : on laisse passer plutôt que de perdre l'alerte.
            return true;
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
