<?php

namespace App\Filament\Resources\Tramites;

use App\Filament\Resources\Operaciones\OperacionResource;
use App\Filament\Resources\Tramites\Pages\ListTramites;
use App\Models\OperacionTramite;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Fieldset;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class TramiteResource extends Resource
{
    protected static ?string $model = OperacionTramite::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-document-check';

    protected static ?string $navigationLabel = 'Tramites';

    protected static ?string $modelLabel = 'Tramite';

    protected static ?string $pluralModelLabel = 'Tramites';

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?string $slug = 'tramites';

    protected static ?int $navigationSort = 3;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with([
                'operacion.cliente',
                'operacion.piloto',
                'operacion.dron',
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->with([
                    'operacion.cliente',
                    'operacion.piloto',
                    'operacion.dron',
                ]))
            ->heading('Seguimiento de tramites')
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
                    ->date('d/m/Y')
                    ->sortable()
                    ->placeholder('Sin definir'),

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

                TextColumn::make('cliente_name')
                    ->label('Cliente')
                    ->state(fn (OperacionTramite $record): string => $record->operacion?->cliente?->fullName() ?: 'Sin cliente')
                    ->searchable(query: function (Builder $query, string $search): void {
                        $query->whereHas('operacion.cliente', fn (Builder $clienteQuery) => $clienteQuery
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('second_last_name', 'like', "%{$search}%"));
                    }),

                TextColumn::make('attachments_count')
                    ->label('PDFs')
                    ->badge()
                    ->color('gray')
                    ->state(fn (OperacionTramite $record): string => (string) count($record->attachments ?? [])),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Estado tramite')
                    ->options(OperacionTramite::statusOptions()),

                Filter::make('deadline_range')
                    ->label('Fecha limite')
                    ->form([
                        Fieldset::make('Fecha limite')
                            ->schema([
                                DatePicker::make('deadline_from')
                                    ->label('Desde')
                                    ->displayFormat('d/m/Y')
                                    ->native(false)
                                    ->closeOnDateSelection(),
                                DatePicker::make('deadline_until')
                                    ->label('Hasta')
                                    ->displayFormat('d/m/Y')
                                    ->native(false)
                                    ->closeOnDateSelection(),
                            ])
                            ->columns(1),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                filled($data['deadline_from'] ?? null),
                                fn (Builder $query): Builder => $query->whereDate('deadline_date', '>=', $data['deadline_from'])
                            )
                            ->when(
                                filled($data['deadline_until'] ?? null),
                                fn (Builder $query): Builder => $query->whereDate('deadline_date', '<=', $data['deadline_until'])
                            );
                    }),

                Filter::make('processed_range')
                    ->label('Fecha tramitacion')
                    ->form([
                        Fieldset::make('Fecha tramitacion')
                            ->schema([
                                DatePicker::make('processed_from')
                                    ->label('Desde')
                                    ->displayFormat('d/m/Y')
                                    ->native(false)
                                    ->closeOnDateSelection(),
                                DatePicker::make('processed_until')
                                    ->label('Hasta')
                                    ->displayFormat('d/m/Y')
                                    ->native(false)
                                    ->closeOnDateSelection(),
                            ])
                            ->columns(1),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                filled($data['processed_from'] ?? null),
                                fn (Builder $query): Builder => $query->whereDate('processed_at', '>=', $data['processed_from'])
                            )
                            ->when(
                                filled($data['processed_until'] ?? null),
                                fn (Builder $query): Builder => $query->whereDate('processed_at', '<=', $data['processed_until'])
                            );
                    }),
            ])
            ->recordActions([
                Action::make('abrirOperacion')
                    ->label('Abrir operacion')
                    ->icon('heroicon-m-arrow-top-right-on-square')
                    ->url(fn (OperacionTramite $record): ?string => static::operationUrl($record)),
            ])
            ->recordAction('abrirOperacion');
    }

    public static function applyPendingTabQuery(Builder $query): Builder
    {
        return $query
            ->where('status', OperacionTramite::STATUS_PENDING)
            ->whereNull('processed_at')
            ->whereNotNull('deadline_date')
            ->orderBy('deadline_date')
            ->orderBy('id');
    }

    public static function applyOverdueTabQuery(Builder $query): Builder
    {
        return $query
            ->whereNull('processed_at')
            ->where('status', '!=', OperacionTramite::STATUS_APPROVED)
            ->whereDate('deadline_date', '<', Carbon::today(config('app.timezone'))->toDateString())
            ->orderBy('deadline_date')
            ->orderBy('id');
    }

    public static function applyProcessedTabQuery(Builder $query): Builder
    {
        return $query
            ->where('status', OperacionTramite::STATUS_PROCESSED)
            ->whereNotNull('processed_at')
            ->orderBy('processed_at')
            ->orderBy('id');
    }

    public static function applyHistoryTabQuery(Builder $query): Builder
    {
        return $query
            ->orderByDesc('created_at')
            ->orderByDesc('id');
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

    public static function getPages(): array
    {
        return [
            'index' => ListTramites::route('/'),
        ];
    }
}
