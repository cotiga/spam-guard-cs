<?php

namespace Cotiga\SpamGuard\Services;

use Cotiga\SpamGuard\Models\BannedIp;
use Cotiga\SpamGuard\Models\ErrorIgnored;
use Cotiga\SpamGuard\Models\HttpError;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
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

        // Notre propre vérification de lien (voir refererCitesUrl) : ni
        // journalisation ni ban, le serveur ne doit pas se bannir lui-même.
        if ($request->headers->has('X-Coti-Linkcheck')) {
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
     * 404 dont on a la PREUVE qu'une page du site pointe dessus — lien mort
     * réel, page supprimée. Les 404 de robots et de scanners, qui forment
     * l'essentiel du volume, restent consultables dans l'écran Erreurs HTTP
     * sans rien envoyer. Une URL n'alerte qu'une fois, et un plafond journalier
     * borne le total.
     */
    protected function maybeAlert(HttpError $error, Request $request): void
    {
        $email = config('spam-guard.alert_email');
        $status = (int) $error->status_code;
        $count = (int) $error->count;

        if (! $email) {
            return;
        }

        // Déjà signalée : le journal suffit, on ne renvoie jamais la même URL.
        if ($error->alerted_at) {
            return;
        }

        if ($status >= 500) {
            $threshold = (int) config('spam-guard.alert_5xx_threshold', 1);
        } elseif ($status === 404) {
            $threshold = (int) config('spam-guard.alert_threshold', 3);
        } else {
            return;
        }

        if ($threshold < 1 || $count < $threshold) {
            return;
        }

        // Le test coûteux vient en dernier : seuil franchi, jamais alertée.
        if ($status === 404 && ! $this->refererCitesUrl($request)) {
            return;
        }

        if (! $this->withinDailyQuota()) {
            return;
        }

        try {
            $site = config('app.name', 'Site');
            $corps = "Erreur {$status} — {$count} occurrence(s)\n"
                .'URL : '.$request->fullUrl()."\n"
                .($status === 404
                    ? 'Lien mort trouvé sur : '.$error->referer."\n"
                    : 'Provenance : '.($error->referer ?: 'directe / inconnue')."\n")
                .'IP : '.($error->ip ?: 'inconnue')."\n"
                .'Message : '.($error->error_message ?: 'aucun');

            $objet = $status === 404
                ? "{$site} — Lien mort sur le site"
                : "{$site} — Erreur {$status}";

            Mail::raw($corps, fn ($m) => $m->to($email)->subject($objet));

            $error->forceFill(['alerted_at' => now()])->save();
        } catch (Throwable) {
        }
    }

    /**
     * Seule preuve recevable d'un lien mort : aller lire la page citée par le
     * Referer et y trouver réellement le lien vers l'URL en 404.
     *
     * L'en-tête Referer est écrit par le client : un robot qui y colle le
     * domaine du site passe pour un visiteur interne. C'est ce qui a rempli la
     * boîte de 404 de scanners. On ne lui fait donc plus confiance — il ne
     * sert qu'à désigner la page à vérifier.
     *
     * Le contrôle n'a lieu qu'au moment où un mail partirait, son résultat est
     * gardé pour la journée et leur nombre est plafonné : quelques requêtes
     * HTTP par jour et par site, au plus.
     */
    protected function refererCitesUrl(Request $request): bool
    {
        $referer = $request->headers->get('referer');

        if (! $referer || ! $this->isInternalHost($referer, $request)) {
            return false;
        }

        $cible = $this->cheminNormalise($request->fullUrl());
        $source = $this->cheminNormalise($referer);

        // Une page qui se cite elle-même ne prouve rien.
        if ($cible === '' || $cible === $source) {
            return false;
        }

        try {
            $cle = 'spam-guard:linkcheck:'.md5($source.'|'.$cible);

            if (($connu = Cache::get($cle)) !== null) {
                return (bool) $connu;
            }

            if (! $this->withinDailyCheckQuota()) {
                return false;
            }

            $cite = $this->pageCite($referer, $cible);

            Cache::put($cle, $cite, now()->endOfDay());

            return $cite;
        } catch (Throwable) {
            // Réseau ou cache indisponible : pas de preuve, donc pas de mail.
            return false;
        }
    }

    /**
     * Charge la page source et cherche un href/src pointant vers le chemin en
     * 404. L'en-tête X-Coti-Linkcheck évite que cet appel se journalise ou
     * fasse bannir le serveur par son propre garde.
     */
    protected function pageCite(string $referer, string $cible): bool
    {
        $reponse = Http::withHeaders(['X-Coti-Linkcheck' => '1'])
            ->connectTimeout(2)
            ->timeout(4)
            ->withoutRedirecting()
            ->get($referer);

        if (! $reponse->successful()) {
            return false;
        }

        $html = Str::limit($reponse->body(), 500000, '');

        if (! preg_match_all('#(?:href|src)\s*=\s*["\']([^"\']+)["\']#i', $html, $liens)) {
            return false;
        }

        foreach ($liens[1] as $lien) {
            if ($this->cheminNormalise($lien, $referer) === $cible) {
                return true;
            }
        }

        return false;
    }

    /**
     * Chemin + query d'une URL, éventuellement relative à une base, ramenés à
     * une forme comparable (décodée, sans slash final, sans ancre).
     */
    protected function cheminNormalise(string $url, ?string $base = null): string
    {
        $url = trim($url);

        if ($url === '' || Str::startsWith($url, ['#', 'mailto:', 'tel:', 'javascript:', 'data:'])) {
            return '';
        }

        // Lien relatif : on le raccroche au dossier de la page source.
        if ($base && ! preg_match('#^(?:[a-z][a-z0-9+.-]*:)?//#i', $url)) {
            $racine = preg_replace('#(?<!/)/[^/]*$#', '/', parse_url($base, PHP_URL_PATH) ?: '/');
            $url = Str::startsWith($url, '/') ? $url : $racine.$url;
        }

        $parties = parse_url($url);
        $chemin = rawurldecode($parties['path'] ?? '');
        $query = $parties['query'] ?? '';

        $chemin = rtrim($chemin, '/');

        return $query !== '' ? $chemin.'?'.$query : $chemin;
    }

    protected function isInternalHost(string $url, Request $request): bool
    {
        $hote = parse_url($url, PHP_URL_HOST);

        if (! $hote) {
            return false;
        }

        $normaliser = fn (string $h) => Str::lower(Str::replaceStart('www.', '', $h));

        return $normaliser($hote) === $normaliser($request->getHost());
    }

    /**
     * Borne dure sur le nombre de vérifications de lien par jour : même sous
     * un scan massif, le site ne s'interroge lui-même qu'un nombre fini de fois.
     */
    protected function withinDailyCheckQuota(): bool
    {
        $max = (int) config('spam-guard.alert_404_max_checks_per_day', 50);

        if ($max < 1) {
            return false;
        }

        $cle = 'spam-guard:linkchecks:'.now()->toDateString();
        Cache::add($cle, 0, now()->endOfDay());

        return (int) Cache::increment($cle) <= $max;
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
