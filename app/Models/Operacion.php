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

    public static function activeForGestorFromDate(): string
    {
        return Carbon::today(config('app.timezone'))->toDateString();
    }

    public function scopeNotRejectedForGestor(Builder $query): Builder
    {
        return $query->where(function (Builder $query): void {
            $query
                ->whereNull('status')
                ->orWhere('status', '!=', self::STATUS_REJECTED);
        });
    }

    public function scopeActiveForGestor(Builder $query, ?string $from = null): Builder
    {
        return $query
            ->whereDate('operation_date', '>=', $from ?? self::activeForGestorFromDate())
            ->notRejectedForGestor();
    }

    /**
     * Operaciones que cuentan en el total mostrado en la lista de clientes:
     * las ya pasadas (salvo rechazadas) y cualquiera que esté confirmada.
     */
    public function scopeCountableForClienteSummary(Builder $query): Builder
    {
        $today = Carbon::today(config('app.timezone'))->toDateString();

        return $query
            ->notRejectedForGestor()
            ->where(function (Builder $query) use ($today): void {
                $query
                    ->whereDate('operation_date', '<', $today)
                    ->orWhere('status', self::STATUS_CONFIRMED);
            });
    }

    /**
     * Operaciones activas para la lista de clientes: confirmadas, de hoy o futuras.
     */
    public function scopeActiveForClienteSummary(Builder $query): Builder
    {
        return $query
            ->whereDate('operation_date', '>=', self::activeForGestorFromDate())
            ->where('status', self::STATUS_CONFIRMED);
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
            'tramites as denied_tramites_count' => fn (Builder $tramitesQuery) => $tramitesQuery
                ->where('status', OperacionTramite::STATUS_DENIED),
            'tramites as processed_for_gestor_tramites_count' => fn (Builder $tramitesQuery) => $tramitesQuery
                ->whereNotNull('processed_at'),
            'tramites as pending_to_process_tramites_count' => fn (Builder $tramitesQuery) => $tramitesQuery
                ->unprocessedNotApprovedForGestor(),
            'tramites as overdue_tramites_count' => fn (Builder $tramitesQuery) => $tramitesQuery
                ->overdueForGestor($today),
            'tramites as due_soon_tramites_count' => fn (Builder $tramitesQuery) => $tramitesQuery
                ->dueSoonForGestor($today, $dueUntil),
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

    public function tramitesAreFullyApproved(): bool
    {
        $tramitesCount = (int) ($this->tramites_count ?? 0);
        $approvedCount = (int) ($this->approved_tramites_count ?? 0);

        return $this->isConfirmed() && $tramitesCount > 0 && $approvedCount === $tramitesCount;
    }

    public function tramitesStatusLabel(): string
    {
        if (! $this->isConfirmed()) {
            return 'No aplican';
        }

        return $this->gestorFollowUpLabel();
    }

    public function tramitesStatusColor(): string
    {
        if (! $this->isConfirmed()) {
            return 'gray';
        }

        return $this->gestorFollowUpColor();
    }

    public function documentationIsFullyApproved(): bool
    {
        return $this->tramitesAreFullyApproved();
    }

    public function documentationStatusLabel(): string
    {
        return $this->tramitesStatusLabel();
    }

    public function documentationStatusColor(): string
    {
        return $this->tramitesStatusColor();
    }

    public function workflowPriorityLabel(): string
    {
        return $this->gestorFollowUpLabel();
    }

    public function workflowPriorityColor(): string
    {
        return $this->gestorFollowUpColor();
    }

    public function workflowFocus(): string
    {
        return $this->gestorFollowUpFocus();
    }

    public function gestorFollowUpLabel(): string
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
        $deniedCount = (int) ($this->denied_tramites_count ?? 0);
        $processedCount = (int) ($this->processed_for_gestor_tramites_count ?? 0);
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
            return $pendingCount.' pendientes de tramitar';
        }

        if ($deniedCount > 0) {
            return $deniedCount.' '.($deniedCount === 1 ? 'denegado' : 'denegados');
        }

        if ($approvedCount === $tramitesCount) {
            return 'Tramites aprobados';
        }

        if ($processedCount === $tramitesCount) {
            return 'Tramites procesados';
        }

        return 'Tramites pendientes';
    }

    public function gestorFollowUpColor(): string
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

        if ((int) ($this->pending_to_process_tramites_count ?? 0) > 0) {
            return 'info';
        }

        if ((int) ($this->denied_tramites_count ?? 0) > 0) {
            return 'danger';
        }

        if ((int) ($this->approved_tramites_count ?? 0) === (int) ($this->tramites_count ?? 0)) {
            return 'success';
        }

        return 'info';
    }

    public function gestorFollowUpFocus(): string
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

        if ((int) ($this->denied_tramites_count ?? 0) > 0) {
            return 'tramites-denegados';
        }

        if ((int) ($this->approved_tramites_count ?? 0) === (int) ($this->tramites_count ?? 0)) {
            return 'tramites-aprobados';
        }

        if ((int) ($this->processed_for_gestor_tramites_count ?? 0) === (int) ($this->tramites_count ?? 0)) {
            return 'tramites-procesados';
        }

        return 'tramites-pendientes';
    }

    public function gestorFollowUpDescription(): string
    {
        if ($this->isRejected()) {
            return 'Operacion rechazada';
        }

        if ($this->isPending()) {
            return 'Revisar y confirmar con el cliente';
        }

        if (! $this->isConfirmed()) {
            return 'Sin seguimiento operativo';
        }

        $tramitesCount = (int) ($this->tramites_count ?? 0);

        if ($tramitesCount === 0) {
            return 'Crea los tramites necesarios para esta operacion';
        }

        return 'Total '.$tramitesCount
            .' - Tramitados '.(int) ($this->processed_for_gestor_tramites_count ?? 0)
            .' - Aprobados '.(int) ($this->approved_tramites_count ?? 0)
            .' - Denegados '.(int) ($this->denied_tramites_count ?? 0)
            .' - Pendientes de tramitar '.(int) ($this->pending_to_process_tramites_count ?? 0);
    }
}
