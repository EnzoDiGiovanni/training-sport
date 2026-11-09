<?php

namespace App\Http\Controllers;

use App\Http\Presenters\Present;
use App\Http\Requests\SessionUpdateRequest;
use App\Models\ExerciseSet;
use App\Models\WorkoutSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SessionController extends Controller
{
    public function index(): Response
    {
        $user = $this->user();

        return Inertia::render('History/Index', [
            'sessions' => Present::history($user),
            'exercises' => ExerciseController::library($user),
            'profile' => Present::profile($user),
            'today' => today()->toDateString(),
        ]);
    }

    public function update(SessionUpdateRequest $request, WorkoutSession $session): RedirectResponse
    {
        $this->own($session->user_id);
        DB::transaction(function () use ($request, $session) {
            $session->update([
                'performed_on' => $request->date('date'), 'duration_seconds' => $request->seconds(),
                'perceived_effort' => $request->input('rpe'), 'comment' => $request->input('comment'),
                'activity' => $request->input('activity', $session->activity),
            ]);
            if ($session->run) {
                $session->run->update($request->only('distance_km', 'max_speed_kmh', 'incline_pct'));
            }
            $ids = $session->exercises()->pluck('id');
            foreach ($request->input('sets', []) as $s) {
                ExerciseSet::whereIn('session_exercise_id', $ids)->whereKey($s['id'])
                    ->update(['weight_kg' => $s['weight_kg'], 'reps' => $s['reps']]);
            }
        });

        return back()->with('toast', 'Séance mise à jour');
    }

    public function destroy(WorkoutSession $session): RedirectResponse
    {
        $this->own($session->user_id);
        $session->delete();

        return back()->with('toast', 'Séance supprimée');
    }
}
