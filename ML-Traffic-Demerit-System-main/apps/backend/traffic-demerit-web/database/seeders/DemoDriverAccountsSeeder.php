<?php

namespace Database\Seeders;

use App\Models\DriverProfile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * DemoDriverAccountsSeeder
 * ------------------------
 * Creates demo driver accounts with known credentials for testing.
 * Idempotent: uses updateOrCreate so running twice won't duplicate.
 *
 * Credentials documented in README.md under "Demo Credentials".
 * NEVER use these credentials in production.
 */
class DemoDriverAccountsSeeder extends Seeder
{
    public function run(): void
    {
        $motoristRole = \App\Models\Role::where('role_name', 'motorist')->first();

        $demoDrivers = [
            [
                'email' => 'ngugi.wangechi@example.com',
                'password' => 'Ngugi@2026',
                'license_no' => 'DL-KE-2022',
                'full_name' => 'Ngugi Wangechi',
                'status' => 'suspended',
                'current_demerit_points' => 71,
            ],
            [
                'email' => 'thuo.njoki@example.com',
                'password' => 'Thuo@2026',
                'license_no' => 'DL-KE-2000',
                'full_name' => 'Thuo Njoki',
                'status' => 'suspended',
                'current_demerit_points' => 70,
            ],
            [
                'email' => 'gathoni.wangui@example.com',
                'password' => 'Gathoni@2026',
                'license_no' => 'DL-KE-2046',
                'full_name' => 'Gathoni Wangui',
                'status' => 'suspended',
                'current_demerit_points' => 65,
            ],
            [
                'email' => 'njenga.nyambura@example.com',
                'password' => 'Njenga@2026',
                'license_no' => 'DL-KE-2017',
                'full_name' => 'Njenga Nyambura',
                'status' => 'suspended',
                'current_demerit_points' => 64,
            ],
            [
                'email' => 'mwangi.njeri@example.com',
                'password' => 'Mwangi@2026',
                'license_no' => 'DL-KE-2031',
                'full_name' => 'Mwangi Njeri',
                'status' => 'active',
                'current_demerit_points' => 28,
            ],
            [
                'email' => 'macharia.nduta@example.com',
                'password' => 'Macharia@2026',
                'license_no' => 'DL-KE-2024',
                'full_name' => 'Macharia Nduta',
                'status' => 'active',
                'current_demerit_points' => 8,
            ],
            [
                'email' => 'grace.wanjiru@example.com',
                'password' => 'Driver@123',
                'license_no' => 'DL-2019-04581',
                'full_name' => 'Grace Wanjiru',
                'status' => 'active',
                'current_demerit_points' => 6,
            ],
        ];

        foreach ($demoDrivers as $data) {
            $user = User::updateOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['full_name'],
                    'password' => Hash::make($data['password']),
                    'role' => 'motorist',
                    'role_id' => $motoristRole?->id,
                    'is_active' => true,
                    'registered_on' => now(),
                    'is_synthetic' => 1,
                ]
            );

            DriverProfile::updateOrCreate(
                ['license_no' => $data['license_no']],
                [
                    'user_id' => $user->id,
                    'full_name' => $data['full_name'],
                    'license_issue_date' => now()->subYears(3)->format('Y-m-d'),
                    'status' => $data['status'],
                    'current_demerit_points' => $data['current_demerit_points'],
                    'account_status' => $data['status'],
                    'is_synthetic' => 1,
                ]
            );

            $this->command->info("  Demo driver: {$data['full_name']} ({$data['email']}) - {$data['current_demerit_points']} pts, {$data['status']}");
        }

        $this->command->info("\nDemo credentials created. See README.md for the full table.");
    }
}
