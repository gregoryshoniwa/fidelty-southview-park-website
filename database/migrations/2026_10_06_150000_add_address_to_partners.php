<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partners', function (Blueprint $t) {
            $t->string('address')->nullable()->after('contact_phone');
            $t->string('contact_phone', 60)->nullable()->change(); // room for "landline / mobile"
        });
    }

    public function down(): void
    {
        Schema::table('partners', fn (Blueprint $t) => $t->dropColumn('address'));
    }
};
