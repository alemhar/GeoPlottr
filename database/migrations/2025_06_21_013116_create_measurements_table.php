<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('measurements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('name')->nullable(); // Making nullable to allow saving without a name
            $table->string('description')->nullable(); // General description of the shape
            $table->decimal('area', 10, 2)->nullable();
            
            // Categorization & Organization
            $table->string('category')->nullable(); // e.g., Residential, Agricultural, Commercial
            $table->string('tags')->nullable(); // Comma-separated tags for filtering/searching
            
            // Enhanced Property Information
            $table->string('parcel_number')->nullable(); // Official property identifier
            $table->decimal('perimeter', 10, 2)->nullable(); // Calculated boundary length
            $table->json('coordinates_summary')->nullable(); // Store center point or boundary box
            
            // Collaboration & Ownership
            $table->unsignedBigInteger('created_by')->nullable(); // Creator of measurement
            $table->unsignedBigInteger('last_modified_by')->nullable(); // Last person to modify
            $table->boolean('is_shared')->default(false); // Allow team sharing
            $table->tinyInteger('access_level')->default(0); // 0=private, 1=team, 2=public
            
            // Analysis & Professional Use
            $table->decimal('estimated_value', 12, 2)->nullable(); // Property valuation
            $table->string('zoning')->nullable(); // Land use zoning information
            $table->json('soil_data')->nullable(); // Soil types or quality information
            $table->decimal('slope', 5, 2)->nullable(); // Average terrain slope
            
            $table->json('geojson');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('measurements');
    }
};
