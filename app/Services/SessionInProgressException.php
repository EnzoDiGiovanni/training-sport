<?php

namespace App\Services;

use App\Models\WorkoutSession;
use RuntimeException;

class SessionInProgressException extends RuntimeException
{
    public function __construct(public readonly WorkoutSession $current)
    {
        parent::__construct('Une séance est déjà en cours.');
    }
}
