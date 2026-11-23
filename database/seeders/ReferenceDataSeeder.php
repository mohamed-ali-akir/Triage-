<?php

namespace Database\Seeders;

use App\Models\Category;
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

        $categories = [
            'materiel' => 'Matériel',
            'reseau' => 'Réseau',
            'acces-comptes' => 'Accès et comptes',
            'logiciel' => 'Logiciel',
            'messagerie' => 'Messagerie',
            'autre' => 'Autre',
        ];

        foreach ($categories as $slug => $name) {
            Category::firstOrCreate(['slug' => $slug], ['name' => $name]);
        }
    }
}
