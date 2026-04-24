<?php

namespace App\Filament\Resources\Clientes\RelationManagers;

use App\Filament\Resources\Operaciones\OperacionResource;
use App\Models\Operacion;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class OperacionesRelationManager extends RelationManager
{
    protected static string $relationship = 'operaciones';

    protected static ?string $title = 'Operaciones';

    protected function pilotoOptions(): array
    {
        return $this->getOwnerRecord()
            ->pilotos()
            ->get()
            ->mapWithKeys(fn ($piloto): array => [$piloto->id => $piloto->fullName()])
            ->all();
    }

    protected function dronOptions(): array
    {
        return $this->getOwnerRecord()
            ->drones()
            ->get()
            ->mapWithKeys(function ($dron): array {
                $label = trim(($dron->manufacturer_name ?? '').' '.($dron->model ?? ''));

                if (filled($dron->registration_number) || $dron->registration_not_applicable) {
                    $label .= ' - '.$dron->registrationLabel();
                }

                return [$dron->id => $label ?: 'Dron #'.$dron->id];
            })
            ->all();
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
        return $schema
            ->components([
                Select::make('dron_id')
                    ->label('Dron')
                    ->options(fn (): array => $this->dronOptions())
                    ->searchable()
                    ->required()
                    ->native(false),

                Select::make('piloto_id')
                    ->label('Piloto')
                    ->options(fn (): array => $this->pilotoOptions())
                    ->searchable()
                    ->required()
                    ->native(false),

                TextInput::make('reference')
                    ->label('Nombre de la operacion')
                    ->required()
                    ->maxLength(255),

                TextInput::make('operation_date')
                    ->label('Fecha de la operacion')
                    ->type('date')
                    ->required(),

                TextInput::make('estimated_filming_schedule')
                    ->label('Horario de rodaje estimado')
                    ->required()
                    ->maxLength(255),

                TextInput::make('google_maps_link')
                    ->label('Link Google Maps')
                    ->url()
                    ->maxLength(255),

                TextInput::make('address')
                    ->label('Direccion completa')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),

                TextInput::make('country')
                    ->label('Pais')
                    ->required()
                    ->maxLength(255),

                TextInput::make('city')
                    ->label('Ciudad')
                    ->required()
                    ->maxLength(255),

                TextInput::make('province')
                    ->label('Provincia')
                    ->required()
                    ->maxLength(255),

                TextInput::make('postal_code')
                    ->label('Codigo postal')
                    ->required()
                    ->maxLength(20),

                TextInput::make('altitude')
                    ->label('Altitud')
                    ->numeric()
                    ->minValue(0)
                    ->suffix('m')
                    ->required(),

                TextInput::make('operation_radius')
                    ->label('Radio operacion')
                    ->numeric()
                    ->minValue(0)
                    ->suffix('m')
                    ->required(),

                TextInput::make('video_objective')
                    ->label('Objetivo del video que se va a grabar')
                    ->maxLength(255),

                TextInput::make('end_client')
                    ->label('Cliente final')
                    ->maxLength(255),

                TextInput::make('production_company_name')
                    ->label('Nombre de la productora')
                    ->maxLength(255),

                TextInput::make('production_contact_phone')
                    ->label('Telefono de la productora o contacto en set')
                    ->maxLength(255),

                Select::make('environment_type')
                    ->label('Interior o exterior')
                    ->options([
                        'interior' => 'Interior',
                        'exterior' => 'Exterior',
                    ])
                    ->native(false),

                Select::make('people_present')
                    ->label('Hay gente')
                    ->options([
                        true => 'Si',
                        false => 'No',
                    ])
                    ->native(false),

                Textarea::make('prior_permits_notes')
                    ->label('Permisos previos necesarios')
                    ->rows(3)
                    ->columnSpanFull(),

                Textarea::make('extra_information')
                    ->label('Observaciones legacy')
                    ->rows(4)
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['piloto', 'dron']))
            ->columns([
                TextColumn::make('reference')
                    ->label('Operacion')
                    ->searchable()
                    ->weight('semibold'),

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
            ])
            ->defaultSort('created_at', 'desc')
            ->headerActions([
                CreateAction::make()
                    ->label('Anadir operacion')
                    ->mutateDataUsing(function (array $data): array {
                        $data['location'] = $data['address'] ?? null;
                        $data['description'] = $data['prior_permits_notes'] ?? ($data['extra_information'] ?? null);

                        return $data;
                    }),
            ])
            ->recordActions([
                Action::make('gestionar')
                    ->label('Gestionar')
                    ->icon('heroicon-m-eye')
                    ->url(fn (Operacion $record): string => OperacionResource::getUrl('view', ['record' => $record])),
                EditAction::make()
                    ->label('Editar')
                    ->mutateDataUsing(function (array $data): array {
                        $data['location'] = $data['address'] ?? null;
                        $data['description'] = $data['prior_permits_notes'] ?? ($data['extra_information'] ?? null);

                        return $data;
                    }),
                DeleteAction::make(),
            ])
            ->recordAction('gestionar');
    }
}
