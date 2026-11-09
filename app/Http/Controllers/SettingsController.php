<?php

namespace App\Http\Controllers;

use App\Http\Presenters\Present;
use App\Http\Requests\SettingsRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('Settings/Edit', ['profile' => Present::profile($this->user())]);
    }

    public function update(SettingsRequest $request): RedirectResponse
    {
        $user = $this->user();
        DB::transaction(function () use ($request, $user) {
            $user->update(['name' => $request->input('name')]);
            $user->profile->update($request->safe()->except('name', 'weight', 'goals') + [
                'goals' => array_values(array_filter(array_map(fn ($g) => trim((string) $g), $request->input('goals', [])))),
                'dumbbells_combinable' => $request->boolean('dumbbells_combinable'),
                'has_bench' => $request->boolean('has_bench'),
                'bench_incline' => $request->boolean('bench_incline'),
            ]);
            // Modifier le poids crée (ou remplace) la pesée du jour.
            $weight = round((float) $request->input('weight'), 1);
            if ($weight !== $user->currentWeight()) {
                $user->weights()->updateOrCreate(['measured_on' => today()->toDateString()], ['weight_kg' => $weight]);
            }
        });

        return back()->with('toast', 'Paramètres enregistrés');
    }
}
