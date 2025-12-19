<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    public function run()
    {
        Role::create(['name' => 'super_admin']);
        Role::create(['name' => 'kepala']);
        Role::create(['name' => 'ketua_tim']);
        Role::create(['name' => 'kasubbag']);
        Role::create(['name' => 'staff']);
    }
}
