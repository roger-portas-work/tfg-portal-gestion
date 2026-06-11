<?php

namespace App\Filament\Resources\Clientes\RelationManagers;

use App\Models\Dron;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DronesRelationManager extends RelationManager
{
    protected static string $relationship = 'drones';

    protected static ?string $title = 'Drones';

    public function isReadOnly(): bool
    {
        return false;
    }

    protected function buildCoveragePolicyFileName(Dron $record): string
    {
        if (filled($record->insurance_coverage_policy_original_name)) {
            return $record->insurance_coverage_policy_original_name;
        }

        $clienteName = Str::slug($record->cliente?->fullName() ?: 'cliente');
        $dronName = Str::slug($record->displayName());
        $extension = pathinfo($record->insurance_coverage_policy_path ?? 'pdf', PATHINFO_EXTENSION) ?: 'pdf';

        return "dron-{$clienteName}-{$dronName}-seguro.{$extension}";
    }

    protected function downloadCoveragePolicy(Dron $record)
    {
        if (blank($record->insurance_coverage_policy_path) || ! Storage::disk('local')->exists($record->insurance_coverage_policy_path)) {
            Notification::make()
                ->title('Archivo no encontrado')
                ->body('El PDF del seguro ya no existe en el almacenamiento.')
                ->danger()
                ->send();

            return null;
        }

        return response()->download(
            Storage::disk('local')->path($record->insurance_coverage_policy_path),
            $this->buildCoveragePolicyFileName($record)
        );
    }

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
                            ->required()
                            ->maxLength(255),

                        TextInput::make('controller_serial_number')
                            ->label('Numero de serie de la controladora')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('registration_number')
                            ->label('Matricula')
                            ->required(fn ($get): bool => ! (bool) $get('registration_not_applicable'))
                            ->maxLength(255),

                        Toggle::make('registration_not_applicable')
                            ->label('Matricula no aplica')
                            ->live()
                            ->afterStateUpdated(fn (Set $set, bool $state): null => $state ? $set('registration_number', null) : null),

                        TextInput::make('mtom_weight')
                            ->label('Peso MTOM (g)')
                            ->numeric()
                            ->minValue(0)
                            ->required(),

                        TextInput::make('remote_id_number')
                            ->label('Numero de ID remoto')
                            ->required(fn ($get): bool => ! (bool) $get('remote_id_not_applicable'))
                            ->maxLength(255),

                        Toggle::make('remote_id_not_applicable')
                            ->label('ID remoto no aplica')
                            ->live()
                            ->afterStateUpdated(fn (Set $set, bool $state): null => $state ? $set('remote_id_number', null) : null),

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
                            ->required(fn ($get): bool => ! (bool) $get('payload_not_applicable'))
                            ->maxLength(1000)
                            ->rows(3),

                        Toggle::make('payload_not_applicable')
                            ->label('Carga de pago no aplica')
                            ->live()
                            ->afterStateUpdated(fn (Set $set, bool $state): null => $state ? $set('payload', null) : null),

                        TextInput::make('vhf_equipment')
                            ->label('Equipo de comunicaciones VHF')
                            ->required(fn ($get): bool => ! (bool) $get('vhf_not_applicable'))
                            ->maxLength(255),

                        Toggle::make('vhf_not_applicable')
                            ->label('VHF no aplica')
                            ->live()
                            ->afterStateUpdated(fn (Set $set, bool $state): null => $state ? $set('vhf_equipment', null) : null),

                        TextInput::make('emergency_equipment')
                            ->label('Equipo de emergencia')
                            ->required(fn ($get): bool => ! (bool) $get('emergency_not_applicable'))
                            ->maxLength(255),

                        Toggle::make('emergency_not_applicable')
                            ->label('Equipo emergencia no aplica')
                            ->live()
                            ->afterStateUpdated(fn (Set $set, bool $state): null => $state ? $set('emergency_equipment', null) : null),
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

                        FileUpload::make('insurance_coverage_policy_path')
                            ->label('PDF de la poliza')
                            ->acceptedFileTypes(['application/pdf'])
                            ->disk('local')
                            ->directory(fn (): string => 'drones/cliente-'.$this->getOwnerRecord()->getKey().'/seguros')
                            ->maxSize(10240)
                            ->required()
                            ->storeFileNamesIn('insurance_coverage_policy_original_name')
                            ->columnSpanFull(),

                        Select::make('aesa_registration_status')
                            ->label('Registro AESA')
                            ->options(Dron::aesaRegistrationOptions())
                            ->required()
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

                TextColumn::make('operational_status')
                    ->label('Expediente')
                    ->badge()
                    ->state(fn (Dron $record): string => $record->operationalStatusLabel())
                    ->color(fn (Dron $record): string => $record->operationalStatusColor())
                    ->description(fn (Dron $record): ?string => $record->isOperationallyComplete()
                        ? null
                        : 'Falta: '.implode(', ', array_slice($record->missingOperationalFields(), 0, 3))),

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
                    ->color(fn (Dron $record): string => $record->aesaRegistrationColor())
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

                TextColumn::make('insurance_coverage_policy_path')
                    ->label('PDF seguro')
                    ->badge()
                    ->state(fn (Dron $record): string => filled($record->insurance_coverage_policy_path) ? 'Adjuntado' : 'Pendiente')
                    ->color(fn (Dron $record): string => filled($record->insurance_coverage_policy_path) ? 'success' : 'warning')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->headerActions([
                CreateAction::make()
                    ->label('Anadir dron'),
            ])
            ->recordActions([
                Action::make('downloadCoveragePolicy')
                    ->label('Descargar seguro')
                    ->icon('heroicon-m-document-arrow-down')
                    ->color('gray')
                    ->button()
                    ->size('sm')
                    ->visible(fn (Dron $record): bool => filled($record->insurance_coverage_policy_path))
                    ->action(fn (Dron $record) => $this->downloadCoveragePolicy($record)),

                EditAction::make()
                    ->label('Editar'),

                DeleteAction::make()
                    ->visible(fn (Dron $record): bool => ! $record->operaciones()->exists()),
            ])
            ->recordAction('edit');
    }
}
