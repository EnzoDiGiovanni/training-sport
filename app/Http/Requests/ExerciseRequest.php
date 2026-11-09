<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Création et modification d'un exercice personnalisé (multipart, image facultative). */
class ExerciseRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Seuls les exercices personnels de l'utilisateur sont modifiables.
        $exercise = $this->route('exercise');
        abort_if($exercise && $exercise->user_id !== $this->user()->id, 404);

        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'primary_muscle' => ['required', 'string', 'max:120'],
            'secondary_muscles' => ['nullable', 'string', 'max:255'],
            'muscle_group' => ['required', 'in:chest,back,legs,shoulders,arms,core'],
            'equipment' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'instructions' => ['nullable', 'array', 'max:20'],
            'instructions.*' => ['nullable', 'string', 'max:300'],
            'common_mistakes' => ['nullable', 'array', 'max:20'],
            'common_mistakes.*' => ['nullable', 'string', 'max:300'],
            'measurement' => ['required', 'in:reps,time'],
            'load_type' => ['required', 'in:two_dumbbells,one_dumbbell,bodyweight'],
            'is_unilateral' => ['boolean'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_image' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return ['name.required' => 'Nom obligatoire.', 'primary_muscle.required' => 'Indique le groupe musculaire.'];
    }
}
