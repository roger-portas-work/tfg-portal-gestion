<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Casts;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

#[Fillable([
    'name',
    'last_name',
    'second_last_name',
    'email',
    'personal_email',
    'phone',
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
        static::saving(function (self $cliente): void {
            $cliente->dni = self::normalizeIdentification($cliente->dni);

            if (filled($cliente->dni) && $cliente->dniIsAlreadyUsed()) {
                throw ValidationException::withMessages([
                    'dni' => 'Ya existe otro cliente con este DNI o NIE.',
                ]);
            }
        });

        static::deleting(function (self $cliente): void {
            if (! $cliente->canBeDeletedSafely()) {
                throw ValidationException::withMessages([
                    'cliente' => $cliente->deletionBlockedMessage(),
                ]);
            }
        });
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
     * @return array<int, string>
     */
    public function deletionBlockers(): array
    {
        $blockers = [];

        if ($this->user()->exists()) {
            $blockers[] = 'usuario de acceso';
        }

        if ($this->operaciones()->exists()) {
            $blockers[] = 'operaciones';
        }

        if ($this->drones()->exists()) {
            $blockers[] = 'drones';
        }

        if ($this->pilotos()->exists()) {
            $blockers[] = 'pilotos';
        }

        if ($this->operadoraRequirements()->exists()) {
            $blockers[] = 'requisitos de operadora';
        }

        if ($this->operadoraProfile()->exists()) {
            $blockers[] = 'expediente de operadora';
        }

        return $blockers;
    }

    public function canBeDeletedSafely(): bool
    {
        return $this->deletionBlockers() === [];
    }

    public function deletionBlockedMessage(): string
    {
        $blockers = $this->deletionBlockers();

        if ($blockers === []) {
            return 'Este cliente no tiene datos de negocio asociados.';
        }

        return 'No se puede eliminar este cliente porque conserva '.implode(', ', $blockers).'. Revisa el expediente antes de archivarlo o borrarlo manualmente.';
    }

    /**
     * Campos minimos que el cliente debe completar.
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

    public static function normalizeIdentification(?string $value): ?string
    {
        $value = Str::upper(trim((string) $value));

        return $value === '' ? null : $value;
    }

    public function dniIsAlreadyUsed(): bool
    {
        $query = static::withTrashed()
            ->whereRaw('UPPER(TRIM(dni)) = ?', [$this->dni]);

        if ($this->exists) {
            $query->whereKeyNot($this->getKey());
        }

        return $query->exists();
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
