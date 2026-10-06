<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // Deed files need one side of the ID (or a passport) and no council clearance certificate from the resident.
    public function up(): void
    {
        DB::table('faqs')->where('answer', 'Your Agreement of Sale, both sides of your national ID, proof of residence, and later a council rates clearance certificate. You upload them from the Deed Tracker.')->update(['answer' => 'Your Agreement of Sale, your national ID or passport, and proof of residence. You upload them from the Deed Tracker.']);
    }

    public function down(): void
    {
        DB::table('faqs')->where('answer', 'Your Agreement of Sale, your national ID or passport, and proof of residence. You upload them from the Deed Tracker.')->update(['answer' => 'Your Agreement of Sale, both sides of your national ID, proof of residence, and later a council rates clearance certificate. You upload them from the Deed Tracker.']);
    }
};
