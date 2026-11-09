<?php

namespace App\Http\Requests;

use App\Services\RunMetrics;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/** Données de course relevées sur le tapis (règle 6). */
class LiveRunRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'duration' => ['required', 'string'],
            'distance_km' => ['nullable'],
            'max_speed_kmh' => ['nullable', 'numeric', 'gt:0'],
            'incline_pct' => ['nullable', 'numeric'],
            'cycles_completed' => ['nullable', 'integer', 'min:0', 'max:50'],
            'rpe' => ['nullable', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return ['duration.required' => 'Indique la durée (ou utilise le chrono).'];
    }

    public function after(): array
    {
        return [function (Validator $v) {
            $seconds = RunMetrics::parseClock($this->input('duration'));
            if (! $seconds) {
                $v->errors()->add('duration', 'Format attendu : mm:ss, par exemple 30:00.');

                return;
            }
            RunValidation::check($v, $this->user()->profile, $seconds, $this->input('distance_km'), $this->input('max_speed_kmh'), $this->input('incline_pct'));
        }];
    }
}
