<?php

namespace App\Http\Requests;

use App\Models\Exercise;
use App\Services\RunMetrics;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/** Création / modification d'une séance type (règle 8). */
class WorkoutRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'type' => ['required', 'in:strength,run,recovery'],
            'objective' => ['nullable', 'string', 'max:1000'],
            'estimated_duration_min' => ['nullable', 'integer', 'min:1', 'max:600'],
            'days' => ['array'],
            'days.*' => ['integer', 'between:1,7'],
            'exercises' => ['array'],
            'exercises.*.exercise_id' => ['required', 'integer'],
            'exercises.*.sets' => ['required', 'integer', 'min:1', 'max:20'],
            'exercises.*.reps_min' => ['required', 'integer', 'min:1', 'max:600'],
            'exercises.*.reps_max' => ['required', 'integer', 'min:1', 'max:600'],
            'exercises.*.target_weight_kg' => ['nullable', 'numeric', 'min:0', 'max:500'],
            'exercises.*.rest_seconds' => ['required', 'integer', 'min:0', 'max:900'],
            'segments' => ['array'],
        ];
    }

    public function messages(): array
    {
        return ['name.required' => 'Donne un nom à la séance.'];
    }

    public function after(): array
    {
        return [function (Validator $v) {
            $profile = $this->user()->profile;
            if ($this->input('type') === 'strength') {
                $ex = $this->input('exercises', []);
                $ids = array_column($ex, 'exercise_id');
                $known = Exercise::visibleTo($this->user())->whereIn('id', $ids)->count();
                if (! $ex) {
                    $v->errors()->add('exercises', 'Ajoute au moins un exercice.');
                } elseif ($known !== count(array_unique($ids))) {
                    $v->errors()->add('exercises', 'Exercice inconnu.');
                } elseif (collect($ex)->contains(fn ($e) => (int) ($e['reps_min'] ?? 0) > (int) ($e['reps_max'] ?? 0))) {
                    $v->errors()->add('exercises', 'Vérifie les séries et répétitions (valeurs positives, min ≤ max).');
                }
            }
            if ($this->input('type') === 'run') {
                $tree = $this->input('segments', []);
                $steps = self::steps($tree);
                if (! $steps) {
                    $v->errors()->add('segments', 'Ajoute au moins une phase.');
                } elseif (collect($steps)->contains(fn ($s) => ! is_numeric($s['duration_min'] ?? null) || $s['duration_min'] <= 0
                    || ! is_numeric($s['speed_min_kmh'] ?? null) || $s['speed_min_kmh'] <= 0
                    || ! is_numeric($s['speed_max_kmh'] ?? null) || $s['speed_min_kmh'] > $s['speed_max_kmh']
                    || ! in_array($s['kind'] ?? null, ['warmup', 'run', 'fast', 'recovery', 'cooldown'], true)
                    || (float) ($s['incline_pct'] ?? 0) < 0 || (float) ($s['incline_pct'] ?? 0) > $profile->treadmill_incline_max)) {
                    $v->errors()->add('segments', 'Chaque phase doit avoir une durée et une vitesse valides (min ≤ max) et une inclinaison possible.');
                } elseif (RunMetrics::maxSpeed($steps) > $profile->treadmill_max_kmh) {
                    $v->errors()->add('segments', "Une vitesse dépasse le maximum de ton tapis ({$profile->treadmill_max_kmh} km/h).");
                }
            }
        }];
    }

    /** Étapes à plat en validant la forme des blocs répétés (1 à 20 cycles, au moins une phase). */
    private static function steps(array $tree): array
    {
        $out = [];
        foreach ($tree as $s) {
            if (($s['kind'] ?? null) === 'repeat') {
                $n = (int) ($s['repeat_count'] ?? 0);
                $children = $s['children'] ?? [];
                if ($n < 1 || $n > 20 || ! $children || collect($children)->contains(fn ($c) => ($c['kind'] ?? null) === 'repeat')) {
                    return [['duration_min' => 0]];
                }
                array_push($out, ...$children);
            } else {
                $out[] = $s;
            }
        }

        return $out;
    }

    /** Arbre nettoyé (seuls les champs attendus). */
    public function segmentTree(): array
    {
        $clean = fn ($s) => [
            'kind' => $s['kind'], 'label' => trim((string) ($s['label'] ?? '')) ?: 'Phase',
            'duration_min' => (float) $s['duration_min'], 'speed_min_kmh' => (float) $s['speed_min_kmh'],
            'speed_max_kmh' => (float) $s['speed_max_kmh'], 'incline_pct' => (float) ($s['incline_pct'] ?? 0),
        ];

        return array_map(fn ($s) => $s['kind'] === 'repeat'
            ? ['kind' => 'repeat', 'repeat_count' => (int) $s['repeat_count'], 'children' => array_map($clean, $s['children'])]
            : $clean($s), $this->input('segments', []));
    }
}
