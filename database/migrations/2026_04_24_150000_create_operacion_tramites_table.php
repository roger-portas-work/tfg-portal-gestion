<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operacion_tramites', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('operacion_id')->constrained('operaciones')->cascadeOnDelete();
            $table->string('title');
            $table->json('attachments')->nullable();
            $table->json('attachment_file_names')->nullable();
            $table->date('deadline_date')->nullable();
            $table->date('processed_at')->nullable();
            $table->string('status')->default('processed');
            $table->string('request_code')->nullable();
            $table->text('extra_information')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operacion_tramites');
    }
};
