<?php

use App\Models\OperadoraRequirement;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El gestor define aqui los requisitos del expediente de operadora
     * y el cliente completa el valor o PDF en el mismo registro.
     */
    public function up(): void
    {
        Schema::create('operadora_requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $table->string('name');
            $table->string('input_type')->default(OperadoraRequirement::TYPE_PDF);
            $table->boolean('is_required')->default(true);
            $table->text('instructions')->nullable();
            $table->string('status')->default(OperadoraRequirement::STATUS_PENDING);
            $table->text('text_value')->nullable();
            $table->string('file_path')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operadora_requirements');
    }
};
