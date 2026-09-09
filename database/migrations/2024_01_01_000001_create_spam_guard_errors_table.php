<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table `spam_guard_errors` à son état final — création consolidée (2026-09-09).
 *
 * Remplace la création d'origine et toutes ses retouches. Ne fait rien là où la
 * table existe déjà : les sites en place gardent leur schéma, seule une
 * installation neuve passe par ici.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('spam_guard_errors')) {
            return;
        }

        Schema::create('spam_guard_errors', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('status_code');
            $table->string('url');
            $table->string('ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->text('error_message')->nullable();
            $table->unsignedInteger('count')->default(1);
            $table->timestamps();
            $table->index('ip', 'spam_guard_errors_ip_index');
            $table->index(['status_code', 'url'], 'spam_guard_errors_status_code_url_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spam_guard_errors');
    }
};
