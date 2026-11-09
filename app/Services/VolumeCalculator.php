<?php

namespace App\Services;

use App\Enums\LoadType;
use App\Models\SessionExercise;
use App\Models\WorkoutSession;

/** Volume = Σ poids × reps × nb d'haltères (× 2 si unilatéral) sur les séries validées ; poids du corps exclu. */
class VolumeCalculator
{
    public static function set(float $weight, int $reps, LoadType $load, bool $unilateral): float
    {
        return $weight * $reps * $load->factor() * ($unilateral ? 2 : 1);
    }

    public static function exercise(SessionExercise $e): float
    {
        return $e->sets->where('is_completed', true)
            ->sum(fn ($s) => self::set($s->weight_kg, $s->reps, $e->load_type, $e->is_unilateral));
    }

    public static function session(WorkoutSession $s): float
    {
        return $s->exercises->sum(fn ($e) => self::exercise($e));
    }
}
