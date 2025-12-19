<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Faker\Factory as Faker;

class UserSeeder extends Seeder
{
    public function run()
    {
        // Buat Faker Indonesia
        $faker = Faker::create('id_ID');

        // ✅ Super Admin
        $superAdminUser = User::create([
            'username' => 'superadmin',
            'name' => 'Super Admin',
            'email' => 'superadmin@example.com',
            'password' => bcrypt('password'),
            'whatsapp_number' => '081111111111',
            'address' => 'Bandar Lampung',
        ]);
        $superAdminUser->assignRole('super_admin');

        // ✅ Kepala
        $kepalaUser = User::create([
            'username' => 'kepala',
            'name' => 'Kepala User',
            'email' => 'kepala@example.com',
            'password' => bcrypt('password'),
            'whatsapp_number' => '0812321111',
            'address' => 'Bandar Lampung',
            'position' => 'pns',
        ]);
        $kepalaUser->assignRole('kepala');

        // ✅ Ketua Tim
        $ketuaTimUser = User::create([
            'username' => 'ketuatim',
            'name' => 'Ketua Tim User',
            'email' => 'ketuatim@example.com',
            'password' => bcrypt('password'),
            'whatsapp_number' => '08111111',
            'address' => 'Bandar Lampung',
            'position' => 'pns',
        ]);
        $ketuaTimUser->assignRole('ketua_tim');

        // ✅ Kasubbag
        $kasubbagUser = User::create([
            'username' => 'kasubbag',
            'name' => 'Kasubbag User',
            'email' => 'kasubbag@example.com',
            'password' => bcrypt('password'),
            'whatsapp_number' => '082377425934',
            'address' => 'Bandar Lampung',
            'position' => 'pns',
        ]);
        $kasubbagUser->assignRole('kasubbag');

        // ✅ Staff
        $staffUser = User::create([
            'username' => 'staff',
            'name' => 'Staff User',
            'email' => 'staff@example.com',
            'password' => bcrypt('password'),
            'whatsapp_number' => '08111111',
            'address' => 'Bandar Lampung',
            'position' => 'pppk',
        ]);
        $staffUser->assignRole('staff');

        // ✅ Tambah 20 user random
        // for ($i = 1; $i <= 2; $i++) {
        //     $user = User::create([
        //         'username' => 'user' . $i,
        //         'name' => $faker->name,
        //         'email' => 'user' . $i . '@example.com',
        //         'password' => bcrypt('password'),
        //         'whatsapp_number' => '08' . $faker->numerify('##########'),
        //         'address' => $faker->city,
        //     ]);

        //     // Bisa kasih role default misal "staff"
        //     $user->assignRole('staff');
        // }
    }
}
