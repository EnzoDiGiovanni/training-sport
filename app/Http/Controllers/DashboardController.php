<?php

namespace App\Http\Controllers;

use App\Http\Presenters\Present;
use App\Services\StatsService;
use App\Services\WeekStatus;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /** Séance du jour déterminée par la semaine type du programme actif (règle 2). */
    public function __invoke(): Response
    {
        $user = $this->user();
        $monday = today()->startOfWeek();
        $weight = StatsService::weight($user);

        return Inertia::render('Dashboard', [
            'today' => today()->toDateString(),
            'week' => WeekStatus::for($user, $monday),
            'workouts' => Present::workouts($user),
            'exercises' => ExerciseController::library($user),
            'profile' => Present::profile($user),
            'stats' => [
                'week' => StatsService::week($user, $monday),
                'prev' => StatsService::week($user, $monday->copy()->subWeek()),
                'weights' => array_slice(array_column($weight['list'], 'kg'), -12),
                'delta30' => $weight['delta30'],
            ],
            'recent' => Present::history($user, 3),
            'weekSessions' => $user->sessions()->completed()->where('performed_on', '>=', $monday->toDateString())->get()
                ->map(fn ($s) => Present::session($s))->all(),
        ]);
    }
}
