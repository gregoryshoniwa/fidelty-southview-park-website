<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_login_tokens', function (Blueprint $t) {
            $t->id();
            $t->string('email')->index();
            $t->string('token_hash', 64)->unique();
            $t->string('purpose', 20)->default('login');
            $t->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $t->timestamp('expires_at');
            $t->timestamp('used_at')->nullable();
            $t->string('ip', 45)->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_login_tokens');
    }
};
