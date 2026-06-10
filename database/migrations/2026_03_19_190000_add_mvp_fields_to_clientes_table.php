<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Anadimos solo lo minimo para el MVP de clientes:
     * saber si la ficha esta completada.
     */
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->boolean('profile_completed')
                ->default(false)
                ->after('address');
        });
    }

    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropColumn([
                'profile_completed',
            ]);
        });
    }
};
