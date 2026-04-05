<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Primer bloque de drones del MVP.
     * Guardamos toda la ficha del dron en una sola tabla para avanzar rapido.
     */
    public function up(): void
    {
        Schema::create('drones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $table->string('uas_class');
            $table->string('manufacturer_name');
            $table->string('model');
            $table->string('controller_serial_number');
            $table->string('registration_number');
            $table->decimal('mtom_weight', 8, 2);
            $table->string('remote_id_number');
            $table->string('class_marking');
            $table->string('band_frequency');
            $table->string('color');
            $table->string('payload')->nullable();
            $table->string('vhf_equipment')->nullable();
            $table->string('emergency_equipment')->nullable();
            $table->string('insurance_policy_number');
            $table->date('insurance_valid_until');
            $table->string('insurance_company_name');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drones');
    }
};
