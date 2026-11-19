<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TestAccountsSeeder extends Seeder
{
    public function run(): void
    {
        $adminRole = Role::where('role_name', 'admin')->first();
        $officerRole = Role::where('role_name', 'officer')->first();
        $motoristRole = Role::where('role_name', 'motorist')->first();

        // Create Admin account
        User::updateOrCreate(
            ['email' => 'admin@ntsa.go.ke'],
            [
                'name' => 'NTSA Administrator',
                'password' => Hash::make('Admin@123'),
                'role' => 'admin',
                'role_id' => $adminRole->id,
                'is_active' => true,
                'registered_on' => now(),
            ]
        );

        // Create Officer account
        User::updateOrCreate(
            ['email' => 'officer@ntsa.go.ke'],
            [
                'name' => 'Test Traffic Officer',
                'password' => Hash::make('Officer@123'),
                'role' => 'officer',
                'role_id' => $officerRole->id,
                'is_active' => true,
                'registered_on' => now(),
            ]
        );

        // Create Driver account
        User::updateOrCreate(
            ['email' => 'driver@ntsa.go.ke'],
            [
                'name' => 'Test Driver Account',
                'password' => Hash::make('Driver@123'),
                'role' => 'motorist',
                'role_id' => $motoristRole->id,
                'is_active' => true,
                'registered_on' => now(),
            ]
        );

        $this->command->info('✅ Test accounts created successfully!');
        $this->command->info('   Admin: admin@ntsa.go.ke / Admin@123');
        $this->command->info('   Officer: officer@ntsa.go.ke / Officer@123');
        $this->command->info('   Driver: driver@ntsa.go.ke / Driver@123');
    }
}
