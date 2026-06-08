<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Casts;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

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

    protected static function booted(): void
    {
        static::saving(function (self $operacion): void {
            $messages = $operacion->assignmentValidationMessages();

            if ($messages !== []) {
                throw ValidationException::withMessages($messages);
            }
        });
    }

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

    public function assignmentsBelongToCliente(): bool
    {
        return $this->assignmentValidationMessages() === [];
    }

    /**
     * @return array<string, string>
     */
    public function assignmentValidationMessages(): array
    {
        if (blank($this->cliente_id)) {
            return [];
        }

        $messages = [];

        if (
            filled($this->piloto_id)
            && ! Piloto::query()
                ->whereKey($this->piloto_id)
                ->where('cliente_id', $this->cliente_id)
                ->exists()
        ) {
            $messages['piloto_id'] = 'El piloto seleccionado no pertenece al cliente de la operacion.';
        }

        if (
            filled($this->dron_id)
            && ! Dron::query()
                ->whereKey($this->dron_id)
                ->where('cliente_id', $this->cliente_id)
                ->exists()
        ) {
            $messages['dron_id'] = 'El dron seleccionado no pertenece al cliente de la operacion.';
        }

        return $messages;
    }

    public function scopeWithTramiteWorkflowCounts(Builder $query): Builder
    {
        $today = Carbon::today(config('app.timezone'))->toDateString();
        $dueUntil = Carbon::today(config('app.timezone'))->addDays(7)->toDateString();

        return $query->withCount([
            'tramites',
            'tramites as approved_tramites_count' => fn (Builder $tramitesQuery) => $tramitesQuery
                ->where('status', OperacionTramite::STATUS_APPROVED),
            'tramites as pending_to_process_tramites_count' => fn (Builder $tramitesQuery) => $tramitesQuery
                ->whereNull('processed_at')
                ->where('status', '!=', OperacionTramite::STATUS_APPROVED),
            'tramites as overdue_tramites_count' => fn (Builder $tramitesQuery) => $tramitesQuery
                ->whereNull('processed_at')
                ->where('status', '!=', OperacionTramite::STATUS_APPROVED)
                ->whereDate('deadline_date', '<', $today),
            'tramites as due_soon_tramites_count' => fn (Builder $tramitesQuery) => $tramitesQuery
                ->whereNull('processed_at')
                ->where('status', '!=', OperacionTramite::STATUS_APPROVED)
                ->whereBetween('deadline_date', [$today, $dueUntil]),
        ]);
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

    public function documentationIsFullyApproved(): bool
    {
        $tramitesCount = (int) ($this->tramites_count ?? 0);
        $approvedCount = (int) ($this->approved_tramites_count ?? 0);

        return $this->isConfirmed() && $tramitesCount > 0 && $approvedCount === $tramitesCount;
    }

    public function documentationStatusLabel(): string
    {
        if (! $this->isConfirmed()) {
            return 'No aplica';
        }

        return $this->documentationIsFullyApproved()
            ? 'Documentacion aprobada'
            : 'Falta documentacion';
    }

    public function documentationStatusColor(): string
    {
        if (! $this->isConfirmed()) {
            return 'gray';
        }

        return $this->documentationIsFullyApproved()
            ? 'success'
            : 'danger';
    }

    public function workflowPriorityLabel(): string
    {
        if ($this->isRejected()) {
            return 'Rechazada';
        }

        if ($this->isPending()) {
            return 'Pendiente decision';
        }

        if (! $this->isConfirmed()) {
            return 'Sin prioridad';
        }

        $tramitesCount = (int) ($this->tramites_count ?? 0);
        $approvedCount = (int) ($this->approved_tramites_count ?? 0);
        $pendingCount = (int) ($this->pending_to_process_tramites_count ?? 0);
        $overdueCount = (int) ($this->overdue_tramites_count ?? 0);
        $dueSoonCount = (int) ($this->due_soon_tramites_count ?? 0);

        if ($tramitesCount === 0) {
            return 'Sin tramites';
        }

        if ($overdueCount > 0) {
            return $overdueCount.' vencidos';
        }

        if ($dueSoonCount > 0) {
            return $dueSoonCount.' vencen en 7 dias';
        }

        if ($pendingCount > 0) {
            return $pendingCount.' pendientes';
        }

        if ($approvedCount < $tramitesCount) {
            return 'Falta cerrar';
        }

        return 'Documentacion completa';
    }

    public function workflowPriorityColor(): string
    {
        if ($this->isRejected()) {
            return 'gray';
        }

        if ($this->isPending()) {
            return 'warning';
        }

        if (! $this->isConfirmed()) {
            return 'gray';
        }

        if ((int) ($this->tramites_count ?? 0) === 0 || (int) ($this->overdue_tramites_count ?? 0) > 0) {
            return 'danger';
        }

        if ((int) ($this->due_soon_tramites_count ?? 0) > 0) {
            return 'warning';
        }

        if (
            (int) ($this->pending_to_process_tramites_count ?? 0) > 0
            || (int) ($this->approved_tramites_count ?? 0) < (int) ($this->tramites_count ?? 0)
        ) {
            return 'info';
        }

        return 'success';
    }

    public function workflowFocus(): string
    {
        if ($this->isRejected()) {
            return 'operacion-rechazada';
        }

        if ($this->isPending()) {
            return 'pendiente-confirmar';
        }

        if ((int) ($this->tramites_count ?? 0) === 0) {
            return 'sin-tramites';
        }

        if ((int) ($this->overdue_tramites_count ?? 0) > 0) {
            return 'tramites-vencidos';
        }

        if ((int) ($this->due_soon_tramites_count ?? 0) > 0) {
            return 'tramites-7-dias';
        }

        if ((int) ($this->pending_to_process_tramites_count ?? 0) > 0) {
            return 'tramites-pendientes';
        }

        if ((int) ($this->approved_tramites_count ?? 0) < (int) ($this->tramites_count ?? 0)) {
            return 'tramites-pendientes';
        }

        return 'documentacion-completa';
    }
}
