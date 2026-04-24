<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('drones', function (Blueprint $table) {
            $table->boolean('remote_id_not_applicable')->default(false)->after('remote_id_number');
        });

        DB::statement('ALTER TABLE drones ALTER COLUMN remote_id_number DROP NOT NULL');
    }

    public function down(): void
    {
        DB::statement("UPDATE drones SET remote_id_number = '' WHERE remote_id_number IS NULL");
        DB::statement('ALTER TABLE drones ALTER COLUMN remote_id_number SET NOT NULL');

        Schema::table('drones', function (Blueprint $table) {
            $table->dropColumn('remote_id_not_applicable');
        });
    }
};
