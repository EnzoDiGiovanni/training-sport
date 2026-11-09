<?php

namespace App\Http\Controllers;

use App\Http\Presenters\Present;
use App\Models\Workout;
use App\Services\SessionStarter;
use Inertia\Inertia;
use Inertia\Response;

class WorkoutController extends Controller
{
    /** Détail d'une séance type avec « Dernière fois ». */
    public function show(Workout $workout): Response
    {
        $user = $this->user();
        $this->own($workout->program->user_id);
        abort_if($workout->archived_at !== null, 404);
        $last = $workout->sessions()->completed()->latest('performed_on')->latest('id')->first();

        return Inertia::render('Workouts/Show', [
            'workout' => Present::workout($workout),
            'exercises' => ExerciseController::library($user),
            'profile' => Present::profile($user),
            'last' => $last ? Present::session($last) : null,
            'lastPerf' => $workout->exercises->mapWithKeys(fn ($we) => [
                $we->exercise_id => ($p = SessionStarter::lastPerformance($user, $we->exercise_id)) ? Present::item($p) + ['date' => $p->session->performed_on->toDateString()] : null,
            ]),
        ]);
    }
}
