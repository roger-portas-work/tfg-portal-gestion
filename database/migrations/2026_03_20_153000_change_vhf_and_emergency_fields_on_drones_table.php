<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE drones ALTER COLUMN vhf_equipment TYPE VARCHAR(255)');
        DB::statement('ALTER TABLE drones ALTER COLUMN emergency_equipment TYPE VARCHAR(255)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE drones ALTER COLUMN vhf_equipment TYPE BOOLEAN USING CASE WHEN LOWER(COALESCE(vhf_equipment, \'\')) IN (\'si\', \'sí\', \'true\', \'1\') THEN TRUE ELSE FALSE END');
        DB::statement('ALTER TABLE drones ALTER COLUMN emergency_equipment TYPE BOOLEAN USING CASE WHEN LOWER(COALESCE(emergency_equipment, \'\')) IN (\'si\', \'sí\', \'true\', \'1\') THEN TRUE ELSE FALSE END');
    }
};