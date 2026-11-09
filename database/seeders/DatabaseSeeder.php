<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /** L'historique de démonstration (DemoHistorySeeder) n'est jamais lancé par défaut. */
    public function run(): void
    {
        $this->call([UserSeeder::class, ExerciseSeeder::class, ProgramSeeder::class]);
    }
}
