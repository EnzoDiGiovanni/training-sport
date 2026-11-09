<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Paramètres du profil (bornes reprises du prototype, PROF_RULES). */
class SettingsRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:60'],
            'height_cm' => ['required', 'integer', 'between:100,230'],
            'weight' => ['required', 'numeric', 'between:30,250'],
            'target_weight_kg' => ['required', 'numeric', 'between:30,250'],
            'weekly_sessions_goal' => ['required', 'integer', 'between:0,14'],
            'weekly_km_goal' => ['required', 'numeric', 'between:0,300'],
            'goals' => ['array', 'max:10'],
            'goals.*' => ['nullable', 'string', 'max:200'],
            'steps_min' => ['required', 'integer', 'between:0,60000'],
            'steps_max' => ['required', 'integer', 'between:0,60000', 'gte:steps_min'],
            'run_level_kmh' => ['required', 'numeric', 'between:2,25'],
            'treadmill_max_kmh' => ['required', 'numeric', 'between:4,30'],
            'treadmill_incline_max' => ['required', 'numeric', 'between:0,40'],
            'dumbbell_max_kg' => ['required', 'numeric', 'between:1,100'],
            'dumbbell_count' => ['required', 'integer', 'between:0,10'],
            'weight_step_kg' => ['required', 'numeric', 'between:0.25,10'],
            'dumbbells_combinable' => ['boolean'],
            'has_bench' => ['boolean'],
            'bench_incline' => ['boolean'],
            'default_rest_seconds' => ['required', 'integer', 'between:0,600'],
        ];
    }

    public function messages(): array
    {
        return ['steps_max.gte' => 'Doit être supérieur au minimum.', 'name.required' => 'Prénom obligatoire.'];
    }
}
