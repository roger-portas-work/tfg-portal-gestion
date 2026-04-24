<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->string('second_last_name')->nullable()->after('last_name');
            $table->string('personal_email')->nullable()->after('email');
        });

        DB::table('clientes')
            ->whereNull('personal_email')
            ->update([
                'personal_email' => DB::raw('email'),
            ]);

        Schema::table('drones', function (Blueprint $table) {
            $table->string('drone_serial_number')->nullable()->after('model');
            $table->boolean('registration_not_applicable')->default(false)->after('registration_number');
            $table->boolean('payload_not_applicable')->default(false)->after('payload');
            $table->boolean('vhf_not_applicable')->default(false)->after('vhf_equipment');
            $table->boolean('emergency_not_applicable')->default(false)->after('emergency_equipment');
            $table->string('insurance_coverage_policy_path')->nullable()->after('insurance_company_name');
            $table->string('insurance_coverage_policy_original_name')->nullable()->after('insurance_coverage_policy_path');
            $table->string('aesa_registration_status')->default('no')->after('insurance_coverage_policy_original_name');
        });

        DB::statement('ALTER TABLE drones ALTER COLUMN registration_number DROP NOT NULL');
        DB::statement('ALTER TABLE drones ALTER COLUMN payload TYPE TEXT');
    }

    public function down(): void
    {
        DB::statement("UPDATE drones SET registration_number = '' WHERE registration_number IS NULL");
        DB::statement('ALTER TABLE drones ALTER COLUMN registration_number SET NOT NULL');
        DB::statement('ALTER TABLE drones ALTER COLUMN payload TYPE VARCHAR(255)');

        Schema::table('drones', function (Blueprint $table) {
            $table->dropColumn([
                'drone_serial_number',
                'registration_not_applicable',
                'payload_not_applicable',
                'vhf_not_applicable',
                'emergency_not_applicable',
                'insurance_coverage_policy_path',
                'insurance_coverage_policy_original_name',
                'aesa_registration_status',
            ]);
        });

        Schema::table('clientes', function (Blueprint $table) {
            $table->dropColumn([
                'second_last_name',
                'personal_email',
            ]);
        });
    }
};
