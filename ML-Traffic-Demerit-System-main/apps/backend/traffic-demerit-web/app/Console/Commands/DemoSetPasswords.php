<?php

namespace App\Console\Commands;

use App\Models\DriverProfile;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class DemoSetPasswords extends Command
{
    protected $signature = 'demo:set-passwords
        {--admin-email=admin@ntsa.go.ke : Admin demo email}
        {--admin-password=Admin@123 : Admin demo password}
        {--officer-email=officer@ntsa.go.ke : Officer demo email}
        {--officer-password=Officer@123 : Officer demo password}
        {--driver-email=driver@ntsa.go.ke : Driver demo email}
        {--driver-password=Driver@123 : Driver demo password}';

    protected $description = 'Set known demo passwords for one admin, one officer and one driver (creates the accounts if missing).';

    public function handle(): int
    {
        $logins = [
            ['role' => 'admin', 'email' => $this->option('admin-email'), 'password' => $this->option('admin-password')],
            ['role' => 'officer', 'email' => $this->option('officer-email'), 'password' => $this->option('officer-password')],
            ['role' => 'motorist', 'email' => $this->option('driver-email'), 'password' => $this->option('driver-password')],
        ];

        foreach ($logins as $login) {
            $user = User::where('email', $login['email'])->first();

            if (! $user) {
                $roleId = \App\Models\Role::where('role_name', $login['role'])->value('id');

                $user = User::create([
                    'name' => ucfirst($login['role']) . ' Demo',
                    'email' => $login['email'],
                    'password' => Hash::make($login['password']),
                    'role' => $login['role'],
                    'role_id' => $roleId,
                    'is_active' => true,
                    'registered_on' => now(),
                ]);

                $this->info("Created {$login['role']} account {$login['email']}.");
            }

            $user->forceFill([
                'password' => Hash::make($login['password']),
                'is_active' => true,
            ])->save();

            if ($login['role'] === 'motorist' && ! DriverProfile::where('user_id', $user->id)->exists()) {
                DriverProfile::create([
                    'user_id' => $user->id,
                    'license_no' => 'PENDING-' . str_pad((string) $user->id, 6, '0', STR_PAD_LEFT),
                    'full_name' => $user->name,
                    'license_issue_date' => now()->toDateString(),
                    'status' => 'active',
                    'current_demerit_points' => 0,
                    'account_status' => 'active',
                ]);
            }

            $portal = $login['role'] === 'motorist' ? 'driver' : $login['role'];
            $this->info(sprintf(
                '  %-7s %-26s %-14s -> /login/%s',
                $login['role'],
                $login['email'],
                $login['password'],
                $portal
            ));
        }

        $this->newLine();
        $this->info('Demo passwords set. personal_access_tokens rows are created on each login.');

        return self::SUCCESS;
    }
}
