<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Guardamos la ficha real del cliente en la propia tabla clientes
     * para mantener el MVP simple y evitar tablas extra por ahora.
     */
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->string('last_name')->nullable()->after('name');
            $table->string('dni')->nullable()->after('phone');
            $table->string('operator_registration_number')->nullable()->after('address');
            $table->date('birth_date')->nullable()->after('operator_registration_number');
            $table->string('pilot_identification_number')->nullable()->after('birth_date');
            $table->string('pilot_certificate')->nullable()->after('pilot_identification_number');
            $table->string('operator_certification')->nullable()->after('pilot_certificate');
        });
    }

    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropColumn([
                'last_name',
                'dni',
                'operator_registration_number',
                'birth_date',
                'pilot_identification_number',
                'pilot_certificate',
                'operator_certification',
            ]);
        });
    }
};
