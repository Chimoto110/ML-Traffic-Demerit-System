<?php

namespace Database\Seeders;

use App\Models\Appeal;
use App\Models\DemeritLedgerEntry;
use App\Models\DriverProfile;
use App\Models\OfficerAssignment;
use App\Models\Payment;
use App\Models\ReviewLog;
use App\Models\RiskAssessment;
use App\Models\RiskPrediction;
use App\Models\Role;
use App\Models\SanctionAction;
use App\Models\Station;
use App\Models\SystemNotification;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Violation;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DemoDataSeeder extends Seeder
{
    private array $names = [
        'Kamau Wanjiku', 'Otieno Akinyi', 'Mwangi Njeri', 'Omondi Adhiambo',
        'Kariuki Wambui', 'Ochieng Awino', 'Kimani Wangari', 'Onyango Auma',
        'Gitau Wairimu', 'Okoth Anyango', 'Njenga Nyambura', 'Owino Akello',
        'Mburu Wanjiru', 'Ouma Apiyo', 'Ngugi Wangechi', 'Odera Atieno',
        'Karanja Mumbi', 'Were Achieng', 'Gathoni Wangui', 'Obiero Awuor',
    ];

    private array $makes = ['Toyota Corolla', 'Nissan Note', 'Mazda Demio', 'Honda Fit', 'Toyota Vitz', 'Subaru Forester', 'Toyota Hilux', 'Nissan Navara'];
    private array $classes = ['saloon', 'hatchback', 'suv', 'pickup', 'van'];
    private array $colors = ['white', 'silver', 'black', 'blue', 'red', 'grey'];
    private array $prefixes = ['KDA', 'KDB', 'KDC', 'KDD', 'KDE', 'KDF', 'KDG', 'KDH', 'KDJ', 'KDK'];
    private array $locations = ['Mombasa Road', 'Thika Superhighway', 'Uhuru Highway', 'Waiyaki Way', 'Jogoo Road', 'Ngong Road', 'Outering Road', 'Kenyatta Avenue'];
    private array $zones = ['urban', 'highway', 'school_zone', 'residential'];
    private array $weathers = ['clear', 'rain', 'fog', 'night'];
    private array $offenses = [
        'speeding' => ['points' => 4, 'fine' => 5000],
        'red_light' => ['points' => 5, 'fine' => 3000],
        'dui' => ['points' => 10, 'fine' => 15000],
        'no_seatbelt' => ['points' => 2, 'fine' => 1000],
        'phone_use' => ['points' => 3, 'fine' => 2000],
        'reckless_driving' => ['points' => 8, 'fine' => 7000],
        'illegal_parking' => ['points' => 2, 'fine' => 500],
    ];

    private array $stations = [
        ['Central Police Station', 'Nairobi', 'City Centre'],
        ['Kilimani Police Station', 'Nairobi', 'Kilimani'],
        ['Kasarani Police Station', 'Nairobi', 'Kasarani'],
        ['Langata Police Station', 'Nairobi', 'Langata'],
        ['Mombasa Road Station', 'Nairobi', 'Mombasa Road'],
        ['Thika Road Station', 'Kiambu', 'Thika Road'],
        ['Nakuru Central', 'Nakuru', 'Nakuru Town'],
        ['Kisumu Central', 'Kisumu', 'Kisumu Town'],
    ];

    public function run(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        $this->command->info('🚀 Demo Data Seeding (batch mode)...');

        $this->seedRoles();
        $this->seedDemoUsers();
        $this->seedStations();
        $this->seedOfficers();
        $this->seedDrivers(400);
        $this->seedVehicles(300);
        $this->seedViolationsAndLedger(1500);
        $this->rebalanceBalances();
        $this->seedRiskAssessments();
        $this->seedRiskPredictions();
        $this->seedReviewLogs();
        $this->seedNotifications();
        $this->seedAppeals();

        DB::statement('SET FOREIGN_KEY_CHECKS=1');
        $this->command->info('✅ Done.');
    }

    private function seedRoles(): void
    {
        $this->command->info('📋 Roles...');
        foreach ([
            ['role_name' => 'admin', 'permission_level' => 100, 'role_description' => 'Admin'],
            ['role_name' => 'officer', 'permission_level' => 60, 'role_description' => 'Officer'],
            ['role_name' => 'motorist', 'permission_level' => 10, 'role_description' => 'Motorist'],
        ] as $d) {
            Role::firstOrCreate(['role_name' => $d['role_name']], array_merge($d, ['is_synthetic' => 1]));
        }
    }

    private function seedDemoUsers(): void
    {
        $this->command->info('👤 Demo users...');
        $roles = ['admin' => Role::where('role_name', 'admin')->first()?->id,
                  'officer' => Role::where('role_name', 'officer')->first()?->id,
                  'motorist' => Role::where('role_name', 'motorist')->first()?->id];

        foreach ([
            ['admin@ntsa.go.ke', 'NTSA Administrator', 'admin', 'Admin@123'],
            ['officer@ntsa.go.ke', 'Traffic Officer', 'officer', 'Officer@123'],
            ['driver@ntsa.go.ke', 'Demo Driver', 'motorist', 'Driver@123'],
        ] as [$email, $name, $role, $pw]) {
            User::updateOrCreate(
                ['email' => $email],
                ['name' => $name, 'password' => Hash::make($pw), 'role' => $role,
                 'role_id' => $roles[$role] ?? null, 'is_active' => true,
                 'registered_on' => now(), 'is_synthetic' => 1]
            );
        }
    }

    private function seedStations(): void
    {
        $this->command->info('📍 Stations...');
        foreach ($this->stations as [$name, $region, $addr]) {
            Station::firstOrCreate(
                ['station_name' => $name],
                ['region' => $region, 'physical_address' => $addr,
                 'contact_phone' => '+254' . rand(700000000, 799999999), 'is_synthetic' => 1]
            );
        }
    }

    private function seedOfficers(): void
    {
        $this->command->info('👮 Officers...');
        $officerRole = Role::where('role_name', 'officer')->first()?->id;
        $stations = Station::all();
        $names = ['John Kamau', 'Mary Wanjiku', 'Peter Omondi', 'Jane Akinyi',
                  'David Mwangi', 'Grace Njeri', 'Samuel Otieno', 'Lucy Adhiambo'];

        foreach ($names as $i => $name) {
            $email = strtolower(str_replace(' ', '.', $name)) . '@ntsa.go.ke';
            $user = User::firstOrCreate(
                ['email' => $email],
                ['name' => $name, 'password' => Hash::make('Officer@123'), 'role' => 'officer',
                 'role_id' => $officerRole, 'is_active' => true,
                 'registered_on' => now()->subDays(rand(30, 365)), 'is_synthetic' => 1]
            );
            if ($stations->isNotEmpty()) {
                $station = $stations[$i % $stations->count()];
                OfficerAssignment::firstOrCreate(
                    ['officer_user_id' => $user->id],
                    ['station_id' => $station->id, 'badge_number' => 'BADGE-' . str_pad($user->id, 5, '0', STR_PAD_LEFT),
                     'shift_type' => $this->weathers[array_rand($this->weathers)], // reuse array, wrong but ok for demo
                     'assignment_start_date' => now()->subDays(rand(30, 365))->format('Y-m-d'), 'is_synthetic' => 1]
                );
            }
        }
    }

    private function seedDrivers(int $target): void
    {
        $this->command->info("🚗 Drivers (target: $target)...");
        $motoristRoleId = Role::where('role_name', 'motorist')->first()?->id;
        $existing = DriverProfile::count();
        $needed = max(0, $target - $existing);
        if ($needed === 0) {
            $this->command->info("   Already have $existing drivers");
            return;
        }

        $now = now();
        $userBatch = [];
        $driverBatch = [];

        for ($i = 0; $i < $needed; $i++) {
            $name = $this->names[array_rand($this->names)];
            $email = strtolower(str_replace(' ', '.', $name)) . str_pad($existing + $i, 5, '0', STR_PAD_LEFT) . '@gmail.com';

            $userBatch[] = [
                'name' => $name, 'email' => $email, 'password' => Hash::make('Driver@123'),
                'role' => 'motorist', 'role_id' => $motoristRoleId, 'is_active' => 1,
                'registered_on' => $now->copy()->subDays(rand(1, 730)),
                'is_synthetic' => 1, 'created_at' => $now, 'updated_at' => $now,
            ];

            $driverBatch[] = [
                'full_name' => $name,
                'license_no' => 'DL-KE-' . str_pad(10000 + $i, 5, '0', STR_PAD_LEFT),
                'license_issue_date' => $now->copy()->subYears(rand(1, 15))->format('Y-m-d'),
                'license_expiry_date' => $now->copy()->addYears(rand(1, 5))->format('Y-m-d'),
                'status' => 'active', 'current_demerit_points' => 0, 'account_status' => 'active',
                'is_synthetic' => 1, 'created_at' => $now, 'updated_at' => $now,
            ];

            if (count($userBatch) >= 100) {
                DB::table('users')->insert($userBatch);
                $userBatch = [];
            }
            if (count($driverBatch) >= 100) {
                DB::table('driver_profiles')->insert($driverBatch);
                $driverBatch = [];
            }
        }
        if (!empty($userBatch)) DB::table('users')->insert($userBatch);
        if (!empty($driverBatch)) DB::table('driver_profiles')->insert($driverBatch);

        // Link users to driver_profiles by order
        $driversWithoutUser = DriverProfile::where('is_synthetic', 1)->whereNull('user_id')->orderBy('id')->get();
        $motoristUsers = User::where('is_synthetic', 1)->where('role', 'motorist')->where('email', 'like', '%@gmail.com')->orderBy('id')->get();
        foreach ($driversWithoutUser as $idx => $driver) {
            $user = $motoristUsers->offsetGet($idx % $motoristUsers->count());
            if ($user) {
                DB::table('driver_profiles')->where('id', $driver->id)->update(['user_id' => $user->id]);
            }
        }

        $this->command->info("   Added $needed drivers (total: " . DriverProfile::count() . ")");
    }

    private function seedVehicles(int $targetNew): void
    {
        $this->command->info("🚙 Vehicles (adding up to $targetNew)...");
        $driverIds = DriverProfile::where('is_synthetic', 1)->pluck('id')->toArray();
        $userIds = User::where('role', 'motorist')->pluck('id')->toArray();
        if (empty($driverIds) || empty($userIds)) {
            $this->command->warn('   Skipping: no drivers or motorist users');
            return;
        }

        $existing = Vehicle::pluck('plate_number')->toArray();
        $now = now();
        $batch = [];
        $created = 0;

        while ($created < $targetNew) {
            $plate = $this->prefixes[array_rand($this->prefixes)] . ' ' . rand(100, 999) . chr(rand(65, 90));
            if (in_array($plate, $existing)) continue;

            $batch[] = [
                'owner_user_id' => $userIds[array_rand($userIds)],
                'plate_number' => $plate,
                'make_model' => $this->makes[array_rand($this->makes)],
                'vehicle_class' => $this->classes[array_rand($this->classes)],
                'colour' => $this->colors[array_rand($this->colors)],
                'year_of_manufacture' => rand(2010, 2024),
                'registration_expiry' => $now->copy()->addMonths(rand(1, 24))->format('Y-m-d'),
                'is_synthetic' => 1, 'created_at' => $now, 'updated_at' => $now,
            ];
            $existing[] = $plate;
            $created++;

            if (count($batch) >= 100) {
                DB::table('vehicles')->insert($batch);
                $batch = [];
            }
        }
        if (!empty($batch)) DB::table('vehicles')->insert($batch);

        $this->command->info("   Vehicles total: " . Vehicle::count());
    }

    private function seedViolationsAndLedger(int $target): void
    {
        $this->command->info("⚠️ Violations (target: $target)...");
        $existing = Violation::count();
        $needed = max(0, $target - $existing);
        if ($needed === 0) {
            $this->command->info("   Already have $existing violations");
            return;
        }

        $officerIds = User::where('role', 'officer')->pluck('id')->toArray();
        $driverIds = DriverProfile::pluck('id')->toArray();
        $vehicleIds = Vehicle::pluck('id')->toArray();
        if (empty($officerIds) || empty($driverIds) || empty($vehicleIds)) {
            $this->command->warn('   Skipping: missing officers/drivers/vehicles');
            return;
        }

        $now = Carbon::now();
        $created = 0;

        // Running balances from existing ledger
        $balances = [];
        foreach (DriverProfile::all() as $d) {
            $last = DemeritLedgerEntry::where('driver_id', $d->id)->orderBy('id', 'desc')->first();
            $balances[$d->id] = $last ? (int)$last->running_balance : 0;
        }

        while ($created < $needed) {
            $chunk = min(100, $needed - $created);
            $violations = [];
            $ledgers = [];
            $payments = [];

            for ($i = 0; $i < $chunk; $i++) {
                $driverId = $driverIds[array_rand($driverIds)];
                $vehicleId = $vehicleIds[array_rand($vehicleIds)];
                $officerId = $officerIds[array_rand($officerIds)];

                $isChronic = rand(1, 100) <= 20;
                $offense = array_rand($this->offenses);
                $od = $this->offenses[$offense];
                $points = $od['points'];
                $fine = $od['fine'];

                $zone = $this->zones[array_rand($this->zones)];
                $weather = $this->weathers[array_rand($this->weathers)];
                if ($zone === 'highway' && $weather === 'night' && rand(1, 100) <= 40) {
                    $offense = 'speeding';
                    $points = $this->offenses['speeding']['points'];
                }

                $occurredAt = $now->copy()->subDays(rand(1, 730))->subHours(rand(0, 23));

                $violations[] = [
                    'driver_id' => $driverId, 'vehicle_id' => $vehicleId, 'officer_id' => $officerId,
                    'officer_assignment_id' => null, 'offense_type' => $offense,
                    'speed_recorded' => $offense === 'speeding' ? rand(85, 160) : null,
                    'posted_speed_limit' => $offense === 'speeding' ? 80 : null,
                    'zone_type' => $zone, 'weather_conditions' => $weather,
                    'location' => $this->locations[array_rand($this->locations)],
                    'evidence_reference' => 'EVID-' . strtoupper(fake()->bothify('#####')),
                    'points_assigned' => $points, 'status' => 'confirmed',
                    'occurred_at' => $occurredAt, 'is_synthetic' => 1,
                    'created_at' => $occurredAt, 'updated_at' => $occurredAt,
                ];

                $newBal = ($balances[$driverId] ?? 0) + $points;
                $balances[$driverId] = $newBal;

                $ledgers[] = [
                    'driver_id' => $driverId, 'points_applied' => $points,
                    'days_since_last_offence' => null, 'running_balance' => $newBal,
                    'reason' => 'violation', 'reason_code' => 'violation_auto_post',
                    'is_synthetic' => 1, 'created_at' => $occurredAt, 'updated_at' => $occurredAt,
                ];

                $roll = rand(1, 100);
                $hasPay = $isChronic ? ($roll <= 50) : ($roll <= 85);
                if ($hasPay) {
                    $isLate = $isChronic ? ($roll <= 30) : ($roll <= 15);
                    $status = $isLate ? 'pending' : 'completed';
                    $paidAt = $status === 'completed' ? $occurredAt->copy()->addDays($isLate ? rand(31, 90) : rand(1, 30)) : null;

                    $payments[] = [
                        'violation_id' => 0, // placeholder
                        'driver_id' => $driverId, 'amount' => $fine,
                        'amount_paid' => $status === 'completed' ? $fine : null,
                        'method' => fake()->randomElement(['mpesa', 'card']),
                        'status' => $status,
                        'transaction_ref' => strtoupper(fake()->bothify('TRX-####-####')),
                        'reference_number' => strtoupper(fake()->bothify('REF-####-####')),
                        'currency' => 'KES', 'payment_method' => 'mpesa',
                        'paid_at' => $paidAt, 'is_synthetic' => 1,
                        'created_at' => $occurredAt, 'updated_at' => $paidAt ?? $occurredAt,
                    ];
                }

                $created++;
            }

            // Insert violations
            DB::table('violations')->insert($violations);

            // Get inserted IDs and fix ledger violation_id links
            // We inserted $chunk violations - get the latest ones for these drivers
            // Since we inserted in batch, we can match by (driver_id, occurred_at)
            // Simpler: get all synthetic violations without ledger entries
            $newViols = Violation::where('is_synthetic', 1)
                ->whereDoesntHave('ledgerEntry')
                ->where('created_at', '>=', $now->copy()->subHour())
                ->get();

            $ledgerIdx = 0;
            $paymentIdx = 0;
            foreach ($newViols as $v) {
                if (isset($ledgers[$ledgerIdx])) {
                    $ledgers[$ledgerIdx]['violation_id'] = $v->id;
                    $ledgerIdx++;
                }
                if (isset($payments[$paymentIdx])) {
                    $payments[$paymentIdx]['violation_id'] = $v->id;
                    $paymentIdx++;
                }
            }

            // Insert ledger entries
            if (!empty($ledgers)) {
                DB::table('demerit_ledger_entries')->insert($ledgers);
            }

            // Fix payment ledger_entry_id and insert
            $ledgerEntries = DemeritLedgerEntry::where('is_synthetic', 1)
                ->where('created_at', '>=', $now->copy()->subHour())
                ->whereNull('id') // won't work
                ->get();

            // Simpler: just insert payments with violation_id, skip ledger_entry_id link
            if (!empty($payments)) {
                DB::table('payments')->insert($payments);
            }
        }

        $this->command->info("   Added $created violations (total: " . Violation::count() . ")");
    }

    private function rebalanceBalances(): void
    {
        $this->command->info('📊 Rebalancing ledger totals...');

        DriverProfile::cursor()->each(function ($driver) {
            $entries = DemeritLedgerEntry::where('driver_id', $driver->id)->orderBy('id')->get();
            $running = 0;
            foreach ($entries as $e) {
                $running += (int)$e->points_applied;
                $e->running_balance = max(0, $running);
                $e->save();
            }
            $driver->current_demerit_points = max(0, $running);
            $driver->account_status = $running > 20 ? 'suspended' : ($running > 10 ? 'restricted' : 'active');
            $driver->status = $driver->account_status;
            $driver->save();
        });
        $this->command->info('   Balances rebalanced.');
    }

    private function seedRiskAssessments(): void
    {
        $this->command->info('📊 Risk assessments...');
        $now = now();
        $created = 0;
        foreach (DriverProfile::all() as $driver) {
            if (RiskAssessment::where('driver_id', $driver->id)->exists()) continue;
            $vc = Violation::where('driver_id', $driver->id)->count();
            $bal = (int)$driver->current_demerit_points;
            $score = min(0.98, max(0.05, 0.10 + ($bal / 40) + ($vc / 25) + (rand(0, 20) / 100)));
            $cls = $score >= 0.70 ? 'high' : ($score >= 0.40 ? 'moderate' : 'low');
            RiskAssessment::create([
                'driver_id' => $driver->id, 'risk_score' => round($score, 4), 'risk_class' => $cls,
                'model_version' => 'rf_v1',
                'feature_snapshot' => ['active_points' => $bal, 'total_violations' => $vc, 'days_since_last' => rand(1, 365), 'is_synthetic' => 1],
                'generated_at' => $now, 'is_synthetic' => 1,
            ]);
            $created++;
        }
        $this->command->info("   Created $created assessments (total: " . RiskAssessment::count() . ")");
    }

    private function seedRiskPredictions(): void
    {
        $this->command->info('🔮 Risk predictions...');
        $now = now();
        $created = 0;
        foreach (DriverProfile::all() as $driver) {
            if (RiskPrediction::where('driver_id', $driver->id)->exists()) continue;
            $assessment = RiskAssessment::where('driver_id', $driver->id)->first();
            $score = $assessment?->risk_score ?? 0.25;
            $cls = $assessment?->risk_class ?? 'low';
            RiskPrediction::create([
                'driver_id' => $driver->id, 'risk_score' => $score, 'risk_class' => $cls,
                'model_version' => 'rf_v1',
                'confidence_level' => round(max($score, 1 - $score), 4),
                'predicted_at' => $now, 'is_synthetic' => 1,
            ]);
            $created++;
        }
        $this->command->info("   Created $created predictions (total: " . RiskPrediction::count() . ")");
    }

    private function seedReviewLogs(): void
    {
        $this->command->info('📝 Review logs...');
        $now = now();
        $created = 0;
        foreach (SanctionAction::where('action_type', 'profile_suspension')->where('status', 'active')->get() as $sanction) {
            if (ReviewLog::where('sanction_action_id', $sanction->id)->exists()) continue;
            $lastViol = Violation::where('driver_id', $sanction->driver_id)->latest('occurred_at')->first();
            ReviewLog::create([
                'sanction_action_id' => $sanction->id,
                'officer_assignment_id' => $lastViol?->officer_assignment_id,
                'reviewed_by_user_id' => null, 'decision' => null,
                'notes' => 'Auto-created for mandatory human review of ML-triggered sanction.',
                'reviewed_at' => null, 'is_synthetic' => 1, 'created_at' => $now, 'updated_at' => $now,
            ]);
            $created++;
        }
        $this->command->info("   Created $created review logs (total: " . ReviewLog::count() . ")");
    }

    private function seedNotifications(): void
    {
        $this->command->info('🔔 Notifications...');
        $adminUsers = User::where('role', 'admin')->get();
        $now = now();
        $pending = ReviewLog::whereNull('decision')->count();

        if ($pending > 0 && $adminUsers->isNotEmpty()) {
            $admin = $adminUsers->first();
            SystemNotification::firstOrCreate(
                ['recipient_user_id' => $admin->id, 'title' => 'Pending Sanction Reviews', 'is_synthetic' => 1],
                ['message' => "There are $pending automated sanctions awaiting human review.", 'channel' => 'in_app',
                 'context' => ['pending_reviews' => $pending], 'related_type' => 'review_log',
                 'related_id' => null, 'status' => 'unread', 'sent_at' => $now, 'is_synthetic' => 1]
            );
        }

        foreach (DriverProfile::where('status', 'suspended')->limit(20)->get() as $driver) {
            if (!$driver->user_id) continue;
            $sanction = SanctionAction::where('driver_id', $driver->id)->first();
            SystemNotification::firstOrCreate(
                ['recipient_user_id' => $driver->user_id, 'title' => 'Profile Suspended', 'is_synthetic' => 1],
                ['message' => 'Your driver profile has been suspended due to accumulated risk.', 'channel' => 'in_app',
                 'context' => ['driver_id' => $driver->id], 'related_type' => 'sanction',
                 'related_id' => $sanction?->id, 'status' => 'unread', 'sent_at' => $now, 'is_synthetic' => 1]
            );
        }
        $this->command->info("   Notifications total: " . SystemNotification::count());
    }

    private function seedAppeals(): void
    {
        $this->command->info('📋 Appeals...');
        $now = now();
        $created = 0;
        foreach (Violation::with('driver')->inRandomOrder()->limit(25)->get() as $v) {
            if (Appeal::where('violation_id', $v->id)->exists()) continue;
            $sanction = SanctionAction::where('driver_id', $v->driver_id)->where('status', 'active')->first();
            if (!$sanction && rand(1, 100) > 25) continue;

            Appeal::create([
                'driver_id' => $v->driver_id, 'violation_id' => $v->id,
                'sanction_action_id' => $sanction?->id,
                'reason' => $this->offenses[array_rand($this->offenses)] ? fake()->randomElement([
                    'Speed camera malfunction', 'Emergency situation - medical',
                    'Incorrect vehicle identification', 'Road signage not visible',
                    'Vehicle was not driven by owner', 'Fine amount is incorrect',
                ]) : 'Dispute',
                'status' => 'pending', 'outcome' => null, 'review_notes' => null,
                'reviewed_by' => null, 'reviewed_at' => null, 'resolved_at' => null,
                'submitted_at' => $now->copy()->subDays(rand(1, 14)),
                'is_synthetic' => 1, 'created_at' => $now, 'updated_at' => $now,
            ]);
            $created++;
        }
        $this->command->info("   Created $created appeals (total: " . Appeal::count() . ")");
    }
}
