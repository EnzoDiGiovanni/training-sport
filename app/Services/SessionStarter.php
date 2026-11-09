<?php

namespace App\Services;

use App\Enums\LoadType;
use App\Enums\SessionStatus;
use App\Enums\WorkoutType;
use App\Models\SessionExercise;
use App\Models\User;
use App\Models\Workout;
use App\Models\WorkoutExercise;
use App\Models\WorkoutSession;
use Illuminate\Support\Facades\DB;

/** Démarre une séance : snapshot des cibles du programme (règle 1) et pré-remplissage (règle 3). */
class SessionStarter
{
    public function start(User $user, Workout $workout, bool $abandonCurrent = false): WorkoutSession
    {
        return DB::transaction(function () use ($user, $workout, $abandonCurrent) {
            $current = self::current($user);
            if ($current && ! $abandonCurrent) {
                throw new SessionInProgressException($current);
            }
            $current?->update(['status' => SessionStatus::Abandoned, 'ended_at' => now()]);

            $workout->load('exercises.exercise');
            $snapshot = $workout->type === WorkoutType::Run
                ? ['segments' => RunMetrics::tree($workout)]
                : ['exercises' => $workout->exercises->map(fn (WorkoutExercise $we) => [
                    'exercise_id' => $we->exercise_id, 'name' => $we->exercise->name, 'sets' => $we->sets,
                    'reps_min' => $we->reps_min, 'reps_max' => $we->reps_max,
                    'target_weight_kg' => $we->target_weight_kg, 'rest_seconds' => $we->rest_seconds,
                ])->all()];

            $session = $user->sessions()->create([
                'workout_id' => $workout->id, 'workout_name' => $workout->name, 'type' => $workout->type,
                'performed_on' => today(), 'started_at' => now(), 'status' => SessionStatus::InProgress,
                'planned_snapshot' => $snapshot + ['objective' => $workout->objective],
            ]);

            foreach ($workout->exercises as $i => $we) {
                $this->addExercise($user, $session, $we, $i);
            }

            return $session;
        });
    }

    private function addExercise(User $user, WorkoutSession $session, WorkoutExercise $we, int $position): void
    {
        $ex = $we->exercise;
        $item = $session->exercises()->create([
            'exercise_id' => $ex->id, 'exercise_name' => $ex->name, 'measurement' => $ex->measurement,
            'load_type' => $ex->load_type, 'is_unilateral' => $ex->is_unilateral, 'position' => $position,
            'target_sets' => $we->sets, 'target_reps_min' => $we->reps_min, 'target_reps_max' => $we->reps_max,
            'target_weight_kg' => $we->target_weight_kg, 'rest_seconds' => $we->rest_seconds,
        ]);

        // Série par série depuis la dernière performance, sinon les cibles du programme.
        $prev = self::lastPerformance($user, $ex->id)?->sets->where('is_completed', true)->values();
        for ($n = 0; $n < $we->sets; $n++) {
            $pv = $prev?->get($n) ?? $prev?->last();
            $item->sets()->create([
                'set_number' => $n + 1,
                'weight_kg' => $ex->load_type === LoadType::Bodyweight ? 0 : ($pv?->weight_kg ?? $we->target_weight_kg ?? 0),
                'reps' => $pv?->reps ?? $we->reps_max,
            ]);
        }
    }

    public static function current(User $user): ?WorkoutSession
    {
        return $user->sessions()->where('status', SessionStatus::InProgress)->latest('id')->first();
    }

    /** Dernière réalisation terminée d'un exercice (avec ses séries), hors séance exclue. */
    public static function lastPerformance(User $user, int $exerciseId, ?int $excludeSessionId = null): ?SessionExercise
    {
        return SessionExercise::query()
            ->select('session_exercises.*')
            ->join('workout_sessions as ws', 'ws.id', '=', 'session_exercises.workout_session_id')
            ->where('ws.user_id', $user->id)->where('ws.status', SessionStatus::Completed)
            ->where('session_exercises.exercise_id', $exerciseId)
            ->when($excludeSessionId, fn ($q) => $q->where('ws.id', '!=', $excludeSessionId))
            ->whereExists(fn ($q) => $q->from('exercise_sets')->whereColumn('session_exercise_id', 'session_exercises.id')->where('is_completed', true))
            ->orderByDesc('ws.performed_on')->orderByDesc('ws.id')
            ->with(['sets', 'session'])
            ->first();
    }
}
