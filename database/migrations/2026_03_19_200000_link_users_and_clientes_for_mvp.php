<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * En el MVP necesitamos dos tipos de usuario:
     * gestor para el panel admin y cliente para el portal del cliente.
     * Tambien enlazamos cada cliente con su usuario de acceso.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')
                ->default(User::ROLE_GESTOR)
                ->after('password');
        });

        // Los usuarios que ya existen en esta fase del proyecto son gestores.
        DB::table('users')->update([
            'role' => User::ROLE_GESTOR,
        ]);

        Schema::table('clientes', function (Blueprint $table) {
            $table->foreignId('user_id')
                ->nullable()
                ->unique()
                ->after('id')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};
