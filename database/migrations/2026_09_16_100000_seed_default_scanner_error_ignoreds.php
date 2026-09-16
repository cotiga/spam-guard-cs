<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sondes de scanners ignorées d'office : ces chemins n'existent sur aucun site
 * CS, ils sortent avant journalisation (4 à 5 requêtes SQL économisées par hit,
 * et c'est l'essentiel du volume).
 *
 * Complète les motifs WordPress de 2026_07_14. Idempotent : respecte les motifs
 * ajoutés à la main.
 */
return new class extends Migration
{
    private array $patterns = [
        '*.env*',           // .env, .env.bak, .env.save…
        '*.git*',           // .git/config, .gitignore
        '*.aws*',           // credentials cloud
        '*vendor/*',        // sondes phpunit, composer
        '*phpunit*',
        '*phpmyadmin*',
        '*pma*',
        '*myadmin*',
        '*adminer*',
        '*.php',            // aucune route CS ne finit en .php
        '*.asp*',           // .asp, .aspx
        '*.jsp',
        '*cgi-bin*',
        '*autodiscover*',   // sondes Exchange
        '*owa/*',
        '*telerik*',
        '*solr*',
        '*jenkins*',
        '*actuator*',       // endpoints Spring
        '*.well-known/traffic-advice*',
        '*ads.txt',
        '*app-ads.txt',
        '*shell*',
        '*eval-stdin*',
        // Pas d'extension de document ici (.pdf, .zip, .doc…) : un fichier
        // téléchargeable absent est un lien mort à signaler, pas du bruit.
    ];

    public function up(): void
    {
        if (! Schema::hasTable('spam_guard_error_ignoreds')) {
            return;
        }

        foreach ($this->patterns as $pattern) {
            $exists = DB::table('spam_guard_error_ignoreds')
                ->where('pattern', $pattern)
                ->exists();

            if (! $exists) {
                DB::table('spam_guard_error_ignoreds')->insert([
                    'pattern' => $pattern,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('spam_guard_error_ignoreds')) {
            return;
        }

        DB::table('spam_guard_error_ignoreds')
            ->whereIn('pattern', $this->patterns)
            ->delete();
    }
};
