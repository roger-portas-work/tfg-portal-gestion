<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OperacionTramite extends Model
{
    public const STATUS_PENDING = 'pending';

    protected $fillable = [
        'operacion_id',
        'title',
        'attachments',
        'attachment_file_names',
        'deadline_date',
        'processed_at',
        'status',
        'request_code',
        'extra_information',
    ];

    protected $casts = [
        'attachments' => 'array',
        'attachment_file_names' => 'array',
        'deadline_date' => 'date',
        'processed_at' => 'date',
    ];

    public const STATUS_DENIED = 'denied';

    public const STATUS_PROCESSED = 'processed';

    public const STATUS_APPROVED = 'approved';

    /**
     * @return array<string, string>
     */
    public static function statusOptions(): array
    {
        return [
            self::STATUS_PENDING => 'Pendiente',
            self::STATUS_DENIED => 'Denegado',
            self::STATUS_PROCESSED => 'Procesado',
            self::STATUS_APPROVED => 'Aprobado',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function frequentTitleOptions(): array
    {
        return [
            'Coordinacion aeropuerto' => 'Coordinacion aeropuerto',
            'Coordinacion helipuerto' => 'Coordinacion helipuerto',
            "Comunicacion Mossos d'Esquadra" => "Comunicacion Mossos d'Esquadra",
            'Comunicacion Ministerio del Interior' => 'Comunicacion Ministerio del Interior',
            'Permiso ZEPA' => 'Permiso ZEPA',
            'Parque natural' => 'Parque natural',
            'FPL' => 'FPL',
            'Briefing operacion' => 'Briefing operacion',
        ];
    }

    public function operacion(): BelongsTo
    {
        return $this->belongsTo(Operacion::class);
    }

    public function statusLabel(): string
    {
        return self::statusOptions()[$this->status] ?? 'Sin definir';
    }

    public function statusColor(): string
    {
        return match ($this->status) {
            self::STATUS_APPROVED => 'success',
            self::STATUS_DENIED => 'danger',
            self::STATUS_PROCESSED => 'warning',
            default => 'gray',
        };
    }
}
