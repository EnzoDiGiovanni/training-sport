<?php

namespace App\Http\Middleware;

use App\Services\SessionStarter;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function share(Request $request): array
    {
        $user = $request->user();
        $live = $user ? SessionStarter::current($user) : null;

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => ['user' => $user?->only('id', 'name', 'email')],
            'profile' => $user?->profile?->only('height_cm'),
            // Pastille « Séance en cours » visible partout.
            'live' => $live ? [
                'id' => $live->id, 'name' => $live->workout_name, 'type' => $live->type,
                'started_at' => $live->started_at?->toIso8601String(),
            ] : null,
            'flash' => [
                'toast' => fn () => $request->session()->get('toast'),
                'created_exercise_id' => fn () => $request->session()->get('created_exercise_id'),
            ],
        ];
    }
}
