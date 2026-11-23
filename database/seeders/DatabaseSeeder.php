<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Needed everywhere: departments and categories.
        $this->call(ReferenceDataSeeder::class);

        // Demonstration accounts use a well-known password: never seed them in production.
        if (! app()->isProduction()) {
            $this->call(DemoSeeder::class);
        }
    }
}
