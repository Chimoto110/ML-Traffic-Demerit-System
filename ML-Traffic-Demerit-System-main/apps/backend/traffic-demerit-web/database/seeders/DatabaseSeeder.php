<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Calls DemoDataSeeder which is idempotent and never truncates existing data.
     */
    public function run(): void
    {
        $this->call(DemoDataSeeder::class);
        $this->call(DemoDriverAccountsSeeder::class);
    }
}
