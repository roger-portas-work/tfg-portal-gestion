<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Casts;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'name',
    'last_name',
    'second_last_name',
    'email',
    'personal_email',
    'phone',
    'client_type',
    'profile_completed',
    'user_id',
    'dni',
    'address',
    'country',
    'city',
    'province',
    'postal_code',
    'operator_registration_number',
    'birth_date',
    'pilot_identification_number',
    'pilot_certificate',
    'operator_certification',
])]
#[Casts([
    'profile_completed' => 'boolean',
    'birth_date' => 'date',
])]
class Cliente extends Model
{
    use SoftDeletes;

    protected static function booted(): void
    {
        static::deleting(function (self $cliente): void {
            // En este MVP cada cliente tiene un unico usuario de acceso.
            // Si el gestor elimina el cliente, eliminamos tambien ese acceso.
            $cliente->user?->delete();
        });
    }

    // Centralizamos los tipos para reutilizarlos en formularios,
    // tablas y futuras reglas sin repetir textos por el proyecto.
    public const TYPE_FISICO = 'fisico';

    public const TYPE_JURIDICO = 'juridico';

    /**
     * @return array<string, string>
     */
    public static function typeOptions(): array
    {
        return [
            self::TYPE_FISICO => 'Fisico',
            self::TYPE_JURIDICO => 'Juridico',
        ];
    }

    /**
     * El cliente del negocio queda enlazado con su usuario de acceso.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * El cliente puede registrar varios drones.
     */
    public function drones(): HasMany
    {
        return $this->hasMany(Dron::class);
    }

    /**
     * El cliente puede registrar uno o varios pilotos operativos.
     */
    public function pilotos(): HasMany
    {
        return $this->hasMany(Piloto::class);
    }

    /**
     * El cliente puede registrar multiples operaciones.
     */
    public function operaciones(): HasMany
    {
        return $this->hasMany(Operacion::class);
    }

    /**
     * Requisitos que el gestor define para el expediente de operadora.
     */
    public function operadoraRequirements(): HasMany
    {
        return $this->hasMany(OperadoraRequirement::class);
    }

    public function operadoraProfile(): HasOne
    {
        return $this->hasOne(OperadoraProfile::class);
    }

    public function ensureOperadoraProfile(): OperadoraProfile
    {
        return $this->operadoraProfile()->firstOrCreate([], []);
    }

    public function ensureDefaultOperadoraRequirement(): OperadoraRequirement
    {
        return $this->operadoraRequirements()->firstOrCreate(
            ['is_system_default' => true],
            [
                'name' => 'CERTIFICADO OPERADOR',
                'input_type' => OperadoraRequirement::TYPE_PDF,
                'is_required' => true,
                'instructions' => 'Sube el PDF del CERTIFICADO OPERADOR.',
                'status' => OperadoraRequirement::STATUS_PENDING,
            ]
        );
    }

    public function ensureOperadoraSetup(): void
    {
        $this->ensureOperadoraProfile();
        $this->ensureDefaultOperadoraRequirement();
    }

    /**
     * Campos minimos que el cliente debe completar segun su tipo.
     *
     * @return array<int, string>
     */
    public function requiredProfileFields(): array
    {
        $commonFields = [
            'name',
            'last_name',
            'personal_email',
            'email',
            'phone',
            'dni',
            'address',
            'country',
            'city',
            'province',
            'postal_code',
            'birth_date',
        ];

        return $commonFields;
    }

    /**
     * Calculamos el estado de ficha completada desde los propios datos.
     * Asi el gestor no lo marca manualmente y el cliente lo desbloquea al completar su ficha.
     *
     * @param  array<string, mixed>|null  $attributes
     */
    public function profileIsComplete(?array $attributes = null): bool
    {
        $attributes ??= $this->attributesToArray();

        foreach ($this->requiredProfileFields() as $field) {
            $value = $attributes[$field] ?? null;

            if ($value === null || $value === '') {
                return false;
            }
        }

        return true;
    }

    public function fullName(): string
    {
        return trim(implode(' ', array_filter([
            $this->name,
            $this->last_name,
            $this->second_last_name,
        ])));
    }

    public function isUnblocked(): bool
    {
        return $this->profile_completed && $this->drones()->exists();
    }

    public function completedOperadoraRequirementsCount(): int
    {
        return $this->operadoraRequirements
            ->filter(fn (OperadoraRequirement $requirement): bool => $requirement->status === OperadoraRequirement::STATUS_APPROVED)
            ->count();
    }

    public function confirmedOperacionesCount(): int
    {
        return $this->operaciones()
            ->where('status', Operacion::STATUS_CONFIRMED)
            ->count();
    }

    public function rejectedOperacionesCount(): int
    {
        return $this->operaciones()
            ->where('status', Operacion::STATUS_REJECTED)
            ->count();
    }

    public function pendingOperacionesCount(): int
    {
        return $this->operaciones()
            ->where(function ($query): void {
                $query
                    ->whereNull('status')
                    ->orWhere('status', Operacion::STATUS_PENDING);
            })
            ->count();
    }

    public function pendingOperadoraRequirementsCount(): int
    {
        return $this->operadoraRequirements
            ->filter(fn (OperadoraRequirement $requirement): bool => $requirement->status !== OperadoraRequirement::STATUS_APPROVED)
            ->count();
    }

    public function pendingRequiredOperadoraRequirementsCount(): int
    {
        return $this->operadoraRequirements
            ->filter(fn (OperadoraRequirement $requirement): bool => $requirement->is_required && $requirement->status !== OperadoraRequirement::STATUS_APPROVED)
            ->count();
    }

    public function pendingOptionalOperadoraRequirementsCount(): int
    {
        return $this->operadoraRequirements
            ->filter(fn (OperadoraRequirement $requirement): bool => ! $requirement->is_required && $requirement->status !== OperadoraRequirement::STATUS_APPROVED)
            ->count();
    }
}
