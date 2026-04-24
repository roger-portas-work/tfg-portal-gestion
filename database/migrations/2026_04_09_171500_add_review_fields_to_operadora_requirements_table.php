<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('operadora_requirements', function (Blueprint $table) {
            $table->string('original_file_name')->nullable()->after('file_path');
            $table->string('mime_type')->nullable()->after('original_file_name');
            $table->unsignedBigInteger('file_size')->nullable()->after('mime_type');
            $table->text('review_notes')->nullable()->after('file_size');
            $table->timestamp('reviewed_at')->nullable()->after('submitted_at');
        });
    }

    public function down(): void
    {
        Schema::table('operadora_requirements', function (Blueprint $table) {
            $table->dropColumn([
                'original_file_name',
                'mime_type',
                'file_size',
                'review_notes',
                'reviewed_at',
            ]);
        });
    }
};
