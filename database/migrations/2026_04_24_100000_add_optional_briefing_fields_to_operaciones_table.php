<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('operaciones', function (Blueprint $table): void {
            $table->string('video_objective')->nullable()->after('extra_information');
            $table->string('end_client')->nullable()->after('video_objective');
            $table->string('production_company_name')->nullable()->after('end_client');
            $table->string('production_contact_phone')->nullable()->after('production_company_name');
            $table->string('environment_type')->nullable()->after('production_contact_phone');
            $table->boolean('people_present')->nullable()->after('environment_type');
            $table->text('prior_permits_notes')->nullable()->after('people_present');
        });
    }

    public function down(): void
    {
        Schema::table('operaciones', function (Blueprint $table): void {
            $table->dropColumn([
                'video_objective',
                'end_client',
                'production_company_name',
                'production_contact_phone',
                'environment_type',
                'people_present',
                'prior_permits_notes',
            ]);
        });
    }
};
