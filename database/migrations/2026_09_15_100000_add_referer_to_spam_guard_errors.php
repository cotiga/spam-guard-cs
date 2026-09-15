<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('spam_guard_errors')) {
            return;
        }

        Schema::table('spam_guard_errors', function (Blueprint $table) {
            if (! Schema::hasColumn('spam_guard_errors', 'referer')) {
                $table->string('referer', 255)->nullable()->after('url');
            }
            // Date du dernier mail d'alerte : une seule alerte par URL et par jour.
            if (! Schema::hasColumn('spam_guard_errors', 'alerted_at')) {
                $table->timestamp('alerted_at')->nullable()->after('count');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('spam_guard_errors')) {
            return;
        }

        Schema::table('spam_guard_errors', function (Blueprint $table) {
            foreach (['referer', 'alerted_at'] as $column) {
                if (Schema::hasColumn('spam_guard_errors', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
