<?php

namespace App\Enums;

enum WorkoutType: string
{
    case Run = 'run';
    case Strength = 'strength';
    case Recovery = 'recovery';
    case Rest = 'rest';
}
