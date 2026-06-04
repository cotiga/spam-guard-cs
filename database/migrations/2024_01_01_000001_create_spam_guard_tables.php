<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('spam_guard_banned_ips')) {
            Schema::create('spam_guard_banned_ips', function (Blueprint $table) {
                $table->id();
                $table->string('ip')->unique();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('spam_guard_errors')) {
            Schema::create('spam_guard_errors', function (Blueprint $table) {
                $table->id();
                $table->unsignedSmallInteger('status_code');
                $table->string('url', 255);
                $table->string('ip', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->text('error_message')->nullable();
                $table->unsignedInteger('count')->default(1);
                $table->timestamps();

                $table->index(['status_code', 'url']);
                $table->index('ip');
            });
        }

        if (! Schema::hasTable('spam_guard_error_ignoreds')) {
            Schema::create('spam_guard_error_ignoreds', function (Blueprint $table) {
                $table->id();
                $table->string('pattern');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('spam_guard_refused_contacts')) {
            Schema::create('spam_guard_refused_contacts', function (Blueprint $table) {
                $table->id();
                $table->string('form_name')->default('contact');
                $table->string('mel')->nullable();
                $table->string('ip', 45)->nullable();
                $table->string('pays', 10)->nullable();
                $table->string('raison');
                $table->timestamps();

                $table->index('ip');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('spam_guard_refused_contacts');
        Schema::dropIfExists('spam_guard_error_ignoreds');
        Schema::dropIfExists('spam_guard_errors');
        Schema::dropIfExists('spam_guard_banned_ips');
    }
};
