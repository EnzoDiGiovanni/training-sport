<?php

namespace App\Services;

use App\Models\Workout;
use Illuminate\Support\Facades\DB;

/** Enregistre le contenu d'une séance type (exercices ordonnés ou arbre de phases de course). */
class WorkoutWriter
{
    /**
     * @param  list<array<string,mixed>>  $exercises  [exercise_id, sets, reps_min, reps_max, target_weight_kg, rest_seconds, notes]
     * @param  list<array<string,mixed>>  $segments  arbre (voir RunMetrics)
     */
    public static function sync(Workout $workout, array $exercises, array $segments): void
    {
        DB::transaction(function () use ($workout, $exercises, $segments) {
            $workout->exercises()->delete();
            $workout->allSegments()->delete();
            foreach (array_values($exercises) as $i => $e) {
                $workout->exercises()->create([
                    'exercise_id' => $e['exercise_id'], 'position' => $i, 'sets' => $e['sets'],
                    'reps_min' => $e['reps_min'], 'reps_max' => $e['reps_max'],
                    'target_weight_kg' => $e['target_weight_kg'] ?? null,
                    'rest_seconds' => $e['rest_seconds'] ?? 90, 'notes' => $e['notes'] ?? null,
                ]);
            }
            self::segments($workout, $segments, null);
        });
    }

    private static function segments(Workout $workout, array $tree, ?int $parentId): void
    {
        foreach (array_values($tree) as $i => $s) {
            $isRepeat = $s['kind'] === 'repeat';
            $row = $workout->allSegments()->create([
                'parent_id' => $parentId, 'position' => $i, 'kind' => $s['kind'],
                'label' => $isRepeat ? null : $s['label'],
                'duration_min' => $isRepeat ? null : $s['duration_min'],
                'speed_min_kmh' => $isRepeat ? null : $s['speed_min_kmh'],
                'speed_max_kmh' => $isRepeat ? null : $s['speed_max_kmh'],
                'incline_pct' => $isRepeat ? null : ($s['incline_pct'] ?? 0),
                'repeat_count' => $isRepeat ? $s['repeat_count'] : null,
            ]);
            if ($isRepeat) {
                self::segments($workout, $s['children'] ?? [], $row->id);
            }
        }
    }
}
