<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Needed everywhere: departments and categories.
        $this->call(ReferenceDataSeeder::class);

        // Demonstration accounts use a well-known password. They are created on a
        // development machine or in the tests only: any other environment (production,
        // but also "staging" or a misspelt name) gets none.
        if (app()->environment('local', 'testing')) {
            $this->call(DemoSeeder::class);
        }
    }
}
