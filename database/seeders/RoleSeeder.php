<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['resident', 'verified_resident', 'partner_user', 'committee', 'finance_admin', 'super_admin'] as $r) {
            Role::findOrCreate($r, 'web');
        }
    }
}
