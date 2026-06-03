<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('products', function (Blueprint $table) {
            // Tambahkan kolom outlet_id dan is_available
            $table->unsignedBigInteger('outlet_id')->nullable()->after('id');
            $table->boolean('is_available')->default(true)->after('description');

            // Opsional: Buat relasi foreign key jika tabel outlets ada
            // $table->foreign('outlet_id')->references('id')->on('outlets')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['outlet_id', 'is_available']);
        });
    }
};