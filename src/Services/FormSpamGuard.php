<?php

namespace Cotiga\SpamGuard\Services;

use Cotiga\SpamGuard\Models\BannedEmail;
use Cotiga\SpamGuard\Models\BannedIp;
use Cotiga\SpamGuard\Models\RefusedContact;
use Illuminate\Support\Facades\RateLimiter;
use Stevebauman\Location\Facades\Location;

class FormSpamGuard
{
    /**
     * Règle de fond : un refus est SILENCIEUX pour le visiteur (faux succès). Un faux
     * positif est donc un prospect perdu sans que personne ne le sache. Aucun critère
     * qu'un vrai client peut déclencher en écrivant normalement ne doit refuser.
     */
    public function isSpam(string $email, string $ip, array $formData = [], string $formName = 'contact'): bool
    {
        // IP de confiance (loopback local par défaut, IP internes déclarées) : jamais
        // considérée comme spam. Évite les faux positifs en développement (rate-limit…).
        if ($ip && in_array($ip, (array) config('spam-guard.trusted_ips', ['127.0.0.1', '::1']), true)) {
            return false;
        }

        // IP bannie (manuellement depuis l'admin ou par ban auto) → refus immédiat.
        if ($ip && BannedIp::where('ip', $ip)->exists()) {
            $this->logRefusal($email, $ip, 'N/A', 'IP bannie', $formName, $formData);
            return true;
        }

        // E-mail banni manuellement depuis l'admin → refus immédiat (comparaison normalisée).
        if ($email !== '' && BannedEmail::where('mel', mb_strtolower(trim($email)))->exists()) {
            $this->logRefusal($email, $ip, 'N/A', 'E-mail banni', $formName, $formData);
            return true;
        }

        $maxAttempts = config('spam-guard.rate_limit_max_attempts', 3);
        $decay       = config('spam-guard.rate_limit_decay_seconds', 3600);
        $key         = 'form-submit:'.$ip;

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            $this->logRefusal($email, $ip, 'N/A', 'Rate limit dépassé ('.$maxAttempts.'/heure)', $formName, $formData);
            return true;
        }
        RateLimiter::hit($key, $decay);

        $tld = $this->extractTld($email);
        if (in_array($tld, config('spam-guard.blocked_tlds', []))) {
            $this->logRefusal($email, $ip, 'N/A', "TLD interdit : .{$tld}", $formName, $formData);
            return true;
        }

        $domain = $this->extractDomain($email);
        foreach (config('spam-guard.blocked_domain_keywords', []) as $keyword) {
            if (stripos($domain, $keyword) !== false) {
                $this->logRefusal($email, $ip, 'N/A', "Domaine suspect : {$domain} (mot-clé: {$keyword})", $formName, $formData);
                return true;
            }
        }

        foreach (config('spam-guard.suspicious_email_patterns', []) as $pattern) {
            if (preg_match($pattern, $email)) {
                $this->logRefusal($email, $ip, 'N/A', 'Pattern email suspect détecté', $formName, $formData);
                return true;
            }
        }

        $nom = $formData['nom'] ?? null;
        if ($nom && $this->isGibberishName($nom)) {
            $this->logRefusal($email, $ip, 'N/A', "Nom charabia détecté : {$nom}", $formName, $formData);
            return true;
        }

        // Le téléphone n'est JAMAIS validé : poste, indicatif entre parenthèses, deux
        // numéros… toute regex finit par refuser un numéro légitime.

        $msg = $formData['msg'] ?? null;
        if ($msg) {
            if ($this->isGibberishMessage($msg)) {
                $this->logRefusal($email, $ip, 'N/A', 'Message charabia (token sans espace) détecté', $formName, $formData);
                return true;
            }
            if ($this->containsForeignUrl($msg)) {
                $this->logRefusal($email, $ip, 'N/A', 'URL externe détectée dans le message', $formName, $formData);
                return true;
            }
            if ($this->containsNonLatinChars($msg)) {
                $this->logRefusal($email, $ip, 'N/A', 'Caractères non-latins détectés', $formName, $formData);
                return true;
            }
        }

        foreach ($formData as $field => $value) {
            if (! is_string($value)) {
                continue;
            }
            foreach (config('spam-guard.spam_content_patterns', []) as $pattern) {
                if (preg_match($pattern, $value)) {
                    $this->logRefusal($email, $ip, 'N/A', "Contenu spam détecté dans : {$field}", $formName, $formData);
                    return true;
                }
            }
        }

        try {
            $location = Location::get($ip);
            $country  = $location?->countryCode ?? null;
            if ($country && in_array($country, config('spam-guard.blocked_countries', []))) {
                $this->logRefusal($email, $ip, $country, "Pays bloqué : {$country}", $formName, $formData);
                return true;
            }
        } catch (\Exception $e) {
            // Localisation impossible, on continue
        }

        return false;
    }

    /**
     * Journalise le refus AVEC le nom et le message soumis : sans eux, un faux positif
     * est indiagnosticable depuis l'écran « Refus antispam ». Message coupé à 500
     * caractères : de quoi juger, sans stocker les pavés des bots.
     */
    public function logRefusal(string $email, string $ip, string $pays, string $reason, string $formName, array $formData = []): void
    {
        RefusedContact::create([
            'form_name' => $formName,
            'mel'       => $email,
            'ip'        => $ip,
            'pays'      => $pays ?: 'Inconnu',
            'raison'    => $reason,
            'nom'       => is_string($formData['nom'] ?? null) ? mb_substr($formData['nom'], 0, 255) : null,
            'message'   => is_string($formData['msg'] ?? null) ? mb_substr($formData['msg'], 0, 500) : null,
        ]);
    }

    protected function isGibberishName(string $name): bool
    {
        $cfg  = config('spam-guard.gibberish', []);
        $name = trim($name);

        if (preg_match('/\d/', $name)) {
            return true;
        }

        if (! str_contains($name, ' ') && mb_strlen($name) > ($cfg['min_length_without_space'] ?? 12)) {
            $vowels    = preg_match_all('/[aeiouyàâäéèêëïîôùûü]/iu', $name);
            $consonants = preg_match_all('/[bcdfghjklmnpqrstvwxz]/iu', $name);
            if ($consonants > 0 && $vowels / $consonants < ($cfg['vowel_consonant_ratio'] ?? 0.2)) {
                return true;
            }
        }

        // Seul le camelCase compte (asDF, xKzP). Compter aussi [A-Z][a-z] revenait à
        // compter chaque mot capitalisé : « Jean-Pierre Van Den Berghe » = 5 → refusé.
        $transitions = preg_match_all('/[a-z][A-Z]/', $name);
        if ($transitions >= ($cfg['max_case_transitions'] ?? 5)) {
            return true;
        }

        $maxSeq = $cfg['max_consonant_sequence'] ?? 5;
        if (preg_match('/[bcdfghjklmnpqrstvwxz]{'.$maxSeq.',}/i', $name)) {
            return true;
        }

        return false;
    }

    /**
     * Un seul bloc sans aucune espace. Une adresse e-mail laissée en guise de message
     * est légitime : elle est exclue.
     */
    protected function isGibberishMessage(string $message): bool
    {
        $message = trim($message);
        $min     = config('spam-guard.gibberish_message.min_length_without_space', 30);

        if (str_contains($message, '@')) {
            return false;
        }

        return ! preg_match('/\s/u', $message) && mb_strlen($message) >= $min;
    }

    /**
     * Les liens vers le site lui-même sont légitimes : un visiteur colle l'URL du bien
     * ou de la page qui l'intéresse. Seuls les liens sortants sont un signal de spam.
     */
    protected function containsForeignUrl(string $text): bool
    {
        if (! preg_match_all('#(?:https?://|www\.)([a-z0-9.-]+)#i', $text, $matches)) {
            return false;
        }

        $own = $this->ownDomain();

        foreach ($matches[1] as $host) {
            $host = $this->normalizeHost($host);
            if ($own === '' || ($host !== $own && ! str_ends_with($host, '.'.$own))) {
                return true;
            }
        }

        return false;
    }

    protected function ownDomain(): string
    {
        return $this->normalizeHost((string) (parse_url((string) config('app.url'), PHP_URL_HOST) ?: ''));
    }

    protected function normalizeHost(string $host): string
    {
        return preg_replace('/^www\\./', '', rtrim(mb_strtolower($host), '.'));
    }

    protected function containsNonLatinChars(string $text): bool
    {
        return (bool) preg_match('/[\p{Cyrillic}\p{Han}\p{Arabic}\p{Hebrew}\p{Thai}\p{Hangul}]/u', $text);
    }

    protected function extractTld(string $email): string
    {
        $domain = substr(strrchr($email, '@'), 1);
        return strtolower(substr(strrchr($domain, '.'), 1));
    }

    protected function extractDomain(string $email): string
    {
        return strtolower(substr(strrchr($email, '@'), 1));
    }
}
