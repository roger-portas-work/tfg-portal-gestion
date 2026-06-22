<?php

namespace App\Filament\Resources\Clientes\RelationManagers;

use App\Filament\Resources\Operaciones\OperacionResource;
use App\Models\OperacionTramite;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class OperacionTramitesRelationManager extends RelationManager
{
    protected static string $relationship = 'operacionTramites';

    protected static ?string $title = 'Tramites de operaciones vigentes';

    protected static bool $isLazy = false;

    public function isReadOnly(): bool
    {
        return true;
    }

    protected static function todayDate(): string
    {
        return Carbon::today(config('app.timezone'))->toDateString();
    }

    public static function applyCurrentOperationsQuery(Builder $query): Builder
    {
        return $query->whereHas('operacion', fn (Builder $query): Builder => $query
            ->activeForGestor());
    }

    public static function applyCurrentWorkflowQuery(Builder $query): Builder
    {
        $processedAt = $query->getModel()->qualifyColumn('processed_at');
        $deadlineDate = $query->getModel()->qualifyColumn('deadline_date');
        $id = $query->getModel()->qualifyColumn('id');

        return static::applyCurrentOperationsQuery($query)
            ->orderByRaw("case when {$processedAt} is null then 0 else 1 end")
            ->orderBy($deadlineDate)
            ->orderByDesc($processedAt)
            ->orderBy($id);
    }

    public static function applyOverdueQuery(Builder $query): Builder
    {
        $processedAt = $query->getModel()->qualifyColumn('processed_at');
        $deadlineDate = $query->getModel()->qualifyColumn('deadline_date');
        $status = $query->getModel()->qualifyColumn('status');
        $id = $query->getModel()->qualifyColumn('id');

        return static::applyCurrentOperationsQuery($query)
            ->whereNull($processedAt)
            ->where($status, '!=', OperacionTramite::STATUS_APPROVED)
            ->whereDate($deadlineDate, '<', static::todayDate())
            ->orderBy($deadlineDate)
            ->orderBy($id);
    }

    public static function applyPendingQuery(Builder $query): Builder
    {
        $processedAt = $query->getModel()->qualifyColumn('processed_at');
        $deadlineDate = $query->getModel()->qualifyColumn('deadline_date');
        $status = $query->getModel()->qualifyColumn('status');
        $id = $query->getModel()->qualifyColumn('id');

        return static::applyCurrentOperationsQuery($query)
            ->where($status, OperacionTramite::STATUS_PENDING)
            ->whereNull($processedAt)
            ->whereNotNull($deadlineDate)
            ->whereDate($deadlineDate, '>=', static::todayDate())
            ->orderBy($deadlineDate)
            ->orderBy($id);
    }

    public static function applyProcessedQuery(Builder $query): Builder
    {
        $processedAt = $query->getModel()->qualifyColumn('processed_at');
        $id = $query->getModel()->qualifyColumn('id');

        return static::applyCurrentOperationsQuery($query)
            ->whereNotNull($processedAt)
            ->orderByDesc($processedAt)
            ->orderByDesc($id);
    }

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        return [
            'todos' => Tab::make('Todos vigentes')
                ->modifyQueryUsing(fn (Builder $query): Builder => static::applyCurrentWorkflowQuery($query)),

            'vencidos' => Tab::make('Vencidos')
                ->modifyQueryUsing(fn (Builder $query): Builder => static::applyOverdueQuery($query)),

            'pendientes' => Tab::make('Pendientes')
                ->modifyQueryUsing(fn (Builder $query): Builder => static::applyPendingQuery($query)),

            'tramitados' => Tab::make('Tramitados')
                ->modifyQueryUsing(fn (Builder $query): Builder => static::applyProcessedQuery($query)),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->with([
                    'operacion.piloto',
                    'operacion.dron',
                ]))
            ->columns([
                TextColumn::make('title')
                    ->label('Tramite')
                    ->searchable()
                    ->sortable()
                    ->weight('semibold')
                    ->description(fn (OperacionTramite $record): ?string => filled($record->request_code)
                        ? 'Codigo '.$record->request_code
                        : null),

                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (OperacionTramite $record): string => $record->statusColor())
                    ->formatStateUsing(fn (?string $state): string => OperacionTramite::statusOptions()[$state] ?? 'Sin definir'),

                TextColumn::make('deadline_date')
                    ->label('Fecha limite')
                    ->state(fn (OperacionTramite $record): mixed => $record->isProcessedForGestor() ? null : $record->deadline_date)
                    ->date('d/m/Y')
                    ->sortable()
                    ->placeholder(fn (OperacionTramite $record): string => $record->isProcessedForGestor() ? 'No aplica' : 'Sin definir'),

                TextColumn::make('deadline_countdown')
                    ->label('Dias restantes')
                    ->badge()
                    ->state(fn (OperacionTramite $record): string => $record->deadlineCountdownLabel())
                    ->color(fn (OperacionTramite $record): string => $record->deadlineCountdownColor()),

                TextColumn::make('processed_at')
                    ->label('Fecha tramitacion')
                    ->date('d/m/Y')
                    ->sortable()
                    ->placeholder('Sin definir'),

                TextColumn::make('operacion_reference')
                    ->label('Operacion')
                    ->state(fn (OperacionTramite $record): string => $record->operacion?->reference ?: 'Sin operacion')
                    ->description(fn (OperacionTramite $record): ?string => static::operationDescription($record))
                    ->searchable(query: function (Builder $query, string $search): void {
                        $query->whereHas('operacion', fn (Builder $operationQuery) => $operationQuery
                            ->where('reference', 'like', "%{$search}%")
                            ->orWhere('city', 'like', "%{$search}%")
                            ->orWhere('province', 'like', "%{$search}%"));
                    }),

                TextColumn::make('attachments_count')
                    ->label('PDFs')
                    ->badge()
                    ->color('gray')
                    ->state(fn (OperacionTramite $record): string => (string) count($record->attachments ?? [])),
            ])
            ->emptyStateHeading('Sin tramites vigentes')
            ->emptyStateDescription('No hay tramites pendientes ni tramitados en operaciones activas.')
            ->recordActions([
                Action::make('abrirOperacion')
                    ->label('Abrir operacion')
                    ->icon('heroicon-m-arrow-top-right-on-square')
                    ->url(fn (OperacionTramite $record): ?string => static::operationUrl($record)),
            ])
            ->recordUrl(fn (OperacionTramite $record): ?string => static::operationUrl($record));
    }

    protected static function operationDescription(OperacionTramite $record): ?string
    {
        $operation = $record->operacion;

        if (! $operation) {
            return null;
        }

        $date = $operation->operation_date instanceof \DateTimeInterface
            ? $operation->operation_date->format('d/m/Y')
            : (filled($operation->operation_date) ? Carbon::parse($operation->operation_date)->format('d/m/Y') : 'Sin fecha');

        $location = collect([$operation->city, $operation->province])->filter()->implode(' - ');

        return collect([$date, $location])->filter()->implode(' - ');
    }

    protected static function operationUrl(OperacionTramite $record): ?string
    {
        if (! $record->operacion) {
            return null;
        }

        return OperacionResource::getUrl('view', [
            'record' => $record->operacion,
            'focus' => 'tramite',
            'tramite' => $record->getKey(),
        ]);
    }
}
