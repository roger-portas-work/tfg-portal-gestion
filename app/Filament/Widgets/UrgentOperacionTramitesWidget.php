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

        $tramites = OperacionTramite::query()
            ->with('operacion.cliente')
            ->whereNull('processed_at')
            ->where('status', '!=', OperacionTramite::STATUS_APPROVED)
            ->whereBetween('deadline_date', [$today, $dueUntil])
            ->whereHas('operacion', fn (Builder $query) => $query
                ->where('status', Operacion::STATUS_CONFIRMED))
            ->orderBy('deadline_date')
            ->limit(8)
            ->get()
            ->map(fn (OperacionTramite $tramite): array => [
                'title' => $tramite->title,
                'operation' => $tramite->operacion?->reference ?: 'Sin operación',
                'cliente' => $tramite->operacion?->cliente?->fullName() ?: 'Sin cliente',
                'deadline' => $this->formatDate($tramite->deadline_date),
                'description' => $this->deadlineDescription($tramite, $today),
                'operation_date' => $this->formatDate($tramite->operacion?->operation_date, null),
                'url' => OperacionResource::getUrl('view', [
                    'record' => $tramite->operacion_id,
                    'focus' => 'tramite-7-dias',
                    'tramite' => $tramite->id,
                ]),
            ]);

        return [
            'tramites' => $tramites,
        ];
    }

    protected function deadlineDescription(OperacionTramite $record, string $today): string
    {
        if (! $record->deadline_date) {
            return 'Sin fecha límite';
        }

        $days = Carbon::parse($today)->diffInDays($record->deadline_date, false);

        if ((int) $days === 0) {
            return 'Vence hoy';
        }

        return 'Faltan '.(int) $days.' días';
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
