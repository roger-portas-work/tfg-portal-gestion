<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Operaciones\OperacionResource;
use App\Models\Operacion;
use App\Models\OperacionTramite;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class TodayOperacionesWidget extends Widget
{
    protected string $view = 'filament.widgets.today-operaciones-widget';

    protected int | string | array $columnSpan = 'full';

    protected static ?int $sort = 2;

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $today = Carbon::today(config('app.timezone'))->toDateString();
        $dueUntil = Carbon::today(config('app.timezone'))->addDays(7)->toDateString();

        $operaciones = $this->withTramiteMetrics(
            Operacion::query(),
            $today,
            $dueUntil
        )
            ->with(['cliente', 'piloto', 'dron'])
            ->whereDate('operation_date', $today)
            ->notRejectedForGestor()
            ->orderByRaw($this->priorityOrderSql())
            ->orderBy('estimated_filming_schedule')
            ->orderBy('id')
            ->limit(8)
            ->get()
            ->map(fn (Operacion $operacion): array => $this->formatOperacion($operacion));

        return [
            'operaciones' => $operaciones,
        ];
    }

    protected function withTramiteMetrics(Builder $query, string $today, string $dueUntil): Builder
    {
        $approvedStatus = OperacionTramite::STATUS_APPROVED;
        $metrics = OperacionTramite::query()
            ->select('operacion_id')
            ->selectRaw('count(*) as tramites_count')
            ->selectRaw('sum(case when status = ? then 1 else 0 end) as approved_tramites_count', [$approvedStatus])
            ->selectRaw('sum(case when processed_at is null and status != ? then 1 else 0 end) as pending_to_process_tramites_count', [$approvedStatus])
            ->selectRaw('sum(case when processed_at is null and status != ? and deadline_date < ? then 1 else 0 end) as overdue_tramites_count', [$approvedStatus, $today])
            ->selectRaw('sum(case when processed_at is null and status != ? and deadline_date between ? and ? then 1 else 0 end) as due_soon_tramites_count', [$approvedStatus, $today, $dueUntil])
            ->selectRaw('min(case when processed_at is null and status != ? and deadline_date is not null then deadline_date else null end) as next_deadline_date', [$approvedStatus])
            ->groupBy('operacion_id');

        return $query
            ->leftJoinSub($metrics, 'tramite_metrics', 'tramite_metrics.operacion_id', '=', 'operaciones.id')
            ->select('operaciones.*')
            ->selectRaw('coalesce(tramite_metrics.tramites_count, 0) as tramites_count')
            ->selectRaw('coalesce(tramite_metrics.approved_tramites_count, 0) as approved_tramites_count')
            ->selectRaw('coalesce(tramite_metrics.pending_to_process_tramites_count, 0) as pending_to_process_tramites_count')
            ->selectRaw('coalesce(tramite_metrics.overdue_tramites_count, 0) as overdue_tramites_count')
            ->selectRaw('coalesce(tramite_metrics.due_soon_tramites_count, 0) as due_soon_tramites_count')
            ->selectRaw('tramite_metrics.next_deadline_date as next_deadline_date');
    }

    protected function formatOperacion(Operacion $record): array
    {
        $isPending = blank($record->status) || $record->status === Operacion::STATUS_PENDING;

        return [
            'reference' => $record->reference,
            'cliente' => $record->cliente?->fullName() ?: 'Sin cliente',
            'location' => collect([$record->city, $record->province])->filter()->implode(' - '),
            'operation_date' => $this->formatDate($record->operation_date),
            'schedule' => $record->estimated_filming_schedule,
            'state' => $isPending ? 'Pendiente de confirmar' : $this->tramitesState($record),
            'tone' => $isPending ? 'warning' : $this->tramitesTone($record),
            'description' => $isPending ? 'Revisar y confirmar con el cliente' : $this->tramitesDescription($record),
            'next_deadline' => $record->next_deadline_date
                ? $this->formatDate($record->next_deadline_date)
                : null,
            'pilot' => $record->piloto?->fullName(),
            'drone' => $record->dron?->displayName(),
            'url' => OperacionResource::getUrl('view', [
                'record' => $record,
                'focus' => $isPending ? 'pendiente-confirmar' : 'operacion-hoy',
            ]),
        ];
    }

    protected function priorityOrderSql(): string
    {
        $pendingStatus = $this->quoteSqlLiteral(Operacion::STATUS_PENDING);

        return <<<SQL
            case
                when status is null or status = {$pendingStatus} then 0
                when coalesce(tramite_metrics.tramites_count, 0) = 0 then 1
                when coalesce(tramite_metrics.overdue_tramites_count, 0) > 0 then 2
                when coalesce(tramite_metrics.due_soon_tramites_count, 0) > 0 then 3
                when coalesce(tramite_metrics.pending_to_process_tramites_count, 0) > 0 then 4
                when coalesce(tramite_metrics.approved_tramites_count, 0) < coalesce(tramite_metrics.tramites_count, 0) then 5
                else 6
            end
        SQL;
    }

    protected function quoteSqlLiteral(string $value): string
    {
        return "'".str_replace("'", "''", $value)."'";
    }

    protected function formatDate(mixed $value, string $fallback = 'Sin fecha'): string
    {
        if (blank($value)) {
            return $fallback;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('d/m/Y');
        }

        return Carbon::parse($value)->format('d/m/Y');
    }

    protected function tramitesState(Operacion $record): string
    {
        if ((int) ($record->tramites_count ?? 0) === 0) {
            return 'Sin trámites';
        }

        if ((int) ($record->overdue_tramites_count ?? 0) > 0) {
            return $record->overdue_tramites_count.' vencidos';
        }

        if ((int) ($record->due_soon_tramites_count ?? 0) > 0) {
            return $record->due_soon_tramites_count.' vencen en 7 días';
        }

        if ((int) ($record->pending_to_process_tramites_count ?? 0) > 0) {
            return $record->pending_to_process_tramites_count.' pendientes';
        }

        if ((int) ($record->approved_tramites_count ?? 0) < (int) ($record->tramites_count ?? 0)) {
            return 'Falta cerrar';
        }

        return 'Completa';
    }

    protected function tramitesTone(Operacion $record): string
    {
        if ((int) ($record->tramites_count ?? 0) === 0) {
            return 'danger';
        }

        if ((int) ($record->overdue_tramites_count ?? 0) > 0) {
            return 'danger';
        }

        if ((int) ($record->due_soon_tramites_count ?? 0) > 0) {
            return 'warning';
        }

        if ((int) ($record->pending_to_process_tramites_count ?? 0) > 0) {
            return 'info';
        }

        if ((int) ($record->approved_tramites_count ?? 0) < (int) ($record->tramites_count ?? 0)) {
            return 'info';
        }

        return 'success';
    }

    protected function tramitesDescription(Operacion $record): string
    {
        return 'Total '.(int) ($record->tramites_count ?? 0)
            .' - Aprobados '.(int) ($record->approved_tramites_count ?? 0)
            .' - Pendientes de tramitar '.(int) ($record->pending_to_process_tramites_count ?? 0);
    }
}
