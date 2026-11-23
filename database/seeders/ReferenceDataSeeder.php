<?php

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;

class ReferenceDataSeeder extends Seeder
{
    /**
     * Data the application needs in every environment, production included.
     */
    public function run(): void
    {
        $departments = ['Direction', 'Comptabilité', 'Ressources humaines', 'Commercial', 'Production', 'Informatique'];

        foreach ($departments as $name) {
            Department::firstOrCreate(['name' => $name]);
        }
    }
}
