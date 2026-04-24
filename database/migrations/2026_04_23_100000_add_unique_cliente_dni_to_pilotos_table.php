<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("UPDATE pilotos SET dni_nie = UPPER(TRIM(dni_nie))");

        Schema::table('pilotos', function (Blueprint $table) {
            $table->unique(['cliente_id', 'dni_nie'], 'pilotos_cliente_id_dni_nie_unique');
        });
    }

    public function down(): void
    {
        Schema::table('pilotos', function (Blueprint $table) {
            $table->dropUnique('pilotos_cliente_id_dni_nie_unique');
        });
    }
};
