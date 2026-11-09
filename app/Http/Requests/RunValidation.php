<?php

namespace App\Http\Requests;

use App\Models\UserProfile;
use App\Services\RunMetrics;
use Illuminate\Validation\Validator;

/** Contrôles d'une course (règle 6), partagés entre la saisie en direct et la modification depuis l'historique. */
class RunValidation
{
    public static function check(Validator $v, UserProfile $p, int $seconds, mixed $distance, mixed $max, mixed $incline): void
    {
        $tm = $p->treadmill_max_kmh;
        $fmt = fn (float $x) => number_format($x, 1, ',', '');
        if (! is_numeric($distance) || $distance <= 0) {
            $v->errors()->add('distance_km', 'Indique la distance affichée par le tapis.');

            return;
        }
        $avg = RunMetrics::averageSpeed((float) $distance, $seconds);
        if ($avg > $tm + 0.05) {
            $v->errors()->add('distance_km', "Vitesse moyenne de {$fmt($avg)} km/h, supérieure au maximum du tapis ({$fmt($tm)} km/h). Vérifie la distance ou la durée.");
        }
        if (is_numeric($max)) {
            if ($max > $tm) {
                $v->errors()->add('max_speed_kmh', "Ton tapis est limité à {$fmt($tm)} km/h.");
            } elseif ($max < $avg - 0.05) {
                $v->errors()->add('max_speed_kmh', "La vitesse max ne peut pas être inférieure à la moyenne ({$fmt($avg)}).");
            }
        }
        if (is_numeric($incline) && ($incline < 0 || $incline > $p->treadmill_incline_max)) {
            $v->errors()->add('incline_pct', "Entre 0 et {$fmt($p->treadmill_incline_max)} %.");
        }
    }
}
