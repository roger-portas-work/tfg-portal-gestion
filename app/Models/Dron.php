<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Casts;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

#[Fillable([
    'cliente_id',
    'uas_class',
    'manufacturer_name',
    'model',
    'drone_serial_number',
    'controller_serial_number',
    'registration_number',
    'registration_not_applicable',
    'mtom_weight',
    'remote_id_number',
    'remote_id_not_applicable',
    'class_marking',
    'band_frequency',
    'color',
    'payload',
    'payload_not_applicable',
    'vhf_equipment',
    'vhf_not_applicable',
    'emergency_equipment',
    'emergency_not_applicable',
    'insurance_policy_number',
    'insurance_valid_until',
    'insurance_company_name',
    'insurance_coverage_policy_path',
    'insurance_coverage_policy_original_name',
    'aesa_registration_status',
])]
#[Casts([
    'insurance_valid_until' => 'date',
    'registration_not_applicable' => 'boolean',
    'remote_id_not_applicable' => 'boolean',
    'payload_not_applicable' => 'boolean',
    'vhf_not_applicable' => 'boolean',
    'emergency_not_applicable' => 'boolean',
])]
class Dron extends Model
{
    protected $table = 'drones';

    public const UAS_CLASS_FIXED_WING = 'ala_fija';

    public const UAS_CLASS_HYBRID = 'hibrido';

    public const UAS_CLASS_ROTOR = 'rotor';

    public const AESA_STATUS_YES = 'si';

    public const AESA_STATUS_NO = 'no';

    public const AESA_STATUS_MANAGER = 'gestiona_gestor';

    public static function uasClassOptions(): array
    {
        return [
            self::UAS_CLASS_FIXED_WING => 'Ala fija',
            self::UAS_CLASS_HYBRID => 'Hibrido',
            self::UAS_CLASS_ROTOR => 'Rotor',
        ];
    }

    public static function classMarkingOptions(): array
    {
        return [
            'C0' => 'C0',
            'C1' => 'C1',
            'C2' => 'C2',
            'C3' => 'C3',
            'C4' => 'C4',
            'C5' => 'C5',
            'C6' => 'C6',
            'Legacy' => 'Legacy',
            'Construccion Privada' => 'Construccion Privada',
        ];
    }

    public static function aesaRegistrationOptions(): array
    {
        return [
            self::AESA_STATUS_YES => 'Si',
            self::AESA_STATUS_NO => 'No',
            self::AESA_STATUS_MANAGER => 'Gestiona gestor',
        ];
    }

    public static function aesaRegistrationDisplayOptions(): array
    {
        return [
            self::AESA_STATUS_YES => 'Registrado',
            self::AESA_STATUS_NO => 'No registrado',
            self::AESA_STATUS_MANAGER => 'Pendiente de registrar',
        ];
    }

    public function displayName(): string
    {
        return trim(($this->manufacturer_name ?? '').' '.($this->model ?? '')) ?: 'Dron sin nombre';
    }

    public function displayNameWithSerial(): string
    {
        $serial = filled($this->drone_serial_number)
            ? $this->drone_serial_number
            : 'Sin definir';

        return $this->displayName().' - Serie: '.$serial;
    }

    public function registrationLabel(): string
    {
        if ($this->registration_not_applicable) {
            return 'No aplica';
        }

        return $this->registration_number ?: 'Sin definir';
    }

    public function remoteIdLabel(): string
    {
        if ($this->remote_id_not_applicable) {
            return 'No aplica';
        }

        return $this->remote_id_number ?: 'Sin definir';
    }

    public function aesaRegistrationLabel(): string
    {
        return self::aesaRegistrationDisplayOptions()[$this->aesa_registration_status] ?? ucfirst((string) $this->aesa_registration_status);
    }

    public function aesaRegistrationColor(): string
    {
        return match ($this->aesa_registration_status) {
            self::AESA_STATUS_YES => 'success',
            self::AESA_STATUS_MANAGER => 'warning',
            default => 'gray',
        };
    }

    public function insuranceIsExpired(): bool
    {
        if (! $this->insurance_valid_until) {
            return false;
        }

        return Carbon::parse($this->insurance_valid_until)->startOfDay()
            ->lt(Carbon::today(config('app.timezone')));
    }

    /**
     * @return array<int, string>
     */
    public function missingOperationalFields(): array
    {
        $missing = [];

        if (blank($this->drone_serial_number)) {
            $missing[] = 'numero de serie';
        }

        if (! $this->registration_not_applicable && blank($this->registration_number)) {
            $missing[] = 'matricula';
        }

        if (! $this->remote_id_not_applicable && blank($this->remote_id_number)) {
            $missing[] = 'ID remoto';
        }

        if (! $this->payload_not_applicable && blank($this->payload)) {
            $missing[] = 'carga de pago';
        }

        if (! $this->vhf_not_applicable && blank($this->vhf_equipment)) {
            $missing[] = 'equipo VHF';
        }

        if (! $this->emergency_not_applicable && blank($this->emergency_equipment)) {
            $missing[] = 'equipo de emergencia';
        }

        if (blank($this->insurance_policy_number)) {
            $missing[] = 'numero de poliza';
        }

        if (blank($this->insurance_company_name)) {
            $missing[] = 'aseguradora';
        }

        if (blank($this->insurance_valid_until)) {
            $missing[] = 'fecha de validez del seguro';
        } elseif ($this->insuranceIsExpired()) {
            $missing[] = 'seguro caducado';
        }

        if (blank($this->insurance_coverage_policy_path)) {
            $missing[] = 'PDF de la poliza';
        }

        if (blank($this->aesa_registration_status)) {
            $missing[] = 'estado AESA';
        }

        return $missing;
    }

    public function isOperationallyComplete(): bool
    {
        return $this->missingOperationalFields() === [];
    }

    public function operationalStatusLabel(): string
    {
        return $this->isOperationallyComplete() ? 'Completo' : 'Incompleto';
    }

    public function operationalStatusColor(): string
    {
        if ($this->isOperationallyComplete()) {
            return 'success';
        }

        return $this->insuranceIsExpired() ? 'danger' : 'warning';
    }

    /**
     * Cada dron pertenece a un cliente concreto.
     */
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function operaciones(): HasMany
    {
        return $this->hasMany(Operacion::class);
    }
}
