<?php

namespace App\Filament\Resources\Clientes\RelationManagers;

use App\Models\Piloto;
use App\Support\DocumentStorage;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class PilotosRelationManager extends RelationManager
{
    protected static string $relationship = 'pilotos';

    protected static ?string $title = 'Pilotos';

    public function isReadOnly(): bool
    {
        return false;
    }

    protected function buildDownloadFileName(Piloto $record, string $field): string
    {
        $clienteName = Str::slug($record->cliente?->fullName() ?: 'cliente');
        $pilotName = Str::slug($record->fullName() ?: 'piloto');
        $documentKey = match ($field) {
            'radiofonista_certificate_path' => 'radiofonista',
            'dni_front_path' => 'dni-frontal',
            'dni_back_path' => 'dni-trasero',
            'theoretical_certificate_path' => 'certificado-teorico',
            'practical_certificate_path' => 'certificado-practico',
            default => 'documento',
        };
        $extension = pathinfo($record->{$field} ?? 'pdf', PATHINFO_EXTENSION) ?: 'pdf';

        return "piloto-{$clienteName}-{$pilotName}-{$documentKey}.{$extension}";
    }

    protected function downloadDocument(Piloto $record, string $field)
    {
        $path = $record->{$field};

        if (blank($path) || ! Storage::disk('local')->exists($path)) {
            Notification::make()
                ->title('Archivo no encontrado')
                ->body('El documento ya no existe en el almacenamiento.')
                ->danger()
                ->send();

            return null;
        }

        return response()->download(
            Storage::disk('local')->path($path),
            $this->buildDownloadFileName($record, $field)
        );
    }

    protected function documentsDirectory(Get $get): string
    {
        $cliente = $this->getOwnerRecord();

        return DocumentStorage::folder(
            'pilotos',
            DocumentStorage::clienteSegment($cliente->getKey(), $cliente->fullName()),
            DocumentStorage::entitySegment('piloto', null, $this->pilotStorageLabel($get), 'piloto'),
            'documentos'
        );
    }

    protected function pilotFullNameFromForm(Get $get): string
    {
        return trim(implode(' ', array_filter([
            $get('first_name'),
            $get('last_name'),
            $get('second_last_name'),
        ]))) ?: 'piloto';
    }

    protected function pilotStorageLabel(Get $get): string
    {
        return trim(implode(' ', array_filter([
            $this->pilotFullNameFromForm($get),
            filled($get('dni_nie')) ? 'dni '.$get('dni_nie') : null,
        ]))) ?: 'piloto';
    }

    protected function storageFileName(TemporaryUploadedFile $file, Get $get, string $documentKey): string
    {
        $clienteName = $this->getOwnerRecord()->fullName();

        return DocumentStorage::pdfFileName([
            $clienteName,
            $this->pilotFullNameFromForm($get),
            $documentKey,
        ], $file->getClientOriginalName());
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function normalizePilotoData(array $data): array
    {
        if (! (bool) ($data['has_radiofonista_certificate'] ?? false)) {
            $data['radiofonista_certificate_path'] = null;
        }

        if (($data['theoretical_certificate_level'] ?? null) !== Piloto::THEORY_STS) {
            $data['practical_certificate_path'] = null;
        }

        return $data;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Datos personales')
                    ->schema([
                        TextInput::make('first_name')
                            ->label('Nombre')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('last_name')
                            ->label('Apellido')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('second_last_name')
                            ->label('Segundo apellido')
                            ->maxLength(255),

                        TextInput::make('dni_nie')
                            ->label('DNI o NIE')
                            ->required()
                            ->dehydrateStateUsing(fn (?string $state): string => Str::upper(trim((string) $state)))
                            ->rule(fn (RelationManager $livewire, ?Piloto $record) => Rule::unique('pilotos', 'dni_nie')
                                ->where(fn ($query) => $query->where('cliente_id', $livewire->getOwnerRecord()->getKey()))
                                ->ignore($record?->id))
                            ->validationMessages([
                                'unique' => 'Ya existe un piloto con este DNI o NIE en este cliente.',
                            ])
                            ->maxLength(50),

                        TextInput::make('birth_date')
                            ->label('Fecha de nacimiento')
                            ->type('date')
                            ->required(),

                        TextInput::make('pilot_identification_number')
                            ->label('Numero de identificacion de piloto')
                            ->required()
                            ->placeholder('ESP-RP-XXXXXXXXXXXX')
                            ->maxLength(255),

                        TextInput::make('phone')
                            ->label('Telefono')
                            ->required()
                            ->maxLength(30),

                        TextInput::make('address')
                            ->label('Direccion completa')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        TextInput::make('country')
                            ->label('Pais')
                            ->required()
                            ->maxLength(120),

                        TextInput::make('city')
                            ->label('Ciudad')
                            ->required()
                            ->maxLength(120),

                        TextInput::make('province')
                            ->label('Provincia')
                            ->required()
                            ->maxLength(120),

                        TextInput::make('postal_code')
                            ->label('Codigo postal')
                            ->required()
                            ->maxLength(20),
                    ])
                    ->columns(2),

                Section::make('Documentacion')
                    ->schema([
                        Radio::make('has_radiofonista_certificate')
                            ->label('Certificado de radiofonista')
                            ->options([
                                true => 'Si',
                                false => 'No',
                            ])
                            ->boolean()
                            ->inline()
                            ->inlineLabel(false)
                            ->live()
                            ->required(),

                        Select::make('theoretical_certificate_level')
                            ->label('Certificado de conocimientos teoricos')
                            ->options(Piloto::theoreticalCertificateOptions())
                            ->required()
                            ->live()
                            ->native(false),

                        FileUpload::make('dni_front_path')
                            ->label('DNI frontal en PDF')
                            ->acceptedFileTypes(['application/pdf'])
                            ->disk('local')
                            ->directory(fn (Get $get): string => $this->documentsDirectory($get))
                            ->getUploadedFileNameForStorageUsing(fn (TemporaryUploadedFile $file, Get $get): string => $this->storageFileName($file, $get, 'dni-frontal'))
                            ->maxSize(10240)
                            ->required(),

                        FileUpload::make('dni_back_path')
                            ->label('DNI trasero en PDF')
                            ->acceptedFileTypes(['application/pdf'])
                            ->disk('local')
                            ->directory(fn (Get $get): string => $this->documentsDirectory($get))
                            ->getUploadedFileNameForStorageUsing(fn (TemporaryUploadedFile $file, Get $get): string => $this->storageFileName($file, $get, 'dni-trasero'))
                            ->maxSize(10240)
                            ->required(),

                        FileUpload::make('theoretical_certificate_path')
                            ->label('Certificado teorico en PDF')
                            ->acceptedFileTypes(['application/pdf'])
                            ->disk('local')
                            ->directory(fn (Get $get): string => $this->documentsDirectory($get))
                            ->getUploadedFileNameForStorageUsing(fn (TemporaryUploadedFile $file, Get $get): string => $this->storageFileName($file, $get, 'certificado-teorico'))
                            ->maxSize(10240)
                            ->required(),

                        FileUpload::make('practical_certificate_path')
                            ->label('Certificado practico en PDF')
                            ->acceptedFileTypes(['application/pdf'])
                            ->disk('local')
                            ->directory(fn (Get $get): string => $this->documentsDirectory($get))
                            ->getUploadedFileNameForStorageUsing(fn (TemporaryUploadedFile $file, Get $get): string => $this->storageFileName($file, $get, 'certificado-practico'))
                            ->maxSize(10240)
                            ->visible(fn (Get $get): bool => $get('theoretical_certificate_level') === Piloto::THEORY_STS)
                            ->required(fn (Get $get): bool => $get('theoretical_certificate_level') === Piloto::THEORY_STS),

                        FileUpload::make('radiofonista_certificate_path')
                            ->label('PDF del certificado de radiofonista')
                            ->acceptedFileTypes(['application/pdf'])
                            ->disk('local')
                            ->directory(fn (Get $get): string => $this->documentsDirectory($get))
                            ->getUploadedFileNameForStorageUsing(fn (TemporaryUploadedFile $file, Get $get): string => $this->storageFileName($file, $get, 'radiofonista'))
                            ->maxSize(10240)
                            ->visible(fn (Get $get): bool => (bool) $get('has_radiofonista_certificate'))
                            ->required(fn (Get $get): bool => (bool) $get('has_radiofonista_certificate')),
                    ])
                    ->columns(2),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount('operaciones'))
            ->columns([
                TextColumn::make('full_name')
                    ->label('Piloto')
                    ->state(fn (Piloto $record): string => $record->fullName())
                    ->searchable(query: function ($query, string $search): void {
                        $query
                            ->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('second_last_name', 'like', "%{$search}%");
                    })
                    ->weight('semibold')
                    ->description(fn (Piloto $record): string => 'DNI/NIE: '.$record->dni_nie),

                TextColumn::make('operational_status')
                    ->label('Expediente')
                    ->badge()
                    ->state(fn (Piloto $record): string => $record->operationalStatusLabel())
                    ->color(fn (Piloto $record): string => $record->operationalStatusColor())
                    ->description(fn (Piloto $record): ?string => $record->isOperationallyComplete()
                        ? null
                        : 'Falta: '.implode(', ', array_slice($record->missingOperationalFields(), 0, 3))),

                TextColumn::make('theoretical_certificate_level')
                    ->label('Nivel teorico')
                    ->badge()
                    ->color('info')
                    ->formatStateUsing(fn (string $state): string => Piloto::theoreticalCertificateOptions()[$state] ?? $state),

                TextColumn::make('has_radiofonista_certificate')
                    ->label('Radiofonista')
                    ->badge()
                    ->color(fn (bool $state): string => $state ? 'success' : 'gray')
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Si' : 'No'),

                TextColumn::make('operaciones_count')
                    ->label('Operaciones')
                    ->badge()
                    ->color('warning'),

                TextColumn::make('city')
                    ->label('Ciudad')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('province')
                    ->label('Provincia')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('phone')
                    ->label('Telefono')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('pilot_identification_number')
                    ->label('Identificacion piloto')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->headerActions([
                CreateAction::make()
                    ->label('Anadir piloto')
                    ->mutateDataUsing(fn (array $data): array => $this->normalizePilotoData($data)),
            ])
            ->recordActions([
                Action::make('downloadDniFront')
                    ->label('DNI frontal')
                    ->icon('heroicon-m-identification')
                    ->color('gray')
                    ->button()
                    ->size('sm')
                    ->visible(fn (Piloto $record): bool => filled($record->dni_front_path))
                    ->action(fn (Piloto $record) => $this->downloadDocument($record, 'dni_front_path')),

                Action::make('downloadDniBack')
                    ->label('DNI trasero')
                    ->icon('heroicon-m-identification')
                    ->color('gray')
                    ->button()
                    ->size('sm')
                    ->visible(fn (Piloto $record): bool => filled($record->dni_back_path))
                    ->action(fn (Piloto $record) => $this->downloadDocument($record, 'dni_back_path')),

                Action::make('downloadRadiofonista')
                    ->label('Radiofonista')
                    ->icon('heroicon-m-arrow-down-tray')
                    ->color('info')
                    ->button()
                    ->size('sm')
                    ->visible(fn (Piloto $record): bool => filled($record->radiofonista_certificate_path))
                    ->action(fn (Piloto $record) => $this->downloadDocument($record, 'radiofonista_certificate_path')),

                Action::make('downloadTheory')
                    ->label('Teorico')
                    ->icon('heroicon-m-document-arrow-down')
                    ->color('success')
                    ->button()
                    ->size('sm')
                    ->visible(fn (Piloto $record): bool => filled($record->theoretical_certificate_path))
                    ->action(fn (Piloto $record) => $this->downloadDocument($record, 'theoretical_certificate_path')),

                Action::make('downloadPractical')
                    ->label('Practico')
                    ->icon('heroicon-m-document-arrow-down')
                    ->color('warning')
                    ->button()
                    ->size('sm')
                    ->visible(fn (Piloto $record): bool => filled($record->practical_certificate_path))
                    ->action(fn (Piloto $record) => $this->downloadDocument($record, 'practical_certificate_path')),

                EditAction::make()
                    ->label('Editar')
                    ->mutateDataUsing(fn (array $data): array => $this->normalizePilotoData($data)),

                DeleteAction::make()
                    ->visible(fn (Piloto $record): bool => ! $record->operaciones()->exists()),
            ])
            ->recordAction('edit');
    }
}
