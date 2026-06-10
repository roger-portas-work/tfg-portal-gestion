<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('clientes', 'client_type')) {
            return;
        }

        Schema::table('clientes', function (Blueprint $table): void {
            $table->dropColumn('client_type');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('clientes', 'client_type')) {
            return;
        }

        Schema::table('clientes', function (Blueprint $table): void {
            $table->string('client_type')
                ->default('fisico')
                ->after('name');
        });
    }
};
