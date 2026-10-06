<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // The constitution stays hidden until the committee publishes it in Admin > Pages.
    public function up(): void
    {
        DB::table('cms_pages')->where('slug', 'constitution')->update(['published' => false]);
    }

    public function down(): void
    {
        DB::table('cms_pages')->where('slug', 'constitution')->update(['published' => true]);
    }
};
