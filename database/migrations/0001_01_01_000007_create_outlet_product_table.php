<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
{
    if (!Schema::hasTable('outlet_product')) {
        Schema::create('outlet_product', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outlet_id')->constrained('outlets')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            
            // 1. Ubah 'custom_price' jadi 'price'
            $table->decimal('price', 12, 2)->nullable(); 
            
            $table->boolean('is_available')->default(true);
            $table->timestamps();
            
            $table->unique(['outlet_id', 'product_id']);
        });
    }
}

    public function down(): void
    {
        Schema::dropIfExists('outlet_product');
    }
};