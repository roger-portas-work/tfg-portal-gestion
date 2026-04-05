<?php

use App\Models\Cliente;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Anadimos solo lo minimo para el MVP de clientes:
     * clasificar el tipo y saber si la ficha esta completada.
     */
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->string('client_type')
                ->default(Cliente::TYPE_FISICO)
                ->after('name');
            $table->boolean('profile_completed')
                ->default(false)
                ->after('address');
        });
    }

    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropColumn([
                'client_type',
                'profile_completed',
            ]);
        });
    }
};
