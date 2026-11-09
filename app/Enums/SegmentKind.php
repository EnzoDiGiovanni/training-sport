<?php

namespace App\Enums;

enum SegmentKind: string
{
    case Warmup = 'warmup';
    case Run = 'run';
    case Fast = 'fast';
    case Recovery = 'recovery';
    case Cooldown = 'cooldown';
    case Repeat = 'repeat';
}
