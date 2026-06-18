<?php
// database/migrations/xxxx_update_vehicles_and_devices_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tambah brand & model ke vehicles jika belum ada
        Schema::table('vehicles', function (Blueprint $table) {
            if (!Schema::hasColumn('vehicles', 'brand')) {
                $table->string('brand', 50)->nullable()->after('vehicle_type');
            }
            if (!Schema::hasColumn('vehicles', 'model')) {
                $table->string('model', 50)->nullable()->after('brand');
            }
            if (!Schema::hasColumn('vehicles', 'year')) {
                $table->smallInteger('year')->nullable()->after('model');
            }
        });

        // Ubah device_type di iot_devices — hapus sim7600/openmv, jadi 1 tipe saja
        Schema::table('iot_devices', function (Blueprint $table) {
            $table->string('device_type', 30)
                  ->default('tracker')
                  ->change();
        });
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropColumn(['brand', 'model', 'year']);
        });
    }
};