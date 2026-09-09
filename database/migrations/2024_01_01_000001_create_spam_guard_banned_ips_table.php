<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table `spam_guard_banned_ips` à son état final — création consolidée (2026-09-09).
 *
 * Remplace la création d'origine et toutes ses retouches. Ne fait rien là où la
 * table existe déjà : les sites en place gardent leur schéma, seule une
 * installation neuve passe par ici.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('spam_guard_banned_ips')) {
            return;
        }

        Schema::create('spam_guard_banned_ips', function (Blueprint $table) {
            $table->id();
            $table->string('ip');
            $table->timestamps();
            $table->unique('ip', 'spam_guard_banned_ips_ip_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spam_guard_banned_ips');
    }
};
