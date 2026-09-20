<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Sans le nom ni le message, un refus est indiagnosticable : on ne peut pas savoir
// si le garde a écarté un bot ou un vrai prospect. 500 caractères suffisent à
// trancher — inutile de stocker les pavés des bots.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spam_guard_refused_contacts', function (Blueprint $table) {
            if (! Schema::hasColumn('spam_guard_refused_contacts', 'nom')) {
                $table->string('nom')->nullable()->after('mel');
            }
            if (! Schema::hasColumn('spam_guard_refused_contacts', 'message')) {
                $table->string('message', 500)->nullable()->after('raison');
            }
        });
    }

    public function down(): void
    {
        Schema::table('spam_guard_refused_contacts', function (Blueprint $table) {
            $table->dropColumn(['nom', 'message']);
        });
    }
};
