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
    | Seuil de déclenchement de l'email (nombre d'occurrences de la même erreur)
    |--------------------------------------------------------------------------
    */
    'alert_threshold' => env('SPAM_GUARD_ALERT_THRESHOLD', 10),

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
    | Messages génériques de bots (similarité > gibberish.similarity_threshold %)
    |--------------------------------------------------------------------------
    */
    'generic_bot_messages' => [
        'je souhaite avoir des informations',
        'i would like to get more information',
        'please contact me',
        'i am interested in your services',
        'veuillez me contacter',
        'looking for a quote',
        'demande de devis',
        'request for information',
    ],

    /*
    |--------------------------------------------------------------------------
    | Mots vagues dans les messages courts
    |--------------------------------------------------------------------------
    */
    'vague_words' => [
        'information', 'informations', 'renseignement', 'contact', 'services',
    ],

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
    | Détection de message générique
    |--------------------------------------------------------------------------
    */
    'generic_message' => [
        'similarity_threshold' => 70,
        'short_max_words'      => 8,
        'short_max_length'     => 60,
    ],

    /*
    |--------------------------------------------------------------------------
    | Détection de message charabia (token sans espace)
    |--------------------------------------------------------------------------
    */
    'gibberish_message' => [
        'min_length_without_space' => 20,
    ],

];
