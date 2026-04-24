<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operadora_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained()->cascadeOnDelete();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('second_last_name')->nullable();
            $table->string('registration_number')->nullable();
            $table->date('expiration_date')->nullable();
            $table->timestamps();

            $table->unique('cliente_id');
        });

        Schema::table('operadora_requirements', function (Blueprint $table) {
            $table->boolean('is_system_default')->default(false)->after('is_required');
        });

        DB::statement('
            INSERT INTO operadora_profiles (cliente_id, first_name, last_name, second_last_name, created_at, updated_at)
            SELECT id, name, last_name, second_last_name, NOW(), NOW()
            FROM clientes
        ');

        DB::table('operadora_requirements')
            ->whereRaw('UPPER(name) = ?', ['CERTIFICADO OPERADOR'])
            ->where('input_type', 'pdf')
            ->update(['is_system_default' => true]);

        $clientesWithoutDefaultRequirement = DB::table('clientes')
            ->leftJoin('operadora_requirements', function ($join) {
                $join->on('operadora_requirements.cliente_id', '=', 'clientes.id')
                    ->where('operadora_requirements.is_system_default', true);
            })
            ->whereNull('operadora_requirements.id')
            ->select('clientes.id')
            ->get();

        foreach ($clientesWithoutDefaultRequirement as $cliente) {
            DB::table('operadora_requirements')->insert([
                'cliente_id' => $cliente->id,
                'name' => 'CERTIFICADO OPERADOR',
                'input_type' => 'pdf',
                'is_required' => true,
                'is_system_default' => true,
                'instructions' => 'Sube el PDF del CERTIFICADO OPERADOR.',
                'status' => 'pending_upload',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('operadora_requirements', function (Blueprint $table) {
            $table->dropColumn('is_system_default');
        });

        Schema::dropIfExists('operadora_profiles');
    }
};
