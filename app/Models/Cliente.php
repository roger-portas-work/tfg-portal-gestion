<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Casts;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'name',
    'last_name',
    'email',
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
     * Requisitos que el gestor define para el expediente de operadora.
     */
    public function operadoraRequirements(): HasMany
    {
        return $this->hasMany(OperadoraRequirement::class);
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
            'email',
            'phone',
            'dni',
            'address',
            'country',
            'city',
            'province',
            'postal_code',
            'operator_registration_number',
            'birth_date',
            'pilot_identification_number',
        ];

        return match ($this->client_type) {
            self::TYPE_JURIDICO => [
                ...$commonFields,
                'operator_certification',
            ],
            default => [
                ...$commonFields,
                'pilot_certificate',
            ],
        };
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
        ])));
    }

    public function isUnblocked(): bool
    {
        return $this->profile_completed && $this->drones()->exists();
    }

    public function completedOperadoraRequirementsCount(): int
    {
        return $this->operadoraRequirements
            ->filter(fn (OperadoraRequirement $requirement): bool => $requirement->isCompleted())
            ->count();
    }

    public function pendingOperadoraRequirementsCount(): int
    {
        return $this->operadoraRequirements
            ->filter(fn (OperadoraRequirement $requirement): bool => ! $requirement->isCompleted())
            ->count();
    }
}
