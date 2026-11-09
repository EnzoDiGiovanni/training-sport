<?php

namespace App\Http\Presenters;

use App\Models\SessionExercise;
use App\Models\User;
use App\Models\Workout;
use App\Models\WorkoutSession;
use App\Services\RunMetrics;
use App\Services\VolumeCalculator;

/** Formes envoyées aux pages Inertia (voir resources/js/types/models.ts). */
class Present
{
    public static function profile(User $user): array
    {
        return $user->profile->toArray() + ['name' => $user->name, 'weight' => $user->currentWeight()];
    }

    public static function workout(Workout $w): array
    {
        $w->loadMissing('exercises', 'days');

        return [
            'id' => $w->id, 'name' => $w->name, 'type' => $w->type->value, 'objective' => $w->objective,
            'estimated_duration_min' => $w->estimated_duration_min,
            'exercises' => $w->exercises->map(fn ($e) => $e->only('id', 'exercise_id', 'sets', 'reps_min', 'reps_max', 'target_weight_kg', 'rest_seconds', 'notes'))->all(),
            'segments' => RunMetrics::tree($w),
            'days' => $w->days->pluck('day_of_week')->sort()->values()->all(),
        ];
    }

    /** Séances type actives du programme actif. */
    public static function workouts(User $user): array
    {
        $program = $user->activeProgram;
        if (! $program) {
            return [];
        }

        return $program->workouts()->active()->with('exercises', 'days', 'segments.children')->get()
            ->map(fn ($w) => self::workout($w))->all();
    }

    /** Jour (1-7) → id de séance ou null. */
    public static function schedule(User $user): array
    {
        $days = $user->activeProgram?->days()->with('workout')->get() ?? collect();

        return collect(range(1, 7))->mapWithKeys(function ($d) use ($days) {
            $w = $days->firstWhere('day_of_week', $d)?->workout;

            return [$d => $w && ! $w->archived_at ? $w->id : null];
        })->all();
    }

    public static function session(WorkoutSession $s): array
    {
        $s->loadMissing('exercises.sets', 'exercises.exercise', 'run');
        $s->run?->setRelation('session', $s); // vitesse moyenne calculée sans requête supplémentaire

        return [
            'id' => $s->id, 'workout_id' => $s->workout_id, 'name' => $s->workout_name, 'type' => $s->type->value,
            'date' => $s->performed_on->toDateString(), 'duration_seconds' => $s->duration_seconds,
            'rpe' => $s->perceived_effort, 'comment' => $s->comment, 'activity' => $s->activity,
            'run' => $s->run ? $s->run->only('distance_km', 'max_speed_kmh', 'incline_pct', 'cycles_completed', 'cycles_planned', 'average_speed_kmh') : null,
            'exercises' => $s->exercises->map(fn ($e) => self::item($e))->all(),
            'volume' => VolumeCalculator::session($s),
        ];
    }

    public static function item(SessionExercise $e): array
    {
        return [
            'id' => $e->id, 'exercise_id' => $e->exercise_id, 'name' => $e->exercise_name,
            'measurement' => $e->measurement->value, 'load_type' => $e->load_type->value, 'is_unilateral' => $e->is_unilateral,
            'note' => $e->note, 'target_sets' => $e->target_sets, 'target_reps_min' => $e->target_reps_min,
            'target_reps_max' => $e->target_reps_max, 'target_weight_kg' => $e->target_weight_kg, 'rest_seconds' => $e->rest_seconds,
            'illustration_key' => $e->exercise?->illustration_key, 'image_url' => $e->exercise?->image_url,
            'sets' => $e->sets->map(fn ($x) => $x->only('id', 'weight_kg', 'reps', 'is_completed'))->all(),
        ];
    }

    /** Séances terminées, plus récentes d'abord. */
    public static function history(User $user, ?int $limit = null): array
    {
        return $user->sessions()->completed()->with('exercises.sets', 'exercises.exercise', 'run')
            ->orderByDesc('performed_on')->orderByDesc('id')->when($limit, fn ($q) => $q->limit($limit))->get()
            ->map(fn ($s) => self::session($s))->all();
    }
}
