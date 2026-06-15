<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('spam_guard_banned_emails')) {
            Schema::create('spam_guard_banned_emails', function (Blueprint $table) {
                $table->id();
                $table->string('mel')->unique();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('spam_guard_banned_emails');
    }
};
