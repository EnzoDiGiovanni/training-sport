<?php

namespace Database\Seeders;

use App\Models\Exercise;
use Illuminate\Database\Seeder;

/** Les 13 exercices de base du prototype (textes copiés de makeExercises). */
class ExerciseSeeder extends Seeder
{
    public function run(): void
    {
        foreach (json_decode(file_get_contents(database_path('data/exercises.json')), true) as $e) {
            Exercise::updateOrCreate(['slug' => $e['slug']], $e + ['user_id' => null, 'is_custom' => false]);
        }
    }
}
