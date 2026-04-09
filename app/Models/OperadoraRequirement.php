<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OperadoraRequirement extends Model
{
    protected $fillable = [
        'cliente_id',
        'name',
        'input_type',
        'is_required',
        'instructions',
        'status',
        'text_value',
        'file_path',
        'submitted_at',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'submitted_at' => 'datetime',
    ];

    public const TYPE_PDF = 'pdf';

    public const TYPE_TEXT = 'text';

    public const STATUS_PENDING = 'pending_upload';

    public const STATUS_IN_REVIEW = 'in_review';

    /**
     * Cada requisito pertenece al expediente de operadora de un cliente.
     */
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    /**
     * Opciones reutilizables para el gestor al definir el tipo.
     *
     * @return array<string, string>
     */
    public static function inputTypeOptions(): array
    {
        return [
            self::TYPE_PDF => 'PDF',
            self::TYPE_TEXT => 'Texto',
        ];
    }

    /**
     * Etiquetas humanas para los estados del expediente.
     *
     * @return array<string, string>
     */
    public static function statusOptions(): array
    {
        return [
            self::STATUS_PENDING => 'Pendiente de subir',
            self::STATUS_IN_REVIEW => 'En revision',
        ];
    }

    public function isCompleted(): bool
    {
        return match ($this->input_type) {
            self::TYPE_TEXT => filled($this->text_value),
            default => filled($this->file_path),
        };
    }
}