<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A resident proves a number by sending "VERIFY <code>" from it on WhatsApp.
        Schema::create('phone_challenges', function (Blueprint $t) {
            $t->ulid('id')->primary();
            $t->string('code', 6);
            $t->string('purpose', 10); // link | login
            $t->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $t->string('status', 10)->default('pending'); // pending | verified | used
            $t->string('phone', 20)->nullable();
            $t->timestamp('expires_at');
            $t->timestamp('verified_at')->nullable();
            $t->timestamps();
            $t->index(['code', 'status']);
        });

        // A number the resident typed but has not proved; the committee confirms it, or WhatsApp does.
        Schema::table('users', function (Blueprint $t) {
            $t->string('unconfirmed_phone', 20)->nullable()->after('phone_verified_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('phone_challenges');
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('unconfirmed_phone'));
    }
};
