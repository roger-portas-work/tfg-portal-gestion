<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Casts;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'cliente_id',
    'first_name',
    'last_name',
    'second_last_name',
    'dni_nie',
    'birth_date',
    'pilot_identification_number',
    'maximum_pilot_certification',
    'address',
    'country',
    'city',
    'province',
    'postal_code',
    'phone',
    'has_radiofonista_certificate',
    'radiofonista_certificate_path',
    'theoretical_certificate_level',
    'dni_front_path',
    'dni_back_path',
    'theoretical_certificate_path',
    'practical_certificate_path',
])]
#[Casts([
    'birth_date' => 'date',
    'has_radiofonista_certificate' => 'boolean',
])]
class Piloto extends Model
{
    public const THEORY_A1_A3 = 'a1_a3';

    public const THEORY_A2 = 'a2';

    public const THEORY_STS = 'sts';

    /**
     * @return array<string, string>
     */
    public static function theoreticalCertificateOptions(): array
    {
        return [
            self::THEORY_A1_A3 => 'A1/A3',
            self::THEORY_A2 => 'A2',
            self::THEORY_STS => 'STS',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function operaciones(): HasMany
    {
        return $this->hasMany(Operacion::class);
    }

    public function fullName(): string
    {
        return trim(implode(' ', array_filter([
            $this->first_name,
            $this->last_name,
            $this->second_last_name,
        ])));
    }

    public function displayName(): string
    {
        return $this->fullName() ?: 'Piloto sin nombre';
    }

    public function displayNameWithIdentification(): string
    {
        $name = trim(implode(' ', array_filter([
            $this->first_name,
            $this->last_name,
        ]))) ?: $this->displayName();

        $identification = filled($this->dni_nie)
            ? $this->dni_nie
            : 'Sin definir';

        return $name.' - DNI/NIE: '.$identification;
    }

    public function requiresPracticalCertificate(): bool
    {
        return $this->theoretical_certificate_level === self::THEORY_STS;
    }

    /**
     * @return array<int, string>
     */
    public function missingOperationalFields(): array
    {
        $missing = [];

        if (blank($this->dni_nie)) {
            $missing[] = 'DNI/NIE';
        }

        if (blank($this->pilot_identification_number)) {
            $missing[] = 'identificacion de piloto';
        }

        if (blank($this->theoretical_certificate_level)) {
            $missing[] = 'nivel teorico';
        }

        if (blank($this->dni_front_path)) {
            $missing[] = 'DNI frontal';
        }

        if (blank($this->dni_back_path)) {
            $missing[] = 'DNI trasero';
        }

        if (blank($this->theoretical_certificate_path)) {
            $missing[] = 'certificado teorico';
        }

        if ($this->requiresPracticalCertificate() && blank($this->practical_certificate_path)) {
            $missing[] = 'certificado practico';
        }

        if ($this->has_radiofonista_certificate && blank($this->radiofonista_certificate_path)) {
            $missing[] = 'certificado radiofonista';
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
        return $this->isOperationallyComplete() ? 'success' : 'warning';
    }
}
