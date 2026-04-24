<?php

namespace App\Filament\Resources\Clientes\RelationManagers;

use App\Models\Dron;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DronesRelationManager extends RelationManager
{
    protected static string $relationship = 'drones';

    protected static ?string $title = 'Drones';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Datos del dron')
                    ->schema([
                        Select::make('uas_class')
                            ->label('Clase de UAS')
                            ->options(Dron::uasClassOptions())
                            ->required()
                            ->native(false),

                        TextInput::make('manufacturer_name')
                            ->label('Nombre del fabricante')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('model')
                            ->label('Modelo')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('drone_serial_number')
                            ->label('Numero de serie del dron')
                            ->maxLength(255),

                        TextInput::make('controller_serial_number')
                            ->label('Numero de serie de la controladora')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('registration_number')
                            ->label('Matricula')
                            ->maxLength(255),

                        TextInput::make('mtom_weight')
                            ->label('Peso MTOM (g)')
                            ->numeric()
                            ->minValue(0)
                            ->required(),

                        TextInput::make('remote_id_number')
                            ->label('Numero de ID remoto')
                            ->required()
                            ->maxLength(255),

                        Select::make('class_marking')
                            ->label('Marcado de clase')
                            ->options(Dron::classMarkingOptions())
                            ->required()
                            ->native(false),

                        TextInput::make('band_frequency')
                            ->label('Banda y frecuencia')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('color')
                            ->label('Color')
                            ->required()
                            ->maxLength(255),

                        Textarea::make('payload')
                            ->label('Carga de pago (Camera, microfono, objetos, dispositivos)')
                            ->rows(3),

                        TextInput::make('vhf_equipment')
                            ->label('Equipo de comunicaciones VHF')
                            ->maxLength(255),

                        TextInput::make('emergency_equipment')
                            ->label('Equipo de emergencia')
                            ->maxLength(255),
                    ])
                    ->columns(2),

                Section::make('Seguro')
                    ->schema([
                        TextInput::make('insurance_policy_number')
                            ->label('Numero de poliza del seguro')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('insurance_valid_until')
                            ->label('Fecha de validez')
                            ->type('date')
                            ->required(),

                        TextInput::make('insurance_company_name')
                            ->label('Nombre de la entidad aseguradora')
                            ->required()
                            ->maxLength(255),

                        Select::make('aesa_registration_status')
                            ->label('Registro AESA')
                            ->options(Dron::aesaRegistrationOptions())
                            ->native(false),
                    ])
                    ->columns(2),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount('operaciones'))
            ->columns([
                TextColumn::make('drone_name')
                    ->label('Dron')
                    ->state(fn (Dron $record): string => trim($record->manufacturer_name.' '.$record->model))
                    ->searchable(query: function ($query, string $search): void {
                        $query
                            ->where('manufacturer_name', 'like', "%{$search}%")
                            ->orWhere('model', 'like', "%{$search}%")
                            ->orWhere('registration_number', 'like', "%{$search}%");
                    })
                    ->weight('semibold')
                    ->description(fn (Dron $record): string => 'Matricula: '.$record->registrationLabel()),

                TextColumn::make('uas_class')
                    ->label('Clase')
                    ->state(fn (Dron $record): string => Dron::uasClassOptions()[$record->uas_class] ?? $record->uas_class)
                    ->badge()
                    ->color('info'),

                TextColumn::make('remote_id_number')
                    ->label('ID remoto')
                    ->state(fn (Dron $record): string => $record->remoteIdLabel())
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('mtom_weight')
                    ->label('MTOM')
                    ->suffix(' g'),

                TextColumn::make('aesa_registration_status')
                    ->label('AESA')
                    ->state(fn (Dron $record): string => $record->aesaRegistrationLabel())
                    ->badge()
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('operaciones_count')
                    ->label('Operaciones')
                    ->badge()
                    ->color('warning'),

                TextColumn::make('insurance_company_name')
                    ->label('Aseguradora')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('insurance_policy_number')
                    ->label('Poliza')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('insurance_valid_until')
                    ->label('Validez seguro')
                    ->date('d/m/Y')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->headerActions([
                CreateAction::make()
                    ->label('Anadir dron'),
            ])
            ->recordActions([
                EditAction::make()
                    ->label('Editar')
                    ->hiddenLabel()
                    ->icon('heroicon-m-pencil-square')
                    ->extraAttributes(['class' => 'hidden']),

                DeleteAction::make()
                    ->visible(fn (Dron $record): bool => ! $record->operaciones()->exists()),
            ])
            ->recordAction('edit');
    }
}
