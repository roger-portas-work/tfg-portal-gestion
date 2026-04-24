<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Casts;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
            'C1' => 'C1',
            'C2' => 'C2',
            'C3' => 'C3',
            'C4' => 'C4',
            'C5' => 'C5',
            'C6' => 'C6',
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
        return self::aesaRegistrationOptions()[$this->aesa_registration_status] ?? ucfirst((string) $this->aesa_registration_status);
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
