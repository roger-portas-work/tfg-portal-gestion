<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Support\DashboardOperationWindow;
use App\Models\Dron;
use App\Models\Operacion;
use App\Models\OperacionTramite;
use App\Models\OperadoraRequirement;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class GestorPriorityStatsWidget extends Widget
{
    protected string $view = 'filament.widgets.gestor-priority-stats-widget';

    protected int | string | array $columnSpan = 'full';

    protected static ?int $sort = 1;

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $today = Carbon::today(config('app.timezone'))->toDateString();
        $dueUntil = Carbon::today(config('app.timezone'))->addDays(7)->toDateString();
        $window = DashboardOperationWindow::selected();

        $operationsToday = Operacion::query()
            ->whereDate('operation_date', $today)
            ->where(function (Builder $query): void {
                $query
                    ->whereNull('status')
                    ->orWhere('status', '!=', Operacion::STATUS_REJECTED);
            })
            ->count();

        $confirmedUpcoming = DashboardOperationWindow::applyToQuery(Operacion::query())
            ->where('status', Operacion::STATUS_CONFIRMED)
            ->count();

        $confirmedWithoutTramites = DashboardOperationWindow::applyToQuery(Operacion::query())
            ->where('status', Operacion::STATUS_CONFIRMED)
            ->doesntHave('tramites')
            ->count();

        $confirmedWithPendingTramites = DashboardOperationWindow::applyToQuery(Operacion::query())
            ->where('status', Operacion::STATUS_CONFIRMED)
            ->whereHas('tramites', fn (Builder $query): Builder => $query
                ->where('status', '!=', OperacionTramite::STATUS_APPROVED))
            ->count();

        $overdueTramites = $this->pendingTramitesQuery()
            ->whereDate('deadline_date', '<', $today)
            ->count();

        $dueSoonTramites = $this->pendingTramitesQuery()
            ->whereBetween('deadline_date', [$today, $dueUntil])
            ->count();

        $pendingOperations = DashboardOperationWindow::applyToQuery(Operacion::query())
            ->where(function (Builder $query): void {
                $query
                    ->whereNull('status')
                    ->orWhere('status', Operacion::STATUS_PENDING);
            })
            ->count();

        $requirementsInReview = OperadoraRequirement::query()
            ->where('status', OperadoraRequirement::STATUS_IN_REVIEW)
            ->count();

        $aesaRequests = Dron::query()
            ->where('aesa_registration_status', Dron::AESA_STATUS_MANAGER)
            ->count();

        return [
            'operationWindow' => $window,
            'operationFilterOptions' => DashboardOperationWindow::filterOptions(),
            'items' => [
                [
                    'label' => 'Hoy',
                    'value' => $operationsToday,
                    'description' => 'Operaciones con fecha de hoy',
                    'tone' => $operationsToday > 0 ? 'warning' : 'neutral',
                    'url' => '#operaciones-hoy',
                ],
                [
                    'label' => 'Operaciones confirmadas',
                    'value' => $confirmedUpcoming,
                    'description' => $this->confirmedOperationsDescription(
                        $confirmedWithoutTramites,
                        $confirmedWithPendingTramites,
                        $window['description']
                    ),
                    'tone' => $this->confirmedOperationsTone(
                        $confirmedWithoutTramites,
                        $confirmedWithPendingTramites
                    ),
                    'url' => '#operaciones-confirmadas-vista',
                ],
                [
                    'label' => 'Tramites vencidos',
                    'value' => $overdueTramites,
                    'description' => 'Fecha limite anterior a hoy',
                    'tone' => $overdueTramites > 0 ? 'danger' : 'success',
                    'url' => '#tramites-vencidos',
                ],
                [
                    'label' => 'Fecha limite tramitacion vence en 7 dias',
                    'value' => $dueSoonTramites,
                    'description' => 'Desde hoy hacia adelante',
                    'tone' => $dueSoonTramites > 0 ? 'warning' : 'success',
                    'url' => '#tramites-7-dias',
                ],
                [
                    'label' => 'Operaciones por confirmar',
                    'value' => $pendingOperations,
                    'description' => 'Pendientes de decisión',
                    'tone' => $pendingOperations > 0 ? 'warning' : 'neutral',
                    'url' => '#operaciones-por-confirmar',
                ],
                [
                    'label' => 'Operadora',
                    'value' => $requirementsInReview + $aesaRequests,
                    'description' => "{$requirementsInReview} requisitos - {$aesaRequests} AESA",
                    'tone' => ($requirementsInReview + $aesaRequests) > 0 ? 'info' : 'neutral',
                    'url' => '#operadora-revision',
                ],
            ],
        ];
    }

    protected function confirmedOperationsDescription(
        int $withoutTramites,
        int $withPendingTramites,
        string $fallback
    ): string {
        if ($withoutTramites > 0 && $withPendingTramites > 0) {
            return "{$withoutTramites} sin tramites - {$withPendingTramites} con tramites pendientes de aprobar";
        }

        if ($withoutTramites > 0) {
            return "{$withoutTramites} sin tramites";
        }

        if ($withPendingTramites > 0) {
            return "{$withPendingTramites} con tramites pendientes de aprobar";
        }

        return $fallback;
    }

    protected function confirmedOperationsTone(int $withoutTramites, int $withPendingTramites): string
    {
        if ($withoutTramites > 0) {
            return 'danger';
        }

        if ($withPendingTramites > 0) {
            return 'warning';
        }

        return 'success';
    }

    protected function pendingTramitesQuery(): Builder
    {
        return OperacionTramite::query()
            ->whereNull('processed_at')
            ->where('status', '!=', OperacionTramite::STATUS_APPROVED)
            ->whereHas('operacion', fn (Builder $query) => $query
                ->where('status', Operacion::STATUS_CONFIRMED));
    }
}
