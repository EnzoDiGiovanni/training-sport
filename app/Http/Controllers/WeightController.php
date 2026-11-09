<?php

namespace App\Http\Controllers;

use App\Models\WeightRecord;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class WeightController extends Controller
{
    /** Une pesée le même jour remplace la précédente. */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'measured_on' => ['required', 'date', 'before_or_equal:today'],
            'weight_kg' => ['required', 'numeric', 'between:30,250'],
        ], ['weight_kg.between' => 'Entre 30 et 250 kg.']);
        $this->user()->weights()->updateOrCreate(['measured_on' => $data['measured_on']], ['weight_kg' => round($data['weight_kg'], 1)]);

        return back()->with('toast', 'Pesée enregistrée');
    }

    public function destroy(WeightRecord $weight): RedirectResponse
    {
        $this->own($weight->user_id);
        $weight->delete();

        return back()->with('toast', 'Pesée supprimée');
    }
}
