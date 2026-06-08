<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Operaciones\OperacionResource;
use App\Models\Operacion;
use App\Models\OperacionTramite;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class UrgentOperacionTramitesWidget extends Widget
{
    protected string $view = 'filament.widgets.urgent-operacion-tramites-widget';

    protected int | string | array $columnSpan = 'full';

    protected static ?int $sort = 4;

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $today = Carbon::today(config('app.timezone'))->toDateString();
        $dueUntil = Carbon::today(config('app.timezone'))->addDays(7)->toDateString();

        $overdueTramites = $this->pendingConfirmedTramitesQuery()
            ->whereDate('deadline_date', '<', $today)
            ->orderBy('deadline_date')
            ->limit(8)
            ->get()
            ->map(fn (OperacionTramite $tramite): array => $this->formatTramite($tramite, 'tramites-vencidos'));

        $dueSoonTramites = $this->pendingConfirmedTramitesQuery()
            ->whereBetween('deadline_date', [$today, $dueUntil])
            ->orderBy('deadline_date')
            ->limit(8)
            ->get()
            ->map(fn (OperacionTramite $tramite): array => $this->formatTramite($tramite, 'tramite-7-dias'));

        return [
            'overdueTramites' => $overdueTramites,
            'dueSoonTramites' => $dueSoonTramites,
        ];
    }

    protected function pendingConfirmedTramitesQuery(): Builder
    {
        return OperacionTramite::query()
            ->with('operacion.cliente')
            ->whereNull('processed_at')
            ->where('status', '!=', OperacionTramite::STATUS_APPROVED)
            ->whereHas('operacion', fn (Builder $query) => $query
                ->where('status', Operacion::STATUS_CONFIRMED));
    }

    /**
     * @return array<string, mixed>
     */
    protected function formatTramite(OperacionTramite $tramite, string $focus): array
    {
        return [
            'title' => $tramite->title,
            'operation' => $tramite->operacion?->reference ?: 'Sin operacion',
            'cliente' => $tramite->operacion?->cliente?->fullName() ?: 'Sin cliente',
            'deadline' => $this->formatDate($tramite->deadline_date),
            'description' => $tramite->deadlineCountdownLabel(),
            'operation_date' => $this->formatDate($tramite->operacion?->operation_date, null),
            'url' => OperacionResource::getUrl('view', [
                'record' => $tramite->operacion_id,
                'focus' => $focus,
                'tramite' => $tramite->id,
            ]),
        ];
    }

    protected function formatDate(mixed $value, ?string $fallback = 'Sin fecha'): ?string
    {
        if (blank($value)) {
            return $fallback;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('d/m/Y');
        }

        return Carbon::parse($value)->format('d/m/Y');
    }
}
