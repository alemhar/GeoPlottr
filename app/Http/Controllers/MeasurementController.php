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
        // Initialize arrays to track changes
        $updated = [];
        $deleted = [];
        $inserted = [];
        
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
                    // Generate a default name based on date/time if not provided
                    $name = $new['name'] ?? 'Measurement ' . date('Y-m-d H:i:s');
                    
                    // Calculate area from geojson if available (using geoPHP or similar library would be better)
                    $area = null;
                    $geojson = json_decode($new['geojson'], true);
                    if (isset($geojson['properties']['area']) && is_numeric($geojson['properties']['area'])) {
                        $area = $geojson['properties']['area'];
                    }
                    
                    // Calculate center point for coordinates_summary
                    $coords_summary = null;
                    if (isset($geojson['geometry']['coordinates'][0]) && is_array($geojson['geometry']['coordinates'][0])) {
                        // Simple calculation of center (average of all points)
                        $lat_sum = 0;
                        $lng_sum = 0;
                        $points = 0;
                        
                        foreach ($geojson['geometry']['coordinates'][0] as $coord) {
                            if (is_array($coord) && count($coord) >= 2) {
                                $lng_sum += $coord[0];
                                $lat_sum += $coord[1];
                                $points++;
                            }
                        }
                        
                        if ($points > 0) {
                            $coords_summary = json_encode([
                                'center' => [
                                    'lat' => $lat_sum / $points,
                                    'lng' => $lng_sum / $points
                                ]
                            ]);
                        }
                    }
                    
                    $inserted[] = \App\Models\Measurement::create([
                        'name' => $name,
                        'geojson' => $new['geojson'],
                        'area' => $area,
                        'coordinates_summary' => $coords_summary,
                        'created_by' => auth()->id(),
                        // 'user_id' => auth()->id(),
                    ]);
                }
            }
        }
        // Return to the same page with success message
        return back()->with([
            'success' => true,
            'stats' => [
                'updated' => count($updated),
                'deleted' => count($deleted),
                'inserted' => count($inserted)
            ]
        ]);
    }
    //
}
