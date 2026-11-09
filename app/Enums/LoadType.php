<?php

namespace App\Enums;

enum LoadType: string
{
    case TwoDumbbells = 'two_dumbbells';
    case OneDumbbell = 'one_dumbbell';
    case Bodyweight = 'bodyweight';

    /** Nombre d'haltères comptés dans le volume (poids du corps exclu). */
    public function factor(): int
    {
        return match ($this) {
            self::TwoDumbbells => 2,
            self::OneDumbbell => 1,
            self::Bodyweight => 0,
        };
    }
}
