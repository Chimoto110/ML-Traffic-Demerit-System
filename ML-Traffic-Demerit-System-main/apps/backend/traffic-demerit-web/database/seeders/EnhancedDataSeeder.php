<?php

namespace Database\Seeders;

use App\Models\Appeal;
use App\Models\DemeritLedgerEntry;
use App\Models\DriverProfile;
use App\Models\LoginAudit;
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
use Illuminate\Support\Facades\Hash;

class EnhancedDataSeeder extends Seeder
{
    private array $kenyanNames = [
        'Kamau Wanjiku', 'Otieno Akinyi', 'Mwangi Njeri', 'Omondi Adhiambo',
        'Kariuki Wambui', 'Ochieng Awino', 'Kimani Wangari', 'Onyango Auma',
        'Gitau Wairimu', 'Okoth Anyango', 'Njenga Nyambura', 'Owino Akello',
        'Mburu Wanjiru', 'Ouma Apiyo', 'Ngugi Wangechi', 'Odera Atieno',
        'Karanja Mumbi', 'Were Achieng', 'Gathoni Wangui', 'Obiero Awuor',
        'Thuo Njoki', 'Odongo Akeyo', 'Kinyanjui Wacera', 'Oluoch Akinyi',
        'Muturi Wamuyu', 'Okello Adoyo', 'Macharia Nduta', 'Ogola Aoko',
        'Njoroge Wamboi', 'Omondi Akoth', 'Kamande Wangeci', 'Otieno Awuor'
    ];

    private array $vehicleMakes = [
        'Toyota Corolla', 'Nissan Note', 'Mazda Demio', 'Subaru Impreza',
        'Honda Fit', 'Mitsubishi Lancer', 'Toyota Vitz', 'Nissan Tiida',
        'Volkswagen Passat', 'Toyota Premio', 'Honda Civic', 'Toyota Axio',
        'Nissan X-Trail', 'Toyota RAV4', 'Subaru Forester', 'Honda CR-V',
        'Isuzu D-Max', 'Toyota Hilux', 'Nissan Navara', 'Ford Ranger',
        'Toyota Prado', 'Nissan Patrol', 'Toyota Land Cruiser', 'Mercedes GLE'
    ];

    private array $locations = [
        'Mombasa Road', 'Thika Superhighway', 'Uhuru Highway', 'Waiyaki Way',
        'Jogoo Road', 'Ngong Road', 'Langata Road', 'Kipande Road',
        'Outering Road', 'Eastern Bypass', 'Southern Bypass', 'Nairobi-Nakuru Highway',
        'Moi Avenue', 'Kenyatta Avenue', 'Tom Mboya Street', 'Ronald Ngala Street',
        'University Way', 'Haile Selassie Avenue', 'Valley Road', 'Argwings Kodhek Road'
    ];

    private array $stations = [
        ['station_name' => 'Central Police Station', 'region' => 'Nairobi Central'],
        ['station_name' => 'Kilimani Police Station', 'region' => 'Nairobi West'],
        ['station_name' => 'Kasarani Police Station', 'region' => 'Nairobi North'],
        ['station_name' => 'Langata Police Station', 'region' => 'Nairobi South'],
        ['station_name' => 'Mombasa Road Station', 'region' => 'Nairobi Industrial'],
        ['station_name' => 'Thika Road Station', 'region' => 'Kiambu County'],
        ['station_name' => 'Nakuru Central', 'region' => 'Nakuru County'],
        ['station_name' => 'Kisumu Central', 'region' => 'Kisumu County'],
    ];

    public function run(): void
    {
        $this->command->info('🚀 Starting Enhanced Data Seeding...');

        // Create stations
        $this->command->info('📍 Creating police stations...');
        $stations = $this->createStations();

        // Create more officers
        $this->command->info('👮 Creating officers...');
        $officers = $this->createOfficers($stations);

        // Create more drivers
        $this->command->info('🚗 Creating drivers...');
        $drivers = $this->createDrivers(50);

        // Create more vehicles
        $this->command->info('🚙 Creating vehicles...');
        $this->createVehicles($drivers, 80);

        // Create violations with realistic scenarios
        $this->command->info('⚠️ Creating violations...');
        $this->createViolations($drivers, $officers, 200);

        // Create appeals
        $this->command->info('📝 Creating appeals...');
        $this->createAppeals();

        // Create system notifications
        // $this->command->info('🔔 Creating notifications...');
        // $this->createNotifications($drivers);

        // Create login audit trail
        // $this->command->info('📊 Creating audit logs...');
        // $this->createLoginAudits();

        $this->command->info('✅ Enhanced data seeding completed!');
    }

    private function createStations(): array
    {
        $stationModels = [];
        foreach ($this->stations as $station) {
            $stationModels[] = Station::firstOrCreate(
                ['station_name' => $station['station_name']],
                [
                    'region' => $station['region'],
                    'physical_address' => fake()->address(),
                    'contact_phone' => '+254' . fake()->numberBetween(700000000, 799999999),
                ]
            );
        }
        return $stationModels;
    }

    private function createOfficers(array $stations): array
    {
        $officerRole = Role::where('role_name', 'officer')->first();
        $officers = [];

        foreach (['John Kamau', 'Mary Wanjiku', 'Peter Omondi', 'Jane Akinyi', 
                  'David Mwangi', 'Grace Njeri', 'Samuel Otieno', 'Lucy Adhiambo'] as $index => $name) {
            $email = strtolower(str_replace(' ', '.', $name)) . '@ntsa.go.ke';
            
            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => Hash::make('Officer@123'),
                    'role' => 'officer',
                    'role_id' => $officerRole->id,
                    'is_active' => true,
                    'registered_on' => now()->subDays(rand(30, 365)),
                ]
            );

            // Assign officer to station
            if (isset($stations[$index % count($stations)])) {
                OfficerAssignment::firstOrCreate(
                    ['officer_user_id' => $user->id],
                    [
                        'station_id' => $stations[$index % count($stations)]->id,
                        'badge_number' => 'BADGE-' . str_pad($user->id, 5, '0', STR_PAD_LEFT),
                        'shift_type' => fake()->randomElement(['day', 'night', 'swing']),
                        'assignment_start_date' => now()->subDays(rand(30, 365))->format('Y-m-d'),
                    ]
                );
            }

            $officers[] = $user;
        }

        return $officers;
    }

    private function createDrivers(int $count): array
    {
        $motoristRole = Role::where('role_name', 'motorist')->first();
        $drivers = [];

        for ($i = 0; $i < $count; $i++) {
            $name = $this->kenyanNames[array_rand($this->kenyanNames)];
            $email = strtolower(str_replace(' ', '.', $name)) . rand(100, 999) . '@gmail.com';

            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => Hash::make('Driver@123'),
                    'role' => 'motorist',
                    'role_id' => $motoristRole->id,
                    'is_active' => true,
                    'registered_on' => now()->subDays(rand(1, 730)),
                ]
            );

            $licenseNo = sprintf('DL-KE-%04d', 2000 + $i);
            $issueDate = now()->subYears(rand(1, 15))->subDays(rand(1, 365));

            $driver = DriverProfile::firstOrCreate(
                ['license_no' => $licenseNo],
                [
                    'full_name' => $name,
                    'license_issue_date' => $issueDate->format('Y-m-d'),
                    'status' => fake()->randomElement(['active', 'active', 'active', 'restricted']),
                    'profile_photo_path' => null,
                ]
            );

            $drivers[] = $driver;
        }

        return $drivers;
    }

    private function createVehicles(array $drivers, int $count): void
    {
        $vehicleClasses = ['saloon', 'hatchback', 'suv', 'pickup', 'van'];
        $colors = ['white', 'silver', 'black', 'blue', 'red', 'grey', 'green'];
        $prefixes = ['KDA', 'KDB', 'KDC', 'KDD', 'KDE', 'KDF', 'KDG', 'KDH', 'KDJ', 'KDK',
                     'KCA', 'KCB', 'KCC', 'KBA', 'KBB', 'KBC', 'KAA', 'KAB', 'KAC'];

        for ($i = 0; $i < $count; $i++) {
            $prefix = $prefixes[array_rand($prefixes)];
            $number = rand(100, 999);
            $suffix = chr(rand(65, 90)); // A-Z
            $plateNumber = sprintf('%s %d%s', $prefix, $number, $suffix);

            $driver = $drivers[array_rand($drivers)];

            Vehicle::firstOrCreate(
                ['plate_number' => $plateNumber],
                [
                    'owner_user_id' => User::where('role', 'motorist')->inRandomOrder()->first()->id,
                    'make_model' => $this->vehicleMakes[array_rand($this->vehicleMakes)],
                    'vehicle_class' => $vehicleClasses[array_rand($vehicleClasses)],
                    'colour' => $colors[array_rand($colors)],
                    'year_of_manufacture' => rand(2010, 2024),
                    'registration_expiry' => now()->addMonths(rand(1, 24))->format('Y-m-d'),
                ]
            );
        }
    }

    private function createViolations(array $drivers, array $officers, int $count): void
    {
        $offenseTypes = [
            'speeding' => ['points' => 5, 'fine' => 5000],
            'red_light' => ['points' => 4, 'fine' => 3000],
            'dui' => ['points' => 8, 'fine' => 10000],
            'no_seatbelt' => ['points' => 2, 'fine' => 1000],
            'phone_use' => ['points' => 3, 'fine' => 2000],
            'reckless_driving' => ['points' => 6, 'fine' => 7000],
            'no_insurance' => ['points' => 6, 'fine' => 8000],
            'overloading' => ['points' => 4, 'fine' => 4000],
            'illegal_parking' => ['points' => 1, 'fine' => 500],
            'no_license' => ['points' => 8, 'fine' => 12000],
        ];

        $zoneTypes = ['urban', 'highway', 'school_zone', 'residential'];
        $weatherTypes = ['clear', 'rain', 'fog', 'night'];

        foreach ($drivers as $driver) {
            $violationCount = rand(1, 6);
            $runningBalance = 0;

            for ($v = 0; $v < $violationCount; $v++) {
                $offenseType = array_rand($offenseTypes);
                $offenseData = $offenseTypes[$offenseType];
                $points = $offenseData['points'];
                $fine = $offenseData['fine'];

                $occurredAt = now()->subDays(rand(1, 365));
                $officer = $officers[array_rand($officers)];

                $violation = Violation::create([
                    'driver_id' => $driver->id,
                    'officer_id' => $officer->id,
                    'offense_type' => $offenseType,
                    'speed_recorded' => $offenseType === 'speeding' ? rand(85, 160) : null,
                    'zone_type' => $zoneTypes[array_rand($zoneTypes)],
                    'weather_conditions' => $weatherTypes[array_rand($weatherTypes)],
                    'location' => $this->locations[array_rand($this->locations)],
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

                // 80% chance of payment
                if (rand(1, 100) <= 80) {
                    $paymentStatus = fake()->randomElement(['completed', 'completed', 'completed', 'pending', 'failed']);
                    $paidAt = $paymentStatus === 'completed' ? $occurredAt->copy()->addDays(rand(1, 30)) : null;

                    Payment::create([
                        'violation_id' => $violation->id,
                        'driver_id' => $driver->id,
                        'amount' => $fine,
                        'method' => fake()->randomElement(['mpesa', 'card']),
                        'status' => $paymentStatus,
                        'transaction_ref' => strtoupper(fake()->bothify('TRX-####-####')),
                        'paid_at' => $paidAt,
                    ]);
                }
            }

            // Create risk assessment for driver
            $this->createRiskAssessment($driver, $runningBalance, $violationCount);
        }
    }

    private function createRiskAssessment($driver, $runningBalance, $violationCount): void
    {
        $riskScore = min(0.98, max(0.05, 
            0.10 + ($runningBalance / 40) + ($violationCount / 25) + (rand(0, 20) / 100)
        ));

        $riskClass = $riskScore >= 0.70 ? 'high' : ($riskScore >= 0.40 ? 'moderate' : 'low');
        $confidence = round($riskScore, 4);

        $assessment = RiskAssessment::create([
            'driver_id' => $driver->id,
            'risk_score' => round($riskScore, 4),
            'risk_class' => $riskClass,
            'model_version' => 'rf_v1',
            'feature_snapshot' => [
                'active_points' => $runningBalance,
                'total_violations' => $violationCount,
                'days_since_last' => rand(1, 365),
            ],
            'generated_at' => now(),
        ]);

        $prediction = RiskPrediction::create([
            'driver_id' => $driver->id,
            'risk_score' => round($riskScore, 4),
            'risk_class' => $riskClass,
            'model_version' => 'rf_v1',
            'confidence_level' => $confidence,
            'predicted_at' => now(),
        ]);

        $assessment->update(['risk_prediction_id' => $prediction->id]);

        // Create sanction if high risk
        if ($riskClass === 'high') {
            SanctionAction::create([
                'driver_id' => $driver->id,
                'risk_assessment_id' => $assessment->id,
                'trigger_reason' => 'risk_score >= 0.70',
                'action_type' => 'profile_suspension',
                'webhook_status' => fake()->randomElement(['sent', 'pending', 'failed']),
                'webhook_response' => ['status' => 'queued', 'timestamp' => now()->toIso8601String()],
            ]);

            $driver->update(['status' => 'suspended']);
        } elseif ($riskClass === 'moderate' && $runningBalance >= 10) {
            SanctionAction::create([
                'driver_id' => $driver->id,
                'risk_assessment_id' => $assessment->id,
                'trigger_reason' => 'risk_score >= 0.40 AND active_points >= 10',
                'action_type' => 'profile_restriction',
                'webhook_status' => 'sent',
                'webhook_response' => ['status' => 'queued'],
            ]);

            $driver->update(['status' => 'restricted']);
        }
    }

    private function createAppeals(): void
    {
        $violations = Violation::with('driver')->limit(30)->get();

        foreach ($violations as $violation) {
            if (rand(1, 100) <= 20) { // 20% appeal rate
                Appeal::create([
                    'violation_id' => $violation->id,
                    'driver_id' => $violation->driver_id,
                    'reason' => fake()->randomElement([
                        'Speed camera malfunction',
                        'Emergency situation - medical',
                        'Incorrect vehicle identification',
                        'Following traffic officer instructions',
                        'Road signage not visible',
                        'Vehicle was not driven by owner',
                    ]),
                    'status' => fake()->randomElement(['pending', 'approved', 'rejected']),
                    'submitted_at' => now()->subDays(rand(1, 30)),
                ]);
            }
        }
    }

    private function createNotifications($drivers): void
    {
        $notificationTypes = [
            'payment_reminder' => 'Your fine payment is due in 7 days',
            'license_suspended' => 'Your driving license has been suspended',
            'appeal_approved' => 'Your appeal has been approved',
            'appeal_rejected' => 'Your appeal has been rejected',
            'points_added' => 'Demerit points added to your account',
            'payment_confirmed' => 'Your payment has been confirmed',
        ];

        foreach ($drivers as $driver) {
            if (rand(1, 100) <= 40) { // 40% get notifications
                $type = array_rand($notificationTypes);
                $message = $notificationTypes[$type];

                SystemNotification::create([
                    'driver_id' => $driver->id,
                    'type' => $type,
                    'message' => $message,
                    'is_read' => fake()->boolean(30),
                    'created_at' => now()->subDays(rand(1, 14)),
                ]);
            }
        }
    }

    private function createLoginAudits(): void
    {
        $users = User::all();

        foreach ($users as $user) {
            $loginCount = rand(5, 30);

            for ($i = 0; $i < $loginCount; $i++) {
                LoginAudit::create([
                    'user_id' => $user->id,
                    'ip_address' => fake()->ipv4(),
                    'user_agent' => fake()->userAgent(),
                    'login_at' => now()->subDays(rand(1, 90))->subHours(rand(0, 23)),
                    'login_successful' => fake()->boolean(95),
                ]);
            }
        }
    }
}
