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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique();
            
            // Relasi User, Outlet, Driver
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('outlet_id')->constrained('outlets')->cascadeOnDelete();
            $table->foreignId('driver_id')->nullable()->constrained('users')->nullOnDelete();
            
            // 1. Status Lengkap (Termasuk 'delivered')
            $table->enum('status', [
                'pending',
                'paid',
                'preparing',
                'ready',
                'on_delivery',
                'delivered',   // <-- Status: Driver sampai & upload bukti
                'completed',   // <-- Status: Validasi POS selesai
                'cancelled',
                'refund_needed',
                'refunded'
            ])->default('pending');

            // 2. Kolom Bukti Foto (Nullable karena awal order belum ada)
            $table->string('proof_of_delivery')->nullable(); 

            // Data Keuangan
            $table->decimal('subtotal', 12, 2);
            $table->decimal('tax', 12, 2);
            $table->decimal('delivery_fee', 12, 2)->default(0);
            $table->decimal('total_price', 12, 2);
            
            // Data Lokasi
            $table->decimal('distance_real', 10, 2)->nullable()->comment('Real distance from OpenRouteService');
            $table->text('delivery_address');
            $table->decimal('delivery_latitude', 10, 8)->nullable();
            $table->decimal('delivery_longitude', 11, 8)->nullable();
            
            // 3. Timestamps Lengkap
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('picked_up_at')->nullable(); // Waktu Driver Ambil Barang
            $table->timestamp('delivered_at')->nullable(); // Waktu Driver Sampai Lokasi
            $table->timestamp('completed_at')->nullable(); // Waktu Order Selesai Final
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};