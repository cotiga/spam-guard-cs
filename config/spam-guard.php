<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Email d'alerte pour les erreurs récurrentes
    |--------------------------------------------------------------------------
    */
    'alert_email' => env('SPAM_GUARD_ALERT_EMAIL', 'support@cotiga-world.com'),

    /*
    |--------------------------------------------------------------------------
    | Alertes mail — ce qui sort par mail et à quelle dose
    |--------------------------------------------------------------------------
    | Seules deux situations méritent un mail : une erreur serveur (5xx) et un
    | 404 dont on a la preuve qu'une page du site pointe dessus. Cette preuve
    | n'est PAS l'en-tête Referer, que le robot écrit lui-même : le garde va
    | lire la page citée et y cherche le lien. Sans lien trouvé, pas de mail.
    |
    | Une URL n'alerte qu'une seule fois. Les 404 de scanners restent au
    | journal, dans l'écran Erreurs HTTP.
    */
    'alert_threshold' => env('SPAM_GUARD_ALERT_THRESHOLD', 3),       // 404 : occurrences avant de vérifier le lien
    'alert_5xx_threshold' => env('SPAM_GUARD_ALERT_5XX_THRESHOLD', 1), // erreurs serveur : dès la première
    'alert_max_per_day' => env('SPAM_GUARD_ALERT_MAX_PER_DAY', 20),  // plafond de mails par jour, 0 = aucun mail
    'alert_404_max_checks_per_day' => env('SPAM_GUARD_ALERT_404_MAX_CHECKS', 50), // vérifications de lien par jour

    /*
    |--------------------------------------------------------------------------
    | Nombre d'erreurs depuis une même IP (sur la journée) avant ban automatique
    |--------------------------------------------------------------------------
    */
    'ban_threshold' => env('SPAM_GUARD_BAN_THRESHOLD', 30),

    /*
    |--------------------------------------------------------------------------
    | IP de confiance : jamais journalisées ni bannies (loopback local par défaut).
    | Évite l'auto-ban en dev. Ajouter ici d'éventuelles IP internes.
    |--------------------------------------------------------------------------
    */
    'trusted_ips' => ['127.0.0.1', '::1'],

    /*
    |--------------------------------------------------------------------------
    | Rate limiting formulaires
    |--------------------------------------------------------------------------
    */
    'rate_limit_max_attempts' => 3,
    'rate_limit_decay_seconds' => 3600,

    /*
    |--------------------------------------------------------------------------
    | Pays bloqués (codes ISO 3166-1 alpha-2)
    |--------------------------------------------------------------------------
    */
    'blocked_countries' => [
        'RU', 'BY', 'UA', 'KZ', 'SU', 'AM', 'AZ', 'GE', 'KG', 'TJ', 'TM', 'UZ',
        'CN', 'IN', 'PK', 'BD', 'VN', 'ID', 'IR', 'NG', 'EG', 'KE',
    ],

    /*
    |--------------------------------------------------------------------------
    | TLD d'email bloqués
    |--------------------------------------------------------------------------
    */
    'blocked_tlds' => [
        'ru', 'su', 'рф', 'by', 'kz', 'ua', 'cn', 'in', 'ir', 'vn', 'pk', 'bd', 'ng',
        'xyz', 'top', 'buzz', 'icu', 'cyou',
    ],

    /*
    |--------------------------------------------------------------------------
    | Mots-clés suspects dans les domaines email
    |--------------------------------------------------------------------------
    */
    'blocked_domain_keywords' => [
        'casino', 'gambling', 'poker', 'betting', 'crypto', 'bitcoin', 'forex', 'trading',
        'viagra', 'cialis', 'pharma', 'pills', 'drug', 'supplement',
        'seo', 'marketing', 'backlink', 'traffic', 'rank',
        'loan', 'credit', 'payday', 'cash',
        'porn', 'adult', 'xxx', 'sex', 'dating', 'hookup',
        'tempmail', 'mailinator', 'guerrilla', 'throwaway', 'fakeinbox', 'temp-mail', 'emailondeck',
    ],

    /*
    |--------------------------------------------------------------------------
    | Patterns regex suspects dans les adresses email
    |--------------------------------------------------------------------------
    */
    'suspicious_email_patterns' => [
        '/^[a-z]{20,}\d{2,}@/i',
        // Seuil à 7 chiffres : à 5, le motif refusait de vrais prospects qui mettent
        // leur code postal ou leur année de naissance dans leur adresse
        // (micela74200@yahoo.fr, issey93600@gmail.com…). Mesuré sur le corpus deuxia :
        // 68 refus à tort sur 89 détections. Un refus est silencieux côté visiteur.
        '/\d{7,}@/',
        '/^[A-Z0-9]{20,}@/',
    ],

    /*
    |--------------------------------------------------------------------------
    | Patterns regex de contenu spam dans les champs du formulaire
    |--------------------------------------------------------------------------
    */
    'spam_content_patterns' => [
        '/^[A-Z]{15,}$/',
        '/^[a-zA-Z]{30,}$/',
        '/^[A-Z][a-z]{20,}[A-Z]/',
    ],

    /*
    |--------------------------------------------------------------------------
    | Message générique : plus aucune détection — retiré le 2026-09-20
    |--------------------------------------------------------------------------
    | « Demande de devis », « Merci de me recontacter », « Je souhaite avoir des
    | informations » : c'est exactement ce qu'écrit un vrai prospect. Aucun critère
    | ne distingue ces messages d'un bot, et le refus est silencieux — le visiteur
    | croit son message envoyé. Les clés generic_bot_messages, vague_words et
    | generic_message sont supprimées, ne pas les réintroduire.
    */

    /*
    |--------------------------------------------------------------------------
    | Détection de nom "charabia"
    |--------------------------------------------------------------------------
    */
    'gibberish' => [
        'min_length_without_space' => 12,
        'vowel_consonant_ratio'    => 0.2,
        'max_case_transitions'     => 5,
        'max_consonant_sequence'   => 5,
    ],

    /*
    |--------------------------------------------------------------------------
    | Détection de message charabia (token sans espace)
    |--------------------------------------------------------------------------
    | Un bloc d'au moins 30 caractères sans la moindre espace. Une adresse e-mail
    | laissée en guise de message est exclue par le code.
    */
    'gibberish_message' => [
        'min_length_without_space' => 30,
    ],

];
