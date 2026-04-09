<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table): void {
            $table->string('country', 120)->nullable()->after('address');
            $table->string('city', 120)->nullable()->after('country');
            $table->string('province', 120)->nullable()->after('city');
            $table->string('postal_code', 20)->nullable()->after('province');
        });
    }

    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table): void {
            $table->dropColumn([
                'country',
                'city',
                'province',
                'postal_code',
            ]);
        });
    }
};
