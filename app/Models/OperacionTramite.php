<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

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

    public function daysUntilDeadline(): ?int
    {
        if (! $this->deadline_date) {
            return null;
        }

        $timezone = config('app.timezone');
        $deadline = $this->deadline_date instanceof \DateTimeInterface
            ? Carbon::instance($this->deadline_date)->startOfDay()
            : Carbon::parse($this->deadline_date, $timezone)->startOfDay();

        return (int) Carbon::today($timezone)->diffInDays($deadline, false);
    }

    public function deadlineCountdownLabel(): string
    {
        $days = $this->daysUntilDeadline();

        if ($days === null) {
            return 'Sin fecha limite';
        }

        if ($days < 0) {
            $elapsedDays = abs($days);

            return 'Vencido hace '.$elapsedDays.' '.($elapsedDays === 1 ? 'dia' : 'dias');
        }

        if ($days === 0) {
            return 'Vence hoy';
        }

        return ($days === 1 ? 'Falta ' : 'Faltan ').$days.' '.($days === 1 ? 'dia' : 'dias');
    }

    public function deadlineCountdownColor(): string
    {
        $days = $this->daysUntilDeadline();

        if ($days === null) {
            return 'gray';
        }

        if ($days <= 7) {
            return 'danger';
        }

        if ($days <= 29) {
            return 'warning';
        }

        return 'success';
    }
}
