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
        Schema::table('users', function (Blueprint $table) {
            // 1. Phone (Sesuai request: 'phone' bukan 'phone_number')
            if (!Schema::hasColumn('users', 'phone')) {
                $table->string('phone')->nullable()->after('email');
            }

            // 2. Plate Number (Driver Info)
            if (!Schema::hasColumn('users', 'plate_number')) {
                $table->string('plate_number')->nullable()->after('phone');
            }

            // 3. Vehicle Type (Motor/Mobil)
            if (!Schema::hasColumn('users', 'vehicle_type')) {
                $table->string('vehicle_type')->nullable()->after('plate_number');
            }

            // 4. Wallet Balance (Penting untuk Driver App)
            if (!Schema::hasColumn('users', 'wallet_balance')) {
                $table->decimal('wallet_balance', 15, 2)->default(0)->after('vehicle_type');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['phone', 'plate_number', 'vehicle_type', 'wallet_balance']);
        });
    }
};