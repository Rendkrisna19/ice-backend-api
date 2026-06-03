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
        Schema::create('products', function (Blueprint $table) {
            $table->id();

            // ❌ HAPUS BAGIAN INI (outlet_id)
            // Karena kita pakai sistem Master Product (Global), relasinya dipindah ke tabel pivot 'outlet_product'
            // $table->foreignId('outlet_id')->constrained('outlets')->onDelete('cascade'); 

            $table->string('name');
            $table->string('slug')->unique(); // Slug harus unik secara global
            $table->text('description')->nullable();
            $table->decimal('price', 12, 2); // Harga dasar (Base Price)
            $table->string('image_url')->nullable();
            
            // Kita ubah jadi ENUM biar konsisten datanya
            $table->enum('category', ['makanan', 'minuman']); 

            // ❌ HAPUS BAGIAN INI (is_available)
            // Status ketersediaan diatur per-outlet di tabel pivot, bukan disini.
            // $table->boolean('is_available')->default(true);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};