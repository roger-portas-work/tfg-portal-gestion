<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('operaciones', function (Blueprint $table): void {
            $table->string('status')->default('pending')->after('reference');
            $table->decimal('operation_cost', 10, 2)->nullable()->after('operation_radius');
            $table->text('operational_conditions')->nullable()->after('operation_cost');
            $table->index(['status', 'operation_date'], 'operaciones_status_operation_date_index');
        });
    }

    public function down(): void
    {
        Schema::table('operaciones', function (Blueprint $table): void {
            $table->dropIndex('operaciones_status_operation_date_index');
            $table->dropColumn([
                'status',
                'operation_cost',
                'operational_conditions',
            ]);
        });
    }
};
