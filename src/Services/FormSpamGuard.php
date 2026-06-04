<?php

namespace Cotiga\SpamGuard\Services;

use Cotiga\SpamGuard\Models\RefusedContact;
use Illuminate\Support\Facades\RateLimiter;
use Stevebauman\Location\Facades\Location;

class FormSpamGuard
{
    public function isSpam(string $email, string $ip, array $formData = [], string $formName = 'contact'): bool
    {
        $maxAttempts = config('spam-guard.rate_limit_max_attempts', 3);
        $decay       = config('spam-guard.rate_limit_decay_seconds', 3600);
        $key         = 'form-submit:'.$ip;

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            $this->logRefusal($email, $ip, 'N/A', 'Rate limit dépassé ('.$maxAttempts.'/heure)', $formName);
            return true;
        }
        RateLimiter::hit($key, $decay);

        $tld = $this->extractTld($email);
        if (in_array($tld, config('spam-guard.blocked_tlds', []))) {
            $this->logRefusal($email, $ip, 'N/A', "TLD interdit : .{$tld}", $formName);
            return true;
        }

        $domain = $this->extractDomain($email);
        foreach (config('spam-guard.blocked_domain_keywords', []) as $keyword) {
            if (stripos($domain, $keyword) !== false) {
                $this->logRefusal($email, $ip, 'N/A', "Domaine suspect : {$domain} (mot-clé: {$keyword})", $formName);
                return true;
            }
        }

        foreach (config('spam-guard.suspicious_email_patterns', []) as $pattern) {
            if (preg_match($pattern, $email)) {
                $this->logRefusal($email, $ip, 'N/A', 'Pattern email suspect détecté', $formName);
                return true;
            }
        }

        $nom = $formData['nom'] ?? null;
        if ($nom && $this->isGibberishName($nom)) {
            $this->logRefusal($email, $ip, 'N/A', "Nom charabia détecté : {$nom}", $formName);
            return true;
        }

        $tel = $formData['tel'] ?? null;
        if ($tel && $this->isInvalidPhone($tel)) {
            $this->logRefusal($email, $ip, 'N/A', "Téléphone invalide : {$tel}", $formName);
            return true;
        }

        $msg = $formData['msg'] ?? null;
        if ($msg) {
            if ($this->isGibberishMessage($msg)) {
                $this->logRefusal($email, $ip, 'N/A', 'Message charabia (token sans espace) détecté', $formName);
                return true;
            }
            if ($this->isGenericBotMessage($msg)) {
                $this->logRefusal($email, $ip, 'N/A', 'Message générique de bot détecté', $formName);
                return true;
            }
            if ($this->containsUrl($msg)) {
                $this->logRefusal($email, $ip, 'N/A', 'URL détectée dans le message', $formName);
                return true;
            }
            if ($this->containsNonLatinChars($msg)) {
                $this->logRefusal($email, $ip, 'N/A', 'Caractères non-latins détectés', $formName);
                return true;
            }
        }

        foreach ($formData as $field => $value) {
            if (! is_string($value)) {
                continue;
            }
            foreach (config('spam-guard.spam_content_patterns', []) as $pattern) {
                if (preg_match($pattern, $value)) {
                    $this->logRefusal($email, $ip, 'N/A', "Contenu spam détecté dans : {$field}", $formName);
                    return true;
                }
            }
        }

        try {
            $location = Location::get($ip);
            $country  = $location?->countryCode ?? null;
            if ($country && in_array($country, config('spam-guard.blocked_countries', []))) {
                $this->logRefusal($email, $ip, $country, "Pays bloqué : {$country}", $formName);
                return true;
            }
        } catch (\Exception $e) {
            // Localisation impossible, on continue
        }

        return false;
    }

    public function logRefusal(string $email, string $ip, string $pays, string $reason, string $formName): void
    {
        RefusedContact::create([
            'form_name' => $formName,
            'mel'       => $email,
            'ip'        => $ip,
            'pays'      => $pays ?: 'Inconnu',
            'raison'    => $reason,
        ]);
    }

    protected function isGibberishName(string $name): bool
    {
        $cfg  = config('spam-guard.gibberish', []);
        $name = trim($name);

        if (preg_match('/\d/', $name)) {
            return true;
        }

        if (! str_contains($name, ' ') && strlen($name) > ($cfg['min_length_without_space'] ?? 12)) {
            $vowels    = preg_match_all('/[aeiouyàâäéèêëïîôùûü]/i', $name);
            $consonants = preg_match_all('/[bcdfghjklmnpqrstvwxz]/i', $name);
            if ($consonants > 0 && $vowels / $consonants < ($cfg['vowel_consonant_ratio'] ?? 0.2)) {
                return true;
            }
        }

        $transitions = preg_match_all('/[a-z][A-Z]|[A-Z][a-z]/', $name);
        if ($transitions >= ($cfg['max_case_transitions'] ?? 5)) {
            return true;
        }

        $maxSeq = $cfg['max_consonant_sequence'] ?? 5;
        if (preg_match('/[bcdfghjklmnpqrstvwxz]{'.$maxSeq.',}/i', $name)) {
            return true;
        }

        return false;
    }

    protected function isGenericBotMessage(string $message): bool
    {
        $cfg         = config('spam-guard.generic_message', []);
        $messageLower = mb_strtolower(trim($message));

        foreach (config('spam-guard.generic_bot_messages', []) as $genericMsg) {
            similar_text($messageLower, $genericMsg, $percent);
            if ($percent > ($cfg['similarity_threshold'] ?? 70)) {
                return true;
            }
        }

        $wordCount = str_word_count($message);
        if ($wordCount <= ($cfg['short_max_words'] ?? 8) && strlen($message) < ($cfg['short_max_length'] ?? 60)) {
            foreach (config('spam-guard.vague_words', []) as $word) {
                if (stripos($message, $word) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    protected function isGibberishMessage(string $message): bool
    {
        $min = config('spam-guard.gibberish_message.min_length_without_space', 20);
        return ! str_contains(trim($message), ' ') && strlen(trim($message)) >= $min;
    }

    protected function isInvalidPhone(string $phone): bool
    {
        $phone = trim($phone);
        if (empty($phone)) {
            return false;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $phone) || preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $phone)) {
            return true;
        }
        if (preg_match('/[a-zA-Z]/', $phone)) {
            return true;
        }
        $digits = preg_replace('/[^\d]/', '', $phone);
        return strlen($digits) < 7 || strlen($digits) > 15;
    }

    protected function containsUrl(string $text): bool
    {
        return (bool) preg_match('/https?:\/\/|www\.|\.com\/|\.fr\/|\.net\/|\.org\//i', $text);
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
