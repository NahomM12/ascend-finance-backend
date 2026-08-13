<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SuperAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
     public function run(): void
    {
        User::create([
            'name' => 'major super admin',
            'email' => 'superadmin@Ascend.com',
            'password' => Hash::make('abcd@123'),
            'role' => 'superadmin',
        ]);
    }
}
