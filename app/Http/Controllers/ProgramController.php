<?php

namespace App\Http\Controllers;

use App\Http\Presenters\Present;
use App\Http\Requests\WorkoutRequest;
use App\Models\TrainingProgram;
use App\Models\Workout;
use App\Services\RunMetrics;
use App\Services\WorkoutWriter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ProgramController extends Controller
{
    public function index(): Response
    {
        $user = $this->user();

        return Inertia::render('Program/Index', [
            'workouts' => Present::workouts($user),
            'schedule' => Present::schedule($user),
            'exercises' => ExerciseController::library($user),
            'profile' => Present::profile($user),
            'sessionsCount' => $user->sessions()->completed()->count(),
        ]);
    }

    public function schedule(Request $request, int $day): RedirectResponse
    {
        abort_unless($day >= 1 && $day <= 7, 404);
        $program = $this->program();
        $data = $request->validate(['workout_id' => ['nullable', 'integer']]);
        $id = $data['workout_id'] ?? null;
        abort_if($id && ! $program->workouts()->active()->whereKey($id)->exists(), 422);
        $program->days()->updateOrCreate(['day_of_week' => $day], ['workout_id' => $id]);

        return back()->with('toast', ['', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'][$day].' mis à jour');
    }

    public function create(Request $request): Response
    {
        $type = in_array($request->query('type'), ['strength', 'run', 'recovery'], true) ? $request->query('type') : 'strength';
        $draft = [
            'id' => null, 'type' => $type, 'objective' => '', 'days' => [], 'exercises' => [], 'segments' => [],
            'name' => ['strength' => 'Musculation C', 'run' => 'Nouvelle course', 'recovery' => 'Mobilité'][$type],
            'estimated_duration_min' => $type === 'strength' ? 40 : 20,
        ];
        if ($type === 'run') {
            $draft['segments'] = [
                ['kind' => 'warmup', 'label' => 'Échauffement', 'duration_min' => 5, 'speed_min_kmh' => 6, 'speed_max_kmh' => 6, 'incline_pct' => 0],
                ['kind' => 'run', 'label' => 'Course', 'duration_min' => 20, 'speed_min_kmh' => 8, 'speed_max_kmh' => 8.5, 'incline_pct' => 1],
                ['kind' => 'cooldown', 'label' => 'Retour au calme', 'duration_min' => 5, 'speed_min_kmh' => 5, 'speed_max_kmh' => 5, 'incline_pct' => 0],
            ];
        }

        return $this->editor($draft);
    }

    public function edit(Workout $workout): Response
    {
        $this->ownWorkout($workout);

        return $this->editor(Present::workout($workout));
    }

    private function editor(array $draft): Response
    {
        return Inertia::render('Program/Edit', [
            'draft' => $draft,
            'exercises' => ExerciseController::library($this->user()),
            'profile' => Present::profile($this->user()),
        ]);
    }

    public function store(WorkoutRequest $request): RedirectResponse
    {
        $program = $this->program();
        DB::transaction(function () use ($request, $program) {
            $workout = $program->workouts()->create($this->attributes($request) + ['position' => $program->workouts()->max('position') + 1]);
            $this->save($request, $workout);
        });

        return redirect()->route('program.index')->with('toast', 'Séance créée');
    }

    public function update(WorkoutRequest $request, Workout $workout): RedirectResponse
    {
        $this->ownWorkout($workout);
        DB::transaction(function () use ($request, $workout) {
            $workout->update($this->attributes($request));
            $this->save($request, $workout);
        });

        return redirect()->route('program.index')->with('toast', 'Programme mis à jour');
    }

    public function duplicate(Workout $workout): RedirectResponse
    {
        $this->ownWorkout($workout);
        DB::transaction(function () use ($workout) {
            $copy = $workout->replicate(['archived_at'])->fill(['name' => $workout->name.' (copie)', 'position' => $workout->position]);
            $copy->save();
            WorkoutWriter::sync($copy, $workout->exercises->toArray(), RunMetrics::tree($workout));
        });

        return back()->with('toast', 'Séance dupliquée, non planifiée');
    }

    /** Archivée si elle a un historique, supprimée sinon ; ses jours passent en repos. */
    public function destroy(Workout $workout): RedirectResponse
    {
        $this->ownWorkout($workout);
        DB::transaction(function () use ($workout) {
            $workout->days()->update(['workout_id' => null]);
            $workout->sessions()->exists() ? $workout->update(['archived_at' => now()]) : $workout->delete();
        });

        return back()->with('toast', 'Séance supprimée du programme');
    }

    private function attributes(WorkoutRequest $request): array
    {
        $data = $request->only('name', 'type', 'objective');
        $data['estimated_duration_min'] = $request->input('type') === 'run'
            ? (int) round(RunMetrics::plannedMinutes($request->segmentTree()))
            : $request->input('estimated_duration_min');

        return $data;
    }

    private function save(WorkoutRequest $request, Workout $workout): void
    {
        $type = $request->input('type');
        WorkoutWriter::sync(
            $workout,
            $type === 'strength' ? $request->input('exercises', []) : [],
            $type === 'run' ? $request->segmentTree() : [],
        );
        $days = array_map('intval', $request->input('days', []));
        $program = $workout->program;
        $program->days()->where('workout_id', $workout->id)->whereNotIn('day_of_week', $days)->update(['workout_id' => null]);
        foreach ($days as $d) {
            $program->days()->updateOrCreate(['day_of_week' => $d], ['workout_id' => $workout->id]);
        }
    }

    private function program(): TrainingProgram
    {
        return $this->user()->activeProgram ?? $this->user()->programs()->create(['name' => 'Mon programme', 'is_active' => true]);
    }

    private function ownWorkout(Workout $workout): void
    {
        $this->own($workout->program->user_id);
        abort_if($workout->archived_at !== null, 404);
    }
}
