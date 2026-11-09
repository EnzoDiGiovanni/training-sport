<?php

namespace App\Http\Controllers;

use App\Http\Requests\ExerciseRequest;
use App\Models\Exercise;
use App\Services\StatsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ExerciseController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Exercises/Index', [
            'exercises' => self::library($this->user()),
        ]);
    }

    /** Bibliothèque visible (hors archivés), triée par nom. */
    public static function library($user): array
    {
        return Exercise::visibleTo($user)->whereNull('archived_at')->orderBy('is_custom')->orderBy('id')->get()->all();
    }

    public function store(ExerciseRequest $request): RedirectResponse
    {
        $data = $this->payload($request);
        $exercise = Exercise::create($data + [
            'user_id' => $this->user()->id, 'is_custom' => true, 'illustration_key' => null,
            'slug' => Str::slug($data['name']).'-'.Str::lower(Str::random(5)),
        ]);
        $this->storeImage($request, $exercise);

        return back()->with('toast', 'Exercice enregistré')->with('created_exercise_id', $exercise->id);
    }

    public function update(ExerciseRequest $request, Exercise $exercise): RedirectResponse
    {
        $this->own($exercise->user_id);
        $exercise->update($this->payload($request));
        if ($request->boolean('remove_image') && $exercise->image_path) {
            Storage::disk('public')->delete($exercise->image_path);
            $exercise->update(['image_path' => null]);
        }
        $this->storeImage($request, $exercise);

        return back()->with('toast', 'Exercice enregistré');
    }

    /** Refusé si utilisé dans le programme ; archivé s'il a un historique ; supprimé sinon. */
    public function destroy(Exercise $exercise): RedirectResponse
    {
        $this->own($exercise->user_id);
        if ($exercise->workoutExercises()->whereHas('workout', fn ($q) => $q->whereNull('archived_at'))->exists()) {
            return back()->withErrors(['exercise' => 'Exercice utilisé dans le programme : retire-le des séances avant de le supprimer.']);
        }
        if ($exercise->sessionExercises()->exists() || $exercise->workoutExercises()->exists()) {
            $exercise->update(['archived_at' => now()]);

            return back()->with('toast', 'Exercice archivé (son historique est conservé)');
        }
        if ($exercise->image_path) {
            Storage::disk('public')->delete($exercise->image_path);
        }
        $exercise->delete();

        return back()->with('toast', 'Exercice supprimé');
    }

    public function history(Exercise $exercise): JsonResponse
    {
        abort_unless($exercise->user_id === null || $exercise->user_id === $this->user()->id, 404);

        return response()->json(StatsService::exerciseHistory($this->user(), $exercise->id));
    }

    private function payload(ExerciseRequest $request): array
    {
        $data = $request->safe()->except(['image', 'remove_image']);
        $data['is_unilateral'] = $request->boolean('is_unilateral');
        foreach (['instructions', 'common_mistakes'] as $k) {
            $data[$k] = array_values(array_filter(array_map(fn ($x) => trim((string) $x), $data[$k] ?? [])));
        }

        return $data;
    }

    private function storeImage(ExerciseRequest $request, Exercise $exercise): void
    {
        if (! $request->hasFile('image')) {
            return;
        }
        if ($exercise->image_path) {
            Storage::disk('public')->delete($exercise->image_path);
        }
        $exercise->update(['image_path' => $request->file('image')->store('exercises', 'public')]);
    }
}
