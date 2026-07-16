<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SupportSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::create([
            'name' => 'It support',
            'email' => 'superadmin@itsupport.com',
            'password' => Hash::make('Itsupport@123'),
            'role' => 'superadmin',
        ]);
    }
}
