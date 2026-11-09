<?php

namespace App\Services;

use App\Enums\WorkoutType;
use App\Models\User;
use Carbon\CarbonInterface;

/** Statut de chaque jour de la semaine (règle 11) : done, today, upcoming, missed, rest, recovery. */
class WeekStatus
{
    /** @return list<array{day:int,date:string,status:string,workout_id:?int,session_id:?int}> */
    public static function for(User $user, CarbonInterface $monday): array
    {
        $program = $user->activeProgram()->with('days.workout')->first();
        $sessions = $user->sessions()->completed()
            ->whereBetween('performed_on', [$monday->toDateString(), $monday->copy()->addDays(6)->toDateString()])
            ->get();
        $today = today();

        $out = [];
        for ($d = 1; $d <= 7; $d++) {
            $date = $monday->copy()->addDays($d - 1);
            $w = $program?->days->firstWhere('day_of_week', $d)?->workout;
            $w = $w && ! $w->archived_at ? $w : null;
            $type = $w?->type ?? WorkoutType::Rest;
            $done = $sessions->first(fn ($s) => $s->performed_on->isSameDay($date)
                && ($s->type === $type || ($w && $s->workout_id === $w->id)));

            $status = match (true) {
                $type === WorkoutType::Rest => 'rest',
                (bool) $done => 'done',
                $type === WorkoutType::Recovery => $date->isSameDay($today) ? 'today' : 'recovery',
                $date->lt($today) => 'missed',
                $date->isSameDay($today) => 'today',
                default => 'upcoming',
            };
            $out[] = ['day' => $d, 'date' => $date->toDateString(), 'status' => $status,
                'workout_id' => $w?->id, 'session_id' => $done?->id];
        }

        return $out;
    }
}
