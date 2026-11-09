<?php

namespace App\Services;

use App\Enums\LoadType;
use App\Enums\SessionStatus;
use App\Enums\WorkoutType;
use App\Models\SessionExercise;
use App\Models\User;
use Illuminate\Support\Carbon;

class StatsService
{
    /** Séances (course + muscu), km et durée d'une semaine commençant le lundi donné. */
    public static function week(User $user, Carbon $monday): array
    {
        $list = $user->sessions()->completed()->with('run')
            ->whereBetween('performed_on', [$monday->toDateString(), $monday->copy()->addDays(6)->toDateString()])->get();

        return [
            'count' => $list->whereIn('type', [WorkoutType::Run, WorkoutType::Strength])->count(),
            'strength' => $list->where('type', WorkoutType::Strength)->count(),
            'km' => round($list->sum(fn ($s) => $s->run?->distance_km ?? 0), 2),
            'seconds' => (int) $list->sum('duration_seconds'),
        ];
    }

    /**
     * Moyenne mobile sur $days jours calendaires (fenêtre glissante incluant le jour).
     *
     * @param  list<array{date:string,kg:float}>  $list  triée par date croissante
     * @return list<float>
     */
    public static function movingAverage(array $list, int $days = 7): array
    {
        return array_map(function ($p) use ($list, $days) {
            $from = Carbon::parse($p['date'])->subDays($days - 1)->toDateString();
            $w = array_filter($list, fn ($x) => $x['date'] >= $from && $x['date'] <= $p['date']);

            return array_sum(array_column($w, 'kg')) / count($w);
        }, $list);
    }

    /** Pesées + moyenne mobile 7 j, variation 30 j, rythme kg/semaine et alerte de perte trop rapide (règle 12). */
    public static function weight(User $user): array
    {
        $list = $user->weights()->get()->map(fn ($w) => ['id' => $w->id, 'date' => $w->measured_on->toDateString(), 'kg' => $w->weight_kg])->all();
        if (! $list) {
            return ['list' => [], 'last' => null, 'delta30' => null, 'ma_delta30' => null, 'rate' => null, 'too_fast' => false];
        }
        $ma = self::movingAverage($list);
        foreach ($list as $i => &$p) {
            $p['ma'] = round($ma[$i], 2);
        }
        unset($p);
        $last = end($list);
        $from30 = today()->subDays(30)->toDateString();
        $i30 = collect($list)->search(fn ($x) => $x['date'] >= $from30);
        $i30 = $i30 === false ? 0 : $i30;
        $weeks = max(1, Carbon::parse($list[0]['date'])->diffInDays(Carbon::parse($last['date'])) / 7);
        $rate = (end($ma) - $ma[0]) / $weeks;

        return [
            'list' => $list,
            'last' => $last,
            'delta30' => round($last['kg'] - $list[$i30]['kg'], 2),
            'ma_delta30' => round(end($ma) - $ma[$i30], 2),
            'rate' => round($rate, 2),
            'too_fast' => $rate <= -0.75,
        ];
    }

    /**
     * Historique d'un exercice, du plus ancien au plus récent (séries validées uniquement).
     *
     * @return list<array<string,mixed>>
     */
    public static function exerciseHistory(User $user, int $exerciseId): array
    {
        return SessionExercise::query()->with('sets')
            ->join('workout_sessions as ws', 'ws.id', '=', 'session_exercises.workout_session_id')
            ->where('ws.user_id', $user->id)->where('ws.status', SessionStatus::Completed)
            ->where('session_exercises.exercise_id', $exerciseId)
            ->orderBy('ws.performed_on')->orderBy('ws.id')
            ->get(['session_exercises.*', 'ws.performed_on'])
            ->map(function (SessionExercise $e) {
                $done = $e->sets->where('is_completed', true);

                return [
                    'session_id' => $e->workout_session_id,
                    'date' => substr((string) $e->performed_on, 0, 10),
                    'max_kg' => $e->load_type === LoadType::Bodyweight ? 0 : (float) ($done->max('weight_kg') ?? 0),
                    'reps' => (int) $done->sum('reps'),
                    'best_reps' => (int) ($done->max('reps') ?? 0),
                    'volume' => VolumeCalculator::exercise($e),
                    'note' => $e->note,
                    'sets' => $done->map(fn ($s) => ['weight_kg' => $s->weight_kg, 'reps' => $s->reps])->values()->all(),
                ];
            })
            ->filter(fn ($h) => $h['sets'])->values()->all();
    }
}
