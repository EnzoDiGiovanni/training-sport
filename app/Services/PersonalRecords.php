<?php

namespace App\Services;

use App\Enums\LoadType;
use App\Enums\SessionStatus;
use App\Enums\WorkoutType;
use App\Models\SessionExercise;
use App\Models\WorkoutSession;

/** Records affichés dans le récapitulatif (règle 10). */
class PersonalRecords
{
    /** @return list<string> */
    public static function for(WorkoutSession $session): array
    {
        $session->loadMissing('exercises.sets', 'run');

        return $session->type === WorkoutType::Run ? self::run($session) : self::strength($session);
    }

    private static function strength(WorkoutSession $session): array
    {
        $out = [];
        foreach ($session->exercises as $item) {
            if (! $item->exercise_id || $item->load_type === LoadType::Bodyweight) {
                continue;
            }
            $before = SessionExercise::query()->with('sets')
                ->join('workout_sessions as ws', 'ws.id', '=', 'session_exercises.workout_session_id')
                ->where('ws.user_id', $session->user_id)->where('ws.status', SessionStatus::Completed)
                ->where('ws.id', '!=', $session->id)->where('session_exercises.exercise_id', $item->exercise_id)
                ->orderBy('ws.performed_on')->orderBy('ws.id')->get(['session_exercises.*']);
            if ($before->isEmpty()) {
                continue;
            }
            $done = fn (SessionExercise $e) => $e->sets->where('is_completed', true);
            $best = $before->max(fn ($e) => $done($e)->max('weight_kg') ?? 0);
            $now = $done($item)->max('weight_kg') ?? 0;
            if ($now > $best) {
                $out[] = sprintf('%s : nouvelle charge max, %s kg (avant %s kg)', $item->exercise_name, self::kg($now), self::kg($best));

                continue;
            }
            $prevVol = VolumeCalculator::exercise($before->last());
            $vol = VolumeCalculator::exercise($item);
            if ($prevVol > 0 && $vol > $prevVol * 1.05) {
                $out[] = sprintf('%s : volume en hausse de %d %%', $item->exercise_name, round(($vol / $prevVol - 1) * 100));
            }
        }

        return $out;
    }

    /** Distance et vitesse comparées uniquement à la même séance type. */
    private static function run(WorkoutSession $session): array
    {
        if (! $session->run || ! $session->workout_id) {
            return [];
        }
        $same = WorkoutSession::query()->completed()->with('run')
            ->where('user_id', $session->user_id)->where('workout_id', $session->workout_id)
            ->where('id', '!=', $session->id)->whereHas('run')->get();
        if ($same->isEmpty()) {
            return [];
        }
        $out = [];
        $bestD = $same->max(fn ($s) => $s->run->distance_km);
        $bestA = $same->max(fn ($s) => $s->run->average_speed_kmh);
        $avg = $session->run->average_speed_kmh;
        if ($session->run->distance_km > $bestD) {
            $out[] = 'Plus longue distance sur cette séance : '.self::num($session->run->distance_km, 2).' km';
        }
        if ($avg > $bestA + 0.005) {
            $out[] = 'Meilleure vitesse moyenne : '.self::num($avg, 2).' km/h (avant '.self::num($bestA, 2).')';
        }

        return $out;
    }

    private static function kg(float $v): string
    {
        return self::num($v, fmod($v, 1) ? 1 : 0);
    }

    private static function num(float $v, int $d): string
    {
        return number_format($v, $d, ',', "\u{202F}");
    }
}
