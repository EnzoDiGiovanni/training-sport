<?php

namespace App\Http\Controllers;

use App\Enums\SessionStatus;
use App\Enums\WorkoutType;
use App\Http\Presenters\Present;
use App\Http\Requests\LiveRunRequest;
use App\Models\Workout;
use App\Models\WorkoutSession;
use App\Services\CalorieEstimator;
use App\Services\PersonalRecords;
use App\Services\RunMetrics;
use App\Services\SessionInProgressException;
use App\Services\SessionStarter;
use App\Services\VolumeCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class LiveSessionController extends Controller
{
    public function start(Request $request, Workout $workout, SessionStarter $starter): RedirectResponse
    {
        $this->own($workout->program->user_id);
        abort_unless(in_array($workout->type, [WorkoutType::Run, WorkoutType::Strength], true) && ! $workout->archived_at, 404);
        try {
            $starter->start($this->user(), $workout, $request->boolean('abandon_current'));
        } catch (SessionInProgressException) {
            return redirect()->route('live.show')->with('toast', 'Une séance est déjà en cours');
        }

        return redirect()->route('live.show');
    }

    public function show(): Response|RedirectResponse
    {
        $user = $this->user();
        $session = SessionStarter::current($user);
        if (! $session) {
            return redirect()->route('dashboard');
        }

        if ($session->type === WorkoutType::Run) {
            $session->load('run');

            return Inertia::render('Live/Run', [
                'session' => Present::session($session) + ['started_at' => $session->started_at->toIso8601String()],
                'segments' => $session->planned_snapshot['segments'] ?? [],
                'profile' => Present::profile($user),
            ]);
        }

        $session->load('exercises.sets', 'exercises.exercise');
        $prev = [];
        foreach ($session->exercises as $item) {
            $p = $item->exercise_id ? SessionStarter::lastPerformance($user, $item->exercise_id, $session->id) : null;
            $prev[$item->id] = $p ? Present::item($p) + ['date' => $p->session->performed_on->toDateString()] : null;
        }

        return Inertia::render('Live/Strength', [
            'session' => Present::session($session) + ['started_at' => $session->started_at->toIso8601String()],
            'prev' => $prev,
            'exercises' => ExerciseController::library($user),
            'profile' => Present::profile($user),
        ]);
    }

    /** Sauvegarde automatique de la séance de musculation (séries, charges, notes). */
    public function autosave(Request $request): JsonResponse
    {
        $session = $this->currentOrFail(WorkoutType::Strength);
        $data = $request->validate([
            'exercises' => ['required', 'array'],
            'exercises.*.id' => ['required', 'integer'],
            'exercises.*.note' => ['nullable', 'string', 'max:255'],
            'exercises.*.sets' => ['required', 'array', 'min:1', 'max:20'],
            'exercises.*.sets.*.weight_kg' => ['required', 'numeric', 'min:0', 'max:500'],
            'exercises.*.sets.*.reps' => ['required', 'integer', 'min:0', 'max:1000'],
            'exercises.*.sets.*.is_completed' => ['required', 'boolean'],
        ]);

        DB::transaction(function () use ($session, $data) {
            $items = $session->exercises()->with('sets')->get()->keyBy('id');
            foreach ($data['exercises'] as $e) {
                $item = $items->get($e['id']) ?? abort(422);
                $item->update(['note' => $e['note'] ?? null]);
                foreach (array_values($e['sets']) as $i => $s) {
                    $existing = $item->sets->firstWhere('set_number', $i + 1);
                    $attrs = ['weight_kg' => $s['weight_kg'], 'reps' => $s['reps'], 'is_completed' => $s['is_completed']];
                    $attrs['completed_at'] = $s['is_completed'] ? ($existing?->completed_at ?? now()) : null;
                    $item->sets()->updateOrCreate(['set_number' => $i + 1], $attrs);
                }
                $item->sets()->where('set_number', '>', count($e['sets']))->delete();
            }
        });

        return response()->json(['volume' => VolumeCalculator::session($session->fresh('exercises.sets'))]);
    }

    public function saveRun(LiveRunRequest $request): RedirectResponse
    {
        $session = $this->currentOrFail(WorkoutType::Run);
        DB::transaction(function () use ($request, $session) {
            $session->update([
                'duration_seconds' => RunMetrics::parseClock($request->input('duration')),
                'perceived_effort' => $request->input('rpe'), 'comment' => $request->input('comment'),
            ]);
            $planned = RunMetrics::plannedCycles($session->planned_snapshot['segments'] ?? []);
            $session->run()->updateOrCreate([], [
                'distance_km' => round((float) $request->input('distance_km'), 2),
                'max_speed_kmh' => $request->input('max_speed_kmh'), 'incline_pct' => $request->input('incline_pct'),
                'cycles_planned' => $planned, 'cycles_completed' => $planned ? $request->input('cycles_completed') : null,
            ]);
        });

        return redirect()->route('live.summary');
    }

    public function summary(): Response|RedirectResponse
    {
        $user = $this->user();
        $session = SessionStarter::current($user);
        if (! $session) {
            return redirect()->route('dashboard');
        }
        $session->load('exercises.sets', 'exercises.exercise', 'run');
        if ($session->type === WorkoutType::Run && ! $session->run) {
            return redirect()->route('live.show');
        }
        $isStrength = $session->type === WorkoutType::Strength;
        $duration = $isStrength ? (int) $session->started_at->diffInSeconds(now()) : $session->duration_seconds;

        // Récapitulatif sur les seules séries validées.
        $present = Present::session($session);
        $present['duration_seconds'] = $duration;
        $present['exercises'] = collect($present['exercises'])
            ->map(fn ($e) => ['sets' => array_values(array_filter($e['sets'], fn ($s) => $s['is_completed']))] + $e)
            ->filter(fn ($e) => $e['sets'])->values()->all();

        $weight = $user->currentWeight() ?? 0;
        $previous = $session->workout_id ? WorkoutSession::completed()->where('user_id', $user->id)
            ->where('workout_id', $session->workout_id)->latest('performed_on')->latest('id')->first() : null;

        return Inertia::render('Live/Summary', [
            'session' => $present,
            'records' => PersonalRecords::for($session),
            'previous' => $previous ? Present::session($previous) : null,
            'previousVolumes' => $isStrength ? $session->exercises->mapWithKeys(fn ($e) => [
                $e->id => ($p = $e->exercise_id ? SessionStarter::lastPerformance($user, $e->exercise_id, $session->id) : null) ? VolumeCalculator::exercise($p) : null,
            ]) : [],
            'kcal' => round($isStrength ? CalorieEstimator::strength($weight, $duration) : CalorieEstimator::run($weight, $session->run->distance_km)),
        ]);
    }

    public function complete(Request $request): RedirectResponse
    {
        $session = $this->currentOrFail();
        $data = $request->validate(['rpe' => ['nullable', 'integer', 'between:1,5'], 'comment' => ['nullable', 'string', 'max:2000']]);

        if ($session->type === WorkoutType::Strength) {
            $session->load('exercises.sets');
            if (! $session->exercises->contains(fn ($e) => $e->sets->contains('is_completed', true))) {
                return back()->withErrors(['session' => 'Valide au moins une série pour enregistrer la séance, ou abandonne-la.']);
            }
            DB::transaction(function () use ($session, $data) {
                foreach ($session->exercises as $e) {
                    $e->sets()->where('is_completed', false)->delete();
                    $e->sets()->exists() ? null : $e->delete();
                }
                $session->update([
                    'perceived_effort' => $data['rpe'] ?? null, 'comment' => $data['comment'] ?? null,
                    'duration_seconds' => (int) $session->started_at->diffInSeconds(now()),
                ]);
            });
        } elseif (! $session->run) {
            return redirect()->route('live.show');
        }
        $session->update(['status' => SessionStatus::Completed, 'ended_at' => now()]);

        return redirect()->route('dashboard')->with('toast', 'Séance enregistrée');
    }

    public function abandon(): RedirectResponse
    {
        $this->currentOrFail()->update(['status' => SessionStatus::Abandoned, 'ended_at' => now()]);

        return redirect()->route('dashboard')->with('toast', 'Séance abandonnée');
    }

    /** Activité de récupération facultative, enregistrée directement comme terminée. */
    public function recovery(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'activity' => ['required', 'string', 'max:60'],
            'duration_min' => ['required', 'integer', 'min:1', 'max:600'],
            'comment' => ['nullable', 'string', 'max:2000'],
            'performed_on' => ['required', 'date', 'before_or_equal:today'],
            'workout_id' => ['nullable', 'integer'],
        ]);
        $workout = $data['workout_id'] ? Workout::find($data['workout_id']) : null;
        $workout && $this->own($workout->program->user_id);

        $this->user()->sessions()->create([
            'workout_id' => $workout?->id, 'workout_name' => $workout?->name ?? 'Récupération', 'type' => WorkoutType::Recovery,
            'performed_on' => $data['performed_on'], 'duration_seconds' => $data['duration_min'] * 60,
            'status' => SessionStatus::Completed, 'perceived_effort' => 1, 'activity' => $data['activity'],
            'comment' => $data['comment'] ?? null, 'started_at' => now(), 'ended_at' => now(),
        ]);

        return back()->with('toast', 'Activité enregistrée');
    }

    private function currentOrFail(?WorkoutType $type = null): WorkoutSession
    {
        $session = SessionStarter::current($this->user());
        abort_unless($session && (! $type || $session->type === $type), 404);

        return $session;
    }
}
