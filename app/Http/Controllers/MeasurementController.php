<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Measurement;
use Inertia\Inertia;

class MeasurementController extends Controller
{
    public function index()
    {
        $measurements = Measurement::all();
        return Inertia::render('Dashboard', [
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
            info($validated['new']);
            foreach ($validated['new'] as $new) {
                // Only insert if there is no id (should not be a modification)
                if (empty($new['id']) && !empty($new['geojson'])) {
                    // Generate a default name based on date/time if not provided
                    $name = $new['name'] ?? 'Measurement ' . date('Y-m-d H:i:s');
                    
                    // Use area and perimeter from request if available
                    $area = null;
                    $perimeter = null;
                    
                    // Check for area directly in the new measurement data
                    if (isset($new['area']) && is_numeric($new['area'])) {
                        $area = $new['area'];
                        info("Found area in request: {$area}");
                    }
                    
                    // Check for perimeter directly in the new measurement data
                    if (isset($new['perimeter']) && is_numeric($new['perimeter'])) {
                        $perimeter = $new['perimeter'];
                        info("Found perimeter in request: {$perimeter}");
                    }
                    
                    // Handle geojson whether it's a JSON string or already an array
                    $geojson = $new['geojson'];
                    if (is_string($geojson)) {
                        $geojson = json_decode($geojson, true);
                    }
                    
                    // Fallback: check for area in geojson properties if not found directly
                    if ($area === null && isset($geojson['properties']['area']) && is_numeric($geojson['properties']['area'])) {
                        $area = $geojson['properties']['area'];
                    }
                    
                    // Calculate center point for coordinates_summary
                    $coords_summary = null;
                    
                    // Make sure we have a valid geojson structure
                    if (is_array($geojson) && isset($geojson['geometry']['coordinates'][0]) && is_array($geojson['geometry']['coordinates'][0])) {
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
                        'perimeter' => $perimeter, // Add perimeter to the fields being saved
                        'coordinates_summary' => $coords_summary,
                        'created_by' => auth()->id(),
                        // 'user_id' => auth()->id(),
                    ]);
                    
                    info("Created measurement with area: {$area}, perimeter: {$perimeter}"); // Debug log
                }
            }
        }
        // Return to the same page with success message
        return back()->with('success', 'Changes saved successfully');
    }
    
    /**
     * Display the specified measurement.
     *
     * @param  int  $id
     * @return \Inertia\Response
     */
    public function show($id)
    {
        $measurement = Measurement::findOrFail($id);
        
        // Check if the user has permission to view this measurement
        // This would be implemented based on your access control rules
        
        return Inertia::render('Api/MeasurementDetail', [
            'measurement' => $measurement
        ]);
    }
    
    /**
     * API endpoint to get a specific measurement.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function apiShow($id)
    {
        $measurement = Measurement::findOrFail($id);
        
        // Check if the user has permission to view this measurement
        // This would be implemented based on your access control rules
        
        return response()->json([
            'measurement' => $measurement
        ]);
    }
    
    /**
     * Update the specified measurement in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        $measurement = Measurement::findOrFail($id);
        
        // Validate the incoming request
        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:255',
            'category' => 'nullable|string|max:100',
            'tags' => 'nullable|string',
            'parcel_number' => 'nullable|string|max:100',
            'area' => 'nullable|numeric',
            'perimeter' => 'nullable|numeric',
            'zoning' => 'nullable|string|max:100',
            'estimated_value' => 'nullable|numeric',
            'slope' => 'nullable|numeric',
            'is_shared' => 'boolean',
            'access_level' => 'integer|min:0|max:2',
        ]);
        
        // Set last_modified_by to current user
        $validated['last_modified_by'] = Auth::id();
        
        // Update the measurement
        $measurement->update($validated);
        
        return response()->json([
            'success' => true,
            'measurement' => $measurement
        ]);
    }
    //
}
