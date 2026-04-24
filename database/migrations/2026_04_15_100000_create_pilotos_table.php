<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pilotos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained()->cascadeOnDelete();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('second_last_name')->nullable();
            $table->string('dni_nie');
            $table->date('birth_date');
            $table->string('pilot_identification_number');
            $table->string('maximum_pilot_certification');
            $table->string('address');
            $table->string('country');
            $table->string('city');
            $table->string('province');
            $table->string('postal_code', 20);
            $table->string('phone', 30);
            $table->boolean('has_radiofonista_certificate')->default(false);
            $table->string('radiofonista_certificate_path')->nullable();
            $table->string('theoretical_certificate_level');
            $table->string('dni_front_path')->nullable();
            $table->string('dni_back_path')->nullable();
            $table->string('theoretical_certificate_path')->nullable();
            $table->string('practical_certificate_path')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pilotos');
    }
};
