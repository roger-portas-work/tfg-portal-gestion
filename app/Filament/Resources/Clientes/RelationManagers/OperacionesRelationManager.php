<?php

namespace App\Filament\Resources\Clientes\RelationManagers;

use App\Filament\Resources\Operaciones\OperacionResource;
use App\Filament\Resources\Operaciones\Schemas\OperacionForm;
use App\Models\Operacion;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OperacionesRelationManager extends RelationManager
{
    protected static string $relationship = 'operaciones';

    protected static ?string $title = 'Operaciones';

    public static function applyCurrentOperationsQuery(Builder $query): Builder
    {
        return $query
            ->activeForGestor()
            ->orderBy('operation_date')
            ->orderBy('id');
    }

    protected function formatMetric(null|int|float|string $value, string $unit): string
    {
        if (! filled($value)) {
            return 'Sin definir';
        }

        $formatted = rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');

        return $formatted.' '.$unit;
    }

    public function form(Schema $schema): Schema
    {
        return OperacionForm::configure($schema, $this->getOwnerRecord());
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => static::applyCurrentOperationsQuery(
                $query->with(['piloto', 'dron'])->withTramiteWorkflowCounts()
            ))
            ->columns([
                TextColumn::make('reference')
                    ->label('Operacion')
                    ->searchable()
                    ->weight('semibold'),

                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (Operacion $record): string => $record->statusColor())
                    ->formatStateUsing(fn (?string $state): string => Operacion::statusOptions()[$state] ?? 'Pendiente'),

                TextColumn::make('gestor_follow_up')
                    ->label('Seguimiento')
                    ->badge()
                    ->state(fn (Operacion $record): string => $record->gestorFollowUpLabel())
                    ->color(fn (Operacion $record): string => $record->gestorFollowUpColor()),

                TextColumn::make('tramites_count')
                    ->label('N. tramites')
                    ->badge()
                    ->color('gray')
                    ->state(fn (Operacion $record): string => (string) ($record->tramites_count ?? 0))
                    ->alignCenter(),

                TextColumn::make('operation_date')
                    ->label('Fecha')
                    ->date('d/m/Y')
                    ->sortable()
                    ->description(fn (Operacion $record): ?string => $record->created_at
                        ? 'Creada el '.$record->created_at->format('d/m/Y')
                        : null),

                TextColumn::make('piloto_name')
                    ->label('Piloto')
                    ->state(fn (Operacion $record): string => $record->piloto?->fullName() ?? 'Sin piloto')
                    ->searchable(query: function ($query, string $search): void {
                        $query->whereHas('piloto', function ($pilotoQuery) use ($search): void {
                            $pilotoQuery
                                ->where('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%")
                                ->orWhere('second_last_name', 'like', "%{$search}%");
                        });
                    }),

                TextColumn::make('dron_label')
                    ->label('Dron')
                    ->state(function (Operacion $record): string {
                        $label = trim(($record->dron?->manufacturer_name ?? '').' '.($record->dron?->model ?? ''));

                        if ($record->dron && (filled($record->dron->registration_number) || $record->dron->registration_not_applicable)) {
                            $label .= ' - '.$record->dron->registrationLabel();
                        }

                        return $label ?: 'Sin dron';
                    }),

                TextColumn::make('estimated_filming_schedule')
                    ->label('Rodaje estimado')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('operation_cost')
                    ->label('Coste')
                    ->state(fn (Operacion $record): string => filled($record->operation_cost)
                        ? number_format((float) $record->operation_cost, 2, ',', '.').' EUR'
                        : 'Sin definir')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('altitude')
                    ->label('Altitud / Radio')
                    ->state(fn (Operacion $record): string => $this->formatMetric($record->altitude, 'm').' - '.$this->formatMetric($record->operation_radius, 'm'))
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('address')
                    ->label('Direccion')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('country')
                    ->label('Pais')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('city')
                    ->label('Ciudad')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('province')
                    ->label('Provincia')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('postal_code')
                    ->label('Codigo postal')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('video_objective')
                    ->label('Objetivo')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('end_client')
                    ->label('Cliente final')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('production_company_name')
                    ->label('Productora')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('environment_type')
                    ->label('Entorno')
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'interior' => 'Interior',
                        'exterior' => 'Exterior',
                        default => 'Sin definir',
                    })
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('people_present')
                    ->label('Hay gente')
                    ->formatStateUsing(fn ($state): string => match ($state) {
                        true => 'Si',
                        false => 'No',
                        default => 'Sin definir',
                    })
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('operational_conditions')
                    ->label('Condiciones operativas')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('operation_date')
            ->emptyStateHeading('Sin operaciones vigentes')
            ->emptyStateDescription('En esta ficha solo aparecen operaciones activas.')
            ->headerActions([
                CreateAction::make()
                    ->label('Anadir operacion')
                    ->mutateDataUsing(fn (array $data): array => OperacionForm::mutateData($data, $this->getOwnerRecord()->getKey())),
            ])
            ->recordActions([
                Action::make('gestionar')
                    ->label('Gestionar')
                    ->icon('heroicon-m-eye')
                    ->url(fn (Operacion $record): string => OperacionResource::getUrl('view', ['record' => $record])),
                EditAction::make()
                    ->label('Editar')
                    ->mutateDataUsing(fn (array $data): array => OperacionForm::mutateData($data, $this->getOwnerRecord()->getKey())),
                DeleteAction::make()
                    ->visible(fn (Operacion $record): bool => $record->isPending()),
            ])
            ->recordAction('gestionar');
    }
}
