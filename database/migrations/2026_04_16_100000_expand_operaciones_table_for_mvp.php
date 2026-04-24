<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('operaciones', function (Blueprint $table) {
            $table->string('estimated_filming_schedule')->nullable()->after('operation_date');
            $table->string('address')->nullable()->after('estimated_filming_schedule');
            $table->string('country')->nullable()->after('address');
            $table->string('city')->nullable()->after('country');
            $table->string('province')->nullable()->after('city');
            $table->string('postal_code', 20)->nullable()->after('province');
            $table->string('google_maps_link')->nullable()->after('postal_code');
            $table->decimal('altitude', 8, 2)->nullable()->after('google_maps_link');
            $table->decimal('operation_radius', 8, 2)->nullable()->after('altitude');
            $table->text('extra_information')->nullable()->after('operation_radius');
        });
    }

    public function down(): void
    {
        Schema::table('operaciones', function (Blueprint $table) {
            $table->dropColumn([
                'estimated_filming_schedule',
                'address',
                'country',
                'city',
                'province',
                'postal_code',
                'google_maps_link',
                'altitude',
                'operation_radius',
                'extra_information',
            ]);
        });
    }
};
