<?php

use App\Models\OperacionTramite;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("alter table operacion_tramites alter column status set default '".OperacionTramite::STATUS_PENDING."'");

        DB::table('operacion_tramites')
            ->whereNull('status')
            ->update(['status' => OperacionTramite::STATUS_PENDING]);
    }

    public function down(): void
    {
        DB::statement("alter table operacion_tramites alter column status set default '".OperacionTramite::STATUS_PROCESSED."'");
    }
};
