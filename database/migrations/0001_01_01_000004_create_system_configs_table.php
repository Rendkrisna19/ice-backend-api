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
        Schema::create('system_configs', function (Blueprint $table) {
            $table->id();
            $table->decimal('delivery_base_price', 12, 2)->default(5000)->comment('Base delivery price');
            $table->decimal('delivery_base_distance', 8, 2)->default(1)->comment('Base distance in KM');
            $table->decimal('delivery_price_per_km', 12, 2)->default(2000)->comment('Price per KM after base distance');
            $table->decimal('tax_percentage', 5, 2)->default(10)->comment('Tax percentage');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_configs');
    }
};
