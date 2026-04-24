<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE drones ALTER COLUMN payload DROP NOT NULL');
        DB::statement('ALTER TABLE drones ALTER COLUMN vhf_equipment DROP NOT NULL');
        DB::statement('ALTER TABLE drones ALTER COLUMN emergency_equipment DROP NOT NULL');
    }

    public function down(): void
    {
        DB::statement("UPDATE drones SET payload = '' WHERE payload IS NULL");
        DB::statement("UPDATE drones SET vhf_equipment = '' WHERE vhf_equipment IS NULL");
        DB::statement("UPDATE drones SET emergency_equipment = '' WHERE emergency_equipment IS NULL");

        DB::statement('ALTER TABLE drones ALTER COLUMN payload SET NOT NULL');
        DB::statement('ALTER TABLE drones ALTER COLUMN vhf_equipment SET NOT NULL');
        DB::statement('ALTER TABLE drones ALTER COLUMN emergency_equipment SET NOT NULL');
    }
};
