<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Check if admin user already exists
        if (!User::where('email', 'admin@courseapp.com')->exists()) {
            User::create([
                'name' => 'Admin',
                'email' => 'admin@courseapp.com',
                'password' => Hash::make('Admin@123456'), // Change this password!
            ]);
        }
    }
}
