<?php

namespace Database\Seeders;

use App\Enums\SessionStatus;
use App\Models\Exercise;
use App\Models\User;
use App\Models\Workout;
use App\Services\RunMetrics;
use Illuminate\Database\Seeder;

/**
 * Historique de démonstration (6 semaines + semaine en cours, pesées sur 9 semaines), porté de makeHistory / makeWeights.
 * Facultatif : php artisan db:seed --class=DemoHistorySeeder. Ne jamais lancer en production.
 */
class DemoHistorySeeder extends Seeder
{
    private int $seed = 42;

    /** PRNG déterministe du prototype. */
    private function rnd(): float
    {
        $this->seed = ($this->seed * 1664525 + 1013904223) % 4294967296;

        return $this->seed / 4294967296;
    }

    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->error('DemoHistorySeeder ne s’exécute pas en production.');

            return;
        }
        $user = User::firstOrFail();
        $program = $user->activeProgram()->with('days.workout.exercises.exercise')->firstOrFail();
        $exercises = Exercise::all()->keyBy('slug');
        // charge de départ, incrément, toutes les n semaines, plafond
        $prog = ['db-bench' => [8, 1, 2, 10], 'row' => [14, 2, 2, 20], 'bulgarian' => [5, 1, 2, 8], 'shoulder-press' => [6, 1, 3, 8], 'crunch' => [0, 0, 1, 0],
            'rdl' => [8, 1, 2, 10], 'incline' => [7, 1, 3, 9], 'hip-thrust' => [14, 2, 2, 20], 'curl' => [6, 1, 3, 8], 'triceps' => [8, 1, 2, 11], 'side-plank' => [0, 0, 1, 0]];
        $skip = ['2-2' => true, '4-5' => true];
        $thisMonday = today()->startOfWeek();

        for ($k = 0; $k < 7; $k++) {
            $monday = $thisMonday->copy()->subWeeks(6 - $k);
            for ($d = 0; $d < 7; $d++) {
                $date = $monday->copy()->addDays($d);
                if ($date->gte(today()) || isset($skip["$k-$d"])) {
                    continue;
                }
                /** @var Workout|null $w */
                $w = $program->days->firstWhere('day_of_week', $d + 1)?->workout;
                if (! $w) {
                    continue;
                }
                $base = ['workout_id' => $w->id, 'workout_name' => $w->name, 'type' => $w->type, 'performed_on' => $date,
                    'status' => SessionStatus::Completed, 'started_at' => $date->copy()->setTime(18, 30), 'perceived_effort' => 3];

                if ($w->name === 'Endurance fondamentale') {
                    $v = round(8.2 + 0.08 * $k + ($this->rnd() - .5) * .1, 1);
                    $this->addRun($user, $base + ['duration_seconds' => 1800 + (int) round($this->rnd() * 40), 'perceived_effort' => $k < 3 ? 3 : 2,
                        'comment' => $k === 6 ? 'Bonnes sensations, souffle régulier.' : null], 5 / 60 * 6 + 20 / 60 * $v + 5 / 60 * 5, min(10, $v + 0.4), 1, null, null);
                } elseif ($w->name === 'Fractionné') {
                    $fast = $k < 3 ? 9.5 : 10;
                    $cycles = $k === 1 ? 4 : 5;
                    $this->addRun($user, $base + ['duration_seconds' => (10 + $cycles * 3) * 60 + (int) round($this->rnd() * 30), 'perceived_effort' => 4,
                        'comment' => $k === 1 ? 'Arrêté au 4e cycle, jambes lourdes.' : ($k === 5 ? 'Dernier cycle à 10 km/h tenu sans problème.' : null)],
                        5 / 60 * 6.5 + $cycles / 60 * $fast + $cycles * 2 / 60 * 7 + 5 / 60 * 5, $fast, 0, $cycles, 5);
                } elseif ($w->name === 'Endurance longue') {
                    $mins = min(30, 25 + $k);
                    $v = round(8.0 + 0.06 * $k, 1);
                    $this->addRun($user, $base + ['duration_seconds' => ($mins + 10) * 60 + (int) round($this->rnd() * 40)],
                        5 / 60 * 6 + $mins / 60 * $v + 5 / 60 * 5, $v + 0.5, 1, null, null);
                } elseif ($w->type->value === 'strength') {
                    $session = $user->sessions()->create($base + [
                        'duration_seconds' => (41 + (int) round($this->rnd() * 6)) * 60, 'perceived_effort' => 3 + ($this->rnd() > .6 ? 1 : 0),
                        'comment' => $k === 4 && $w->name === 'Musculation A' ? 'Passage à 10 kg au développé couché, dernière série difficile.' : null,
                    ]);
                    foreach ($w->exercises as $i => $we) {
                        $ex = $we->exercise;
                        [$b, $inc, $every, $cap] = $prog[$ex->slug] ?? [$we->target_weight_kg ?? 0, 0, 1, $we->target_weight_kg ?? 0];
                        $kg = min($cap, $b + intdiv($k, $every) * $inc);
                        $item = $session->exercises()->create([
                            'exercise_id' => $ex->id, 'exercise_name' => $ex->name, 'measurement' => $ex->measurement, 'load_type' => $ex->load_type,
                            'is_unilateral' => $ex->is_unilateral, 'position' => $i, 'target_sets' => $we->sets, 'target_reps_min' => $we->reps_min,
                            'target_reps_max' => $we->reps_max, 'target_weight_kg' => $we->target_weight_kg, 'rest_seconds' => $we->rest_seconds,
                        ]);
                        for ($s = 0; $s < $we->sets; $s++) {
                            $reps = match (true) {
                                $ex->measurement->value === 'time' => min(40, 25 + $k * 2),
                                $ex->load_type->value === 'bodyweight' => min($we->reps_max, $we->reps_min + (int) floor($k / 1.5) - $s),
                                default => max($we->reps_min, ($k % $every === 0 ? $we->reps_min + 1 : $we->reps_max) - $s),
                            };
                            $item->sets()->create(['set_number' => $s + 1, 'weight_kg' => $kg, 'reps' => max(1, $reps), 'is_completed' => true, 'completed_at' => $date]);
                        }
                    }
                } elseif ($w->type->value === 'recovery' && $k % 2 === 1) {
                    $user->sessions()->create($base + ['duration_seconds' => (25 + (int) round($this->rnd() * 15)) * 60, 'perceived_effort' => 1,
                        'activity' => 'Marche', 'comment' => 'Marche tranquille.']);
                }
            }
        }

        $this->seed = 7;
        $user->weights()->delete();
        $start = today()->subDays(63);
        $last = null;
        for ($i = 0; $i <= 63; $i += ($i % 3 === 0 ? 2 : 3)) {
            $day = $start->copy()->addDays($i);
            if ($day->gte(today())) {
                break;
            }
            $last = $user->weights()->create(['measured_on' => $day, 'weight_kg' => round(68.4 - ($i / 63) * 1.9 + ($this->rnd() - .5) * .6, 1)]);
        }
        $last?->update(['weight_kg' => 66.5]);
    }

    private function addRun(User $user, array $base, float $km, float $max, float $incline, ?int $cycles, ?int $planned): void
    {
        $session = $user->sessions()->create($base);
        $session->run()->create(['distance_km' => round($km, 2), 'max_speed_kmh' => round($max, 1), 'incline_pct' => $incline,
            'cycles_completed' => $cycles, 'cycles_planned' => $planned]);
        $session->update(['planned_snapshot' => ['segments' => RunMetrics::tree($session->workout)]]);
    }
}
