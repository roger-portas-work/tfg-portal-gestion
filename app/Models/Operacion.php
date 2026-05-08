<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Casts;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'cliente_id',
    'piloto_id',
    'dron_id',
    'reference',
    'status',
    'operation_date',
    'estimated_filming_schedule',
    'address',
    'country',
    'city',
    'province',
    'postal_code',
    'google_maps_link',
    'altitude',
    'operation_radius',
    'operation_cost',
    'operational_conditions',
    'extra_information',
    'video_objective',
    'end_client',
    'production_company_name',
    'production_contact_phone',
    'environment_type',
    'people_present',
    'prior_permits_notes',
    'location',
    'description',
])]
#[Casts([
    'operation_date' => 'date',
    'altitude' => 'decimal:2',
    'operation_radius' => 'decimal:2',
    'operation_cost' => 'decimal:2',
    'people_present' => 'boolean',
])]
class Operacion extends Model
{
    protected $table = 'operaciones';

    public const STATUS_PENDING = 'pending';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_CONFIRMED = 'confirmed';

    /**
     * @return array<string, string>
     */
    public static function statusOptions(): array
    {
        return [
            self::STATUS_PENDING => 'Pendiente',
            self::STATUS_REJECTED => 'Rechazada',
            self::STATUS_CONFIRMED => 'Confirmada',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function piloto(): BelongsTo
    {
        return $this->belongsTo(Piloto::class);
    }

    public function dron(): BelongsTo
    {
        return $this->belongsTo(Dron::class);
    }

    public function tramites(): HasMany
    {
        return $this->hasMany(OperacionTramite::class);
    }

    public function statusLabel(): string
    {
        return self::statusOptions()[$this->status] ?? self::statusOptions()[self::STATUS_PENDING];
    }

    public function statusColor(): string
    {
        return match ($this->status) {
            self::STATUS_CONFIRMED => 'success',
            self::STATUS_REJECTED => 'danger',
            default => 'warning',
        };
    }

    public function isPending(): bool
    {
        return blank($this->status) || $this->status === self::STATUS_PENDING;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    public function isConfirmed(): bool
    {
        return $this->status === self::STATUS_CONFIRMED;
    }
}
