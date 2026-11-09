<?php

namespace App\Http\Requests;

use App\Services\RunMetrics;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/** Modification d'une séance réalisée depuis l'historique. */
class SessionUpdateRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'date' => ['required', 'date', 'before_or_equal:today'],
            'duration' => ['required', 'string'],
            'rpe' => ['nullable', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:2000'],
            'activity' => ['nullable', 'string', 'max:60'],
            'distance_km' => ['nullable', 'numeric', 'gt:0', 'max:100'],
            'max_speed_kmh' => ['nullable', 'numeric', 'gt:0'],
            'incline_pct' => ['nullable', 'numeric', 'min:0'],
            'sets' => ['array'],
            'sets.*.id' => ['required', 'integer'],
            'sets.*.weight_kg' => ['required', 'numeric', 'min:0', 'max:500'],
            'sets.*.reps' => ['required', 'integer', 'min:0', 'max:1000'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $v) {
            $seconds = RunMetrics::parseClock($this->input('duration'));
            if (! $seconds) {
                $v->errors()->add('duration', 'Durée invalide (mm:ss ou h:mm:ss).');

                return;
            }
            if ($this->route('session')->type->value === 'run') {
                RunValidation::check($v, $this->user()->profile, $seconds, $this->input('distance_km'), $this->input('max_speed_kmh'), $this->input('incline_pct'));
            }
        }];
    }

    public function seconds(): int
    {
        return RunMetrics::parseClock($this->input('duration'));
    }
}
