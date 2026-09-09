<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table `spam_guard_refused_contacts` à son état final — création consolidée (2026-09-09).
 *
 * Remplace la création d'origine et toutes ses retouches. Ne fait rien là où la
 * table existe déjà : les sites en place gardent leur schéma, seule une
 * installation neuve passe par ici.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('spam_guard_refused_contacts')) {
            return;
        }

        Schema::create('spam_guard_refused_contacts', function (Blueprint $table) {
            $table->id();
            $table->string('form_name')->default('contact');
            $table->string('mel')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('pays', 10)->nullable();
            $table->string('raison');
            $table->timestamps();
            $table->index('ip', 'spam_guard_refused_contacts_ip_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spam_guard_refused_contacts');
    }
};
