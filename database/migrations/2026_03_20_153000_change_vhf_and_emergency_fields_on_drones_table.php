<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * La tabla drones ya se creo con estos campos como booleanos.
     * Ahora los necesitamos como texto libre para ajustarnos al formulario real.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE drones ALTER COLUMN vhf_equipment TYPE VARCHAR(255) USING CASE WHEN vhf_equipment IS TRUE THEN \'Si\' WHEN vhf_equipment IS FALSE THEN \'No\' ELSE NULL END');
        DB::statement('ALTER TABLE drones ALTER COLUMN emergency_equipment TYPE VARCHAR(255) USING CASE WHEN emergency_equipment IS TRUE THEN \'Si\' WHEN emergency_equipment IS FALSE THEN \'No\' ELSE NULL END');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE drones ALTER COLUMN vhf_equipment TYPE BOOLEAN USING CASE WHEN LOWER(COALESCE(vhf_equipment, \'\')) IN (\'si\', \'sí\', \'true\', \'1\') THEN TRUE ELSE FALSE END');
        DB::statement('ALTER TABLE drones ALTER COLUMN emergency_equipment TYPE BOOLEAN USING CASE WHEN LOWER(COALESCE(emergency_equipment, \'\')) IN (\'si\', \'sí\', \'true\', \'1\') THEN TRUE ELSE FALSE END');
    }
};
