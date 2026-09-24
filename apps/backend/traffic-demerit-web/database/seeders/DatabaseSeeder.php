<?php

namespace Database\Seeders;

use App\Models\DemeritLedgerEntry;
use App\Models\DriverProfile;
use App\Models\Payment;
use App\Models\RiskAssessment;
use App\Models\SanctionAction;
use App\Models\User;
use App\Models\Violation;
use App\Models\Role;
use App\Models\Vehicle;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $bulkDriverCount = 100;
        $offensePoints = [
            'speeding' => 5,
            'red_light' => 4,
            'dui' => 8,
            'no_seatbelt' => 2,
            'phone_use' => 3,
            'reckless_driving' => 6,
        ];
        $zoneTypes = ['urban', 'highway', 'school_zone', 'residential'];
        $weatherTypes = ['clear', 'rain', 'fog', 'night'];

        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        Payment::truncate();
        SanctionAction::truncate();
        RiskAssessment::truncate();
        DemeritLedgerEntry::truncate();
        Violation::truncate();
        Vehicle::truncate();
        DriverProfile::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $motoristRoleId = Role::query()->where('role_name', 'motorist')->value('id');
        $officerRoleId = Role::query()->where('role_name', 'officer')->value('id');
        $adminRoleId = Role::query()->where('role_name', 'admin')->value('id');

        User::updateOrCreate(
            ['email' => 'admin@ntsa.go.ke'],
            [
                'name' => 'NTSA Admin',
                'password' => Hash::make('Admin@123'),
                'role' => 'admin',
                'role_id' => $adminRoleId,
                'is_active' => true,
                'registered_on' => now(),
            ]
        );

        $officer = User::updateOrCreate(
            ['email' => 'officer@ntsa.go.ke'],
            [
                'name' => 'NTSA Field Officer',
                'password' => Hash::make('Officer@123'),
                'role' => 'officer',
                'role_id' => $officerRoleId,
                'is_active' => true,
                'registered_on' => now(),
            ]
        );

        User::updateOrCreate(
            ['email' => 'driver@ntsa.go.ke'],
            [
                'name' => 'Demo Driver',
                'password' => Hash::make('Driver@123'),
                'role' => 'motorist',
                'role_id' => $motoristRoleId,
                'is_active' => true,
                'registered_on' => now(),
            ]
        );

        $vehicleOwners = [
            User::updateOrCreate(
                ['email' => 'driver@ntsa.go.ke'],
                [
                    'name' => 'Demo Driver',
                    'password' => Hash::make('Driver@123'),
                    'role' => 'motorist',
                    'role_id' => $motoristRoleId,
                    'is_active' => true,
                    'registered_on' => now(),
                ]
            ),
            User::updateOrCreate(
                ['email' => 'mary.atieno@ntsa.go.ke'],
                [
                    'name' => 'Mary Atieno',
                    'password' => Hash::make('Driver@123'),
                    'role' => 'motorist',
                    'role_id' => $motoristRoleId,
                    'is_active' => true,
                    'registered_on' => now(),
                ]
            ),
            User::updateOrCreate(
                ['email' => 'peter.mwangi@ntsa.go.ke'],
                [
                    'name' => 'Peter Mwangi',
                    'password' => Hash::make('Driver@123'),
                    'role' => 'motorist',
                    'role_id' => $motoristRoleId,
                    'is_active' => true,
                    'registered_on' => now(),
                ]
            ),
        ];

        User::updateOrCreate(
            ['email' => 'officer@ntsa.test'],
            [
                'name' => 'NTSA Field Officer',
                'password' => Hash::make('password'),
                'role' => 'officer',
                'role_id' => $officerRoleId,
                'is_active' => true,
                'registered_on' => now(),
            ]
        );

        $drivers = [
            [
                'license_no' => 'DL-KE-1001',
                'full_name' => 'John Kamau',
                'profile_photo_path' => null,
                'license_issue_date' => '2020-04-14',
                'status' => 'active',
            ],
            [
                'license_no' => 'DL-KE-1002',
                'full_name' => 'Mary Atieno',
                'profile_photo_path' => null,
                'license_issue_date' => '2021-08-02',
                'status' => 'restricted',
            ],
            [
                'license_no' => 'DL-KE-1003',
                'full_name' => 'Peter Mwangi',
                'profile_photo_path' => null,
                'license_issue_date' => '2019-11-22',
                'status' => 'suspended',
            ],
        ];

        $driverProfiles = collect($drivers)->map(function (array $driver) {
            return DriverProfile::updateOrCreate(
                ['license_no' => $driver['license_no']],
                $driver
            );
        });

        $vehicles = [
            [
                'owner_user_id' => $vehicleOwners[0]->id,
                'plate_number' => 'KDA 245T',
                'make_model' => 'Toyota Axio',
                'vehicle_class' => 'saloon',
                'colour' => 'silver',
                'year_of_manufacture' => 2018,
                'registration_expiry' => '2027-03-31',
            ],
            [
                'owner_user_id' => $vehicleOwners[1]->id,
                'plate_number' => 'KDJ 908Q',
                'make_model' => 'Nissan Note',
                'vehicle_class' => 'hatchback',
                'colour' => 'blue',
                'year_of_manufacture' => 2020,
                'registration_expiry' => '2027-06-30',
            ],
            [
                'owner_user_id' => $vehicleOwners[2]->id,
                'plate_number' => 'KCF 117B',
                'make_model' => 'Isuzu D-Max',
                'vehicle_class' => 'pickup',
                'colour' => 'white',
                'year_of_manufacture' => 2019,
                'registration_expiry' => '2026-12-31',
            ],
            [
                'owner_user_id' => $vehicleOwners[0]->id,
                'plate_number' => 'KDG 332M',
                'make_model' => 'Mazda Demio',
                'vehicle_class' => 'hatchback',
                'colour' => 'red',
                'year_of_manufacture' => 2017,
                'registration_expiry' => '2027-01-31',
            ],
            [
                'owner_user_id' => $vehicleOwners[1]->id,
                'plate_number' => 'KCH 451S',
                'make_model' => 'Subaru Forester',
                'vehicle_class' => 'suv',
                'colour' => 'black',
                'year_of_manufacture' => 2021,
                'registration_expiry' => '2027-09-30',
            ],
            [
                'owner_user_id' => $vehicleOwners[2]->id,
                'plate_number' => 'KBX 764P',
                'make_model' => 'Toyota Prado',
                'vehicle_class' => 'suv',
                'colour' => 'pearl white',
                'year_of_manufacture' => 2016,
                'registration_expiry' => '2026-11-30',
            ],
        ];

        foreach ($vehicles as $vehicle) {
            Vehicle::updateOrCreate(
                ['plate_number' => $vehicle['plate_number']],
                $vehicle
            );
        }

        for ($i = 0; $i < $bulkDriverCount; $i++) {
            $driverProfiles->push(DriverProfile::create([
                'license_no' => sprintf('DL-KE-%04d', 1100 + $i),
                'full_name' => fake()->name(),
                'profile_photo_path' => null,
                'license_issue_date' => fake()->dateTimeBetween('-12 years', '-1 year')->format('Y-m-d'),
                'status' => 'active',
            ]));
        }

        $now = Carbon::now();

        foreach ($driverProfiles as $driver) {
            $runningBalance = 0;
            $violationsCount = random_int(1, 6);
            $latestOccurredAt = null;

            for ($v = 0; $v < $violationsCount; $v++) {
                $offenseType = fake()->randomElement(array_keys($offensePoints));
                $points = $offensePoints[$offenseType];
                $occurredAt = $now->copy()->subDays(random_int(1, 365));
                $latestOccurredAt = $latestOccurredAt === null || $occurredAt->gt($latestOccurredAt)
                    ? $occurredAt
                    : $latestOccurredAt;

                $violation = Violation::create([
                    'driver_id' => $driver->id,
                    'officer_id' => $officer->id,
                    'offense_type' => $offenseType,
                    'speed_recorded' => $offenseType === 'speeding' ? random_int(85, 150) : null,
                    'zone_type' => fake()->randomElement($zoneTypes),
                    'weather_conditions' => fake()->randomElement($weatherTypes),
                    'location' => fake()->randomElement([
                        'Mombasa Road',
                        'Thika Superhighway',
                        'Kenyatta Avenue',
                        'Waiyaki Way',
                        'Jogoo Road',
                    ]),
                    'points_assigned' => $points,
                    'occurred_at' => $occurredAt,
                ]);

                $runningBalance += $points;

                DemeritLedgerEntry::create([
                    'driver_id' => $driver->id,
                    'violation_id' => $violation->id,
                    'points_applied' => $points,
                    'running_balance' => $runningBalance,
                    'reason' => 'violation',
                ]);

                if (random_int(1, 100) <= 70) {
                    $status = fake()->randomElement(['completed', 'pending', 'failed']);

                    Payment::create([
                        'violation_id' => $violation->id,
                        'driver_id' => $driver->id,
                        'amount' => $points * 1000,
                        'method' => fake()->randomElement(['mpesa', 'card']),
                        'status' => $status,
                        'transaction_ref' => strtoupper(fake()->bothify('TRX-#####')),
                        'paid_at' => $status === 'completed' ? $occurredAt->copy()->addDays(random_int(1, 14)) : null,
                    ]);
                }
            }

            if (random_int(1, 100) <= 35) {
                $runningBalance = max(0, $runningBalance - 1);

                DemeritLedgerEntry::create([
                    'driver_id' => $driver->id,
                    'violation_id' => null,
                    'points_applied' => -1,
                    'running_balance' => $runningBalance,
                    'reason' => 'manual_adjustment',
                ]);
            }

            $riskScore = min(0.98, max(0.08, 0.12 + ($runningBalance / 30) + ($violationsCount / 20) + (random_int(0, 20) / 100)));
            $riskClass = $riskScore >= 0.70 ? 'high' : ($riskScore >= 0.40 ? 'moderate' : 'low');

            $assessment = RiskAssessment::create([
                'driver_id' => $driver->id,
                'risk_score' => round($riskScore, 4),
                'risk_class' => $riskClass,
                'model_version' => 'rf_v1.2.0',
                'feature_snapshot' => [
                    'active_points' => $runningBalance,
                    'total_violations' => $violationsCount,
                    'latest_violation_days_ago' => $latestOccurredAt ? $latestOccurredAt->diffInDays($now) : null,
                ],
                'generated_at' => $latestOccurredAt ? $latestOccurredAt->copy()->addDays(1) : $now,
            ]);

            if ($riskClass === 'high') {
                $driver->status = 'suspended';

                SanctionAction::create([
                    'driver_id' => $driver->id,
                    'risk_assessment_id' => $assessment->id,
                    'trigger_reason' => 'risk_score >= 0.70',
                    'action_type' => 'profile_suspension',
                    'webhook_status' => fake()->randomElement(['pending', 'sent', 'failed']),
                    'webhook_response' => ['status' => 'queued'],
                ]);
            } elseif ($riskClass === 'moderate' && $runningBalance >= 10) {
                $driver->status = 'restricted';

                SanctionAction::create([
                    'driver_id' => $driver->id,
                    'risk_assessment_id' => $assessment->id,
                    'trigger_reason' => 'risk_score >= 0.40 AND active_points >= 10',
                    'action_type' => 'profile_restriction',
                    'webhook_status' => fake()->randomElement(['pending', 'sent']),
                    'webhook_response' => ['status' => 'queued'],
                ]);
            } else {
                $driver->status = 'active';
            }

            $driver->save();
        }
    }
}
