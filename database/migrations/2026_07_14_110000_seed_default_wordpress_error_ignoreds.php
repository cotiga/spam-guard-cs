<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Motifs d'URL ignorés par défaut : les sondes WordPress des bots (wp-admin,
 * wp-includes, wp-login.php, xmlrpc…) polluent le journal d'erreurs HTTP alors
 * qu'aucun site CS n'est sous WordPress. On les ignore d'office.
 *
 * Idempotent : n'insère un motif que s'il n'existe pas déjà (respecte les motifs
 * ajoutés à la main).
 */
return new class extends Migration
{
    private array $patterns = [
        '*wp-*',       // wp-admin, wp-includes, wp-login.php, wp-content…
        '*xmlrpc*',    // xmlrpc.php
        '*wordpress*', // /wordpress/…
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
