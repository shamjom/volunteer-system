<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\VolunteerProfile;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Admin

        User::create([
            'name' => 'Admin',
            'email' => 'admin@test.com',
            'password' => Hash::make('12345678'),
            'role' => 'admin',
            'status' => 'active'
        ]);

        // HR

        User::create([
            'name' => 'HR User',
            'email' => 'hr@test.com',
            'password' => Hash::make('12345678'),
            'role' => 'hr',
            'status' => 'active'
        ]);

        // Team Leader

        User::create([
            'name' => 'Leader User',
            'email' => 'leader@test.com',
            'password' => Hash::make('12345678'),
            'role' => 'leader',
            'status' => 'active'
        ]);

        // Volunteer

        $volunteer = User::create([
            'name' => 'Volunteer User',
            'email' => 'volunteer@test.com',
            'password' => Hash::make('12345678'),
            'role' => 'volunteer',
            'status' => 'active'
        ]);

        VolunteerProfile::create([
            'user_id' => $volunteer->id,
            'phone' => '0999999999',
            'address' => 'Damascus',
            'total_hours' => 0,
            'status' => 'active'
        ]);
    }
}