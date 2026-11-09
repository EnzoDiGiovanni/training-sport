<?php

namespace Database\Seeders;

use App\Models\Exercise;
use App\Models\User;
use App\Services\WorkoutWriter;
use Illuminate\Database\Seeder;

/** Programme du prototype (makeWorkouts) : lundi → samedi, dimanche repos. */
class ProgramSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::firstOrFail();
        if ($user->programs()->exists()) {
            return;
        }
        $program = $user->programs()->create(['name' => 'Recomposition', 'is_active' => true]);
        $ids = Exercise::pluck('id', 'slug');
        $days = [];

        foreach (json_decode(file_get_contents(database_path('data/program.json')), true) as $i => $w) {
            $workout = $program->workouts()->create([
                'name' => $w['name'], 'type' => $w['type'], 'objective' => $w['objective'],
                'estimated_duration_min' => $w['estimated_duration_min'], 'position' => $i,
            ]);
            WorkoutWriter::sync(
                $workout,
                array_map(fn ($e) => ['exercise_id' => $ids[$e['slug']]] + $e, $w['exercises']),
                $w['segments'],
            );
            $days[$w['day']] = $workout->id;
        }
        for ($d = 1; $d <= 7; $d++) {
            $program->days()->create(['day_of_week' => $d, 'workout_id' => $days[$d] ?? null]);
        }
    }
}
