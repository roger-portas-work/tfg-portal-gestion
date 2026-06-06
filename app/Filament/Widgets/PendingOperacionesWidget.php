<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Operaciones\OperacionResource;
use App\Filament\Widgets\Support\DashboardOperationWindow;
use App\Models\Operacion;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class PendingOperacionesWidget extends Widget
{
    protected string $view = 'filament.widgets.pending-operaciones-widget';

    protected int | string | array $columnSpan = 'full';

    protected static ?int $sort = 5;

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $today = Carbon::today(config('app.timezone'))->toDateString();
        $window = DashboardOperationWindow::selected();

        $operaciones = DashboardOperationWindow::applyToQuery(Operacion::query())
            ->with(['cliente', 'piloto', 'dron'])
            ->where(function (Builder $query): void {
                $query
                    ->whereNull('status')
                    ->orWhere('status', Operacion::STATUS_PENDING);
            })
            ->orderByRaw('case when operation_date = '.$this->quoteSqlLiteral($today).' then 0 else 1 end')
            ->orderBy('operation_date')
            ->latest('created_at')
            ->limit(6)
            ->get()
            ->map(fn (Operacion $operacion): array => [
                'reference' => $operacion->reference,
                'cliente' => $operacion->cliente?->fullName() ?: 'Sin cliente',
                'location' => collect([$operacion->city, $operacion->province])->filter()->implode(' - '),
                'operation_date' => $this->formatDate($operacion->operation_date),
                'is_today' => $this->isSameDate($operacion->operation_date, $today),
                'schedule' => $operacion->estimated_filming_schedule,
                'pilot' => $operacion->piloto?->fullName(),
                'drone' => $operacion->dron?->displayName(),
                'url' => OperacionResource::getUrl('view', [
                    'record' => $operacion,
                    'focus' => 'pendiente-confirmar',
                ]),
            ]);

        return [
            'operaciones' => $operaciones,
            'indexUrl' => OperacionResource::getUrl('index'),
            'operationWindow' => $window,
        ];
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

    protected function quoteSqlLiteral(string $value): string
    {
        return "'".str_replace("'", "''", $value)."'";
    }
}
