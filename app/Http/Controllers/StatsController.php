<?php

namespace App\Http\Controllers;

use App\Enums\WorkoutType;
use App\Http\Presenters\Present;
use App\Models\Exercise;
use App\Models\SessionExercise;
use App\Services\StatsService;
use Inertia\Inertia;
use Inertia\Response;

class StatsController extends Controller
{
    public function __invoke(): Response
    {
        $user = $this->user();
        $monday = today()->startOfWeek();
        $weeks = collect(range(7, 0))->map(function ($i) use ($user, $monday) {
            $m = $monday->copy()->subWeeks($i);

            return ['monday' => $m->toDateString(), 'cur' => $i === 0] + StatsService::week($user, $m);
        });

        $runs = $user->sessions()->completed()->where('type', WorkoutType::Run)->with('run')->whereHas('run')
            ->orderBy('performed_on')->orderBy('id')->get()->map(fn ($s) => Present::session($s));

        $usedIds = SessionExercise::whereIn('workout_session_id', $user->sessions()->completed()->select('id'))
            ->whereNotNull('exercise_id')->distinct()->pluck('exercise_id');
        $used = Exercise::whereIn('id', $usedIds)->orderBy('id')->get();
        $schedule = Present::schedule($user);
        $workouts = collect(Present::workouts($user));

        return Inertia::render('Stats/Index', [
            'weeks' => $weeks,
            'runs' => $runs,
            'runWorkouts' => $runs->unique('workout_id')->filter(fn ($s) => $s['workout_id'])->map(fn ($s) => ['id' => $s['workout_id'], 'name' => $s['name']])->values(),
            'exercises' => $used,
            'histories' => $used->mapWithKeys(fn ($e) => [$e->id => StatsService::exerciseHistory($user, $e->id)]),
            'strengthGoal' => collect($schedule)->filter(fn ($id) => ($workouts->firstWhere('id', $id)['type'] ?? null) === 'strength')->count(),
            'weight' => StatsService::weight($user),
            'profile' => Present::profile($user),
            'today' => today()->toDateString(),
        ]);
    }
}
