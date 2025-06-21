<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use Inertia\Inertia;

class MeasurementController extends Controller
{
    public function index()
    {
        $measurements = \App\Models\Measurement::all();
        return Inertia::render('Welcome', [
            'measurements' => $measurements,
        ]);
    }
    public function store(Request $request)
    {
        $validated = $request->validate([
            'new' => 'array',
            'modified' => 'array',
            'deleted' => 'array',
        ]);
        $inserted = [];
        // Delete
        if (!empty($validated['deleted'])) {
            \App\Models\Measurement::whereIn('id', $validated['deleted'])->delete();
        }
        // Update
        if (!empty($validated['modified'])) {
            foreach ($validated['modified'] as $mod) {
                if (!empty($mod['id']) && !empty($mod['geojson'])) {
                    $measurement = \App\Models\Measurement::find($mod['id']);
                    if ($measurement) {
                        $measurement->geojson = $mod['geojson'];
                        $measurement->save();
                    }
                }
            }
        }
        // Insert
        if (!empty($validated['new'])) {
            foreach ($validated['new'] as $new) {
                // Only insert if there is no id (should not be a modification)
                if (empty($new['id']) && !empty($new['geojson'])) {
                    $inserted[] = \App\Models\Measurement::create([
                        'geojson' => $new['geojson'],
                        // 'user_id' => auth()->id(),
                    ]);
                }
            }
        }
        // Return a proper Inertia response by redirecting back
        return \Inertia\Inertia::location(url()->previous());
    }
    //
}
