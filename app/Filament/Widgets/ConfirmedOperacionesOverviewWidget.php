<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Operaciones\OperacionResource;
use App\Filament\Widgets\Support\DashboardOperationWindow;
use App\Models\Operacion;
use App\Models\OperacionTramite;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class ConfirmedOperacionesOverviewWidget extends Widget
{
    protected string $view = 'filament.widgets.confirmed-operaciones-overview-widget';

    protected int | string | array $columnSpan = 'full';

    protected static ?int $sort = 3;

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $today = Carbon::today(config('app.timezone'))->toDateString();
        $dueUntil = Carbon::today(config('app.timezone'))->addDays(7)->toDateString();
        $window = DashboardOperationWindow::selected();

        $operaciones = $this->withTramiteMetrics(
            DashboardOperationWindow::applyToQuery(Operacion::query()),
            $today,
            $dueUntil
        )
            ->with(['cliente', 'piloto', 'dron'])
            ->where('status', Operacion::STATUS_CONFIRMED)
            ->orderByRaw('case when operation_date = '.$this->quoteSqlLiteral($today).' then 0 else 1 end')
            ->orderByRaw($this->priorityOrderSql())
            ->orderBy('operation_date')
            ->limit(6)
            ->get()
            ->map(fn (Operacion $operacion): array => $this->formatOperacion($operacion));

        return [
            'operaciones' => $operaciones,
            'operationWindow' => $window,
        ];
    }

    protected function withTramiteMetrics(Builder $query, string $today, string $dueUntil): Builder
    {
        $approvedStatus = OperacionTramite::STATUS_APPROVED;
        $deniedStatus = OperacionTramite::STATUS_DENIED;
        $metrics = OperacionTramite::query()
            ->select('operacion_id')
            ->selectRaw('count(*) as tramites_count')
            ->selectRaw('sum(case when status = ? then 1 else 0 end) as approved_tramites_count', [$approvedStatus])
            ->selectRaw('sum(case when status = ? then 1 else 0 end) as denied_tramites_count', [$deniedStatus])
            ->selectRaw('sum(case when processed_at is not null then 1 else 0 end) as processed_for_gestor_tramites_count')
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
            ->selectRaw('coalesce(tramite_metrics.denied_tramites_count, 0) as denied_tramites_count')
            ->selectRaw('coalesce(tramite_metrics.processed_for_gestor_tramites_count, 0) as processed_for_gestor_tramites_count')
            ->selectRaw('coalesce(tramite_metrics.pending_to_process_tramites_count, 0) as pending_to_process_tramites_count')
            ->selectRaw('coalesce(tramite_metrics.overdue_tramites_count, 0) as overdue_tramites_count')
            ->selectRaw('coalesce(tramite_metrics.due_soon_tramites_count, 0) as due_soon_tramites_count')
            ->selectRaw('tramite_metrics.next_deadline_date as next_deadline_date');
    }

    protected function formatOperacion(Operacion $record): array
    {
        $focus = $record->gestorFollowUpFocus();

        return [
            'reference' => $record->reference,
            'cliente' => $record->cliente?->fullName() ?: 'Sin cliente',
            'location' => collect([$record->city, $record->province])->filter()->implode(' - '),
            'operation_date' => $this->formatDate($record->operation_date),
            'is_today' => $this->isSameDate($record->operation_date, Carbon::today(config('app.timezone'))->toDateString()),
            'schedule' => $record->estimated_filming_schedule,
            'state' => $record->gestorFollowUpLabel(),
            'tone' => $record->gestorFollowUpColor(),
            'description' => $record->gestorFollowUpDescription(),
            'next_deadline' => $record->next_deadline_date
                ? $this->formatDate($record->next_deadline_date)
                : null,
            'pilot' => $record->piloto?->fullName(),
            'drone' => $record->dron?->displayName(),
            'url' => OperacionResource::getUrl('view', [
                'record' => $record,
                'focus' => $focus,
            ]),
        ];
    }

    protected function priorityOrderSql(): string
    {
        return <<<SQL
            case
                when coalesce(tramite_metrics.tramites_count, 0) = 0 then 0
                when coalesce(tramite_metrics.overdue_tramites_count, 0) > 0 then 1
                when coalesce(tramite_metrics.due_soon_tramites_count, 0) > 0 then 2
                when coalesce(tramite_metrics.pending_to_process_tramites_count, 0) > 0 then 3
                when coalesce(tramite_metrics.denied_tramites_count, 0) > 0 then 4
                when coalesce(tramite_metrics.processed_for_gestor_tramites_count, 0) = coalesce(tramite_metrics.tramites_count, 0)
                    and coalesce(tramite_metrics.approved_tramites_count, 0) < coalesce(tramite_metrics.tramites_count, 0) then 5
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

    protected function isSameDate(mixed $value, string $date): bool
    {
        if (blank($value)) {
            return false;
        }

        return Carbon::parse($value)->toDateString() === $date;
    }
}
