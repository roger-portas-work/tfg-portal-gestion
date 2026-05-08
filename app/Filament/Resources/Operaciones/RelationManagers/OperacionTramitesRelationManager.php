<?php

namespace App\Filament\Resources\Operaciones\RelationManagers;

use App\Models\Operacion;
use App\Models\OperacionTramite;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

class OperacionTramitesRelationManager extends RelationManager
{
    protected static string $relationship = 'tramites';

    protected static ?string $title = 'Tramites de operacion';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return parent::canViewForRecord($ownerRecord, $pageClass)
            && $ownerRecord instanceof Operacion
            && $ownerRecord->isConfirmed();
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    protected function buildDirectory(): string
    {
        return 'operaciones/operacion-'.$this->getOwnerRecord()->getKey().'/tramites';
    }

    protected function firstAttachmentName(OperacionTramite $record): string
    {
        $originalNames = $record->attachment_file_names ?? [];

        return $originalNames[0] ?? basename(($record->attachments ?? [])[0] ?? 'documento.pdf');
    }

    protected function buildArchiveName(OperacionTramite $record): string
    {
        $operationReference = Str::slug($record->operacion?->reference ?: 'operacion');
        $tramiteTitle = Str::slug($record->title ?: 'tramite');

        return "{$operationReference}-{$tramiteTitle}.zip";
    }

    protected function downloadAttachments(OperacionTramite $record)
    {
        $attachments = collect($record->attachments ?? [])->filter()->values();

        if ($attachments->isEmpty()) {
            return null;
        }

        if ($attachments->count() === 1 || ! class_exists(ZipArchive::class)) {
            $path = (string) $attachments->first();

            return response()->download(
                Storage::disk('public')->path($path),
                $this->firstAttachmentName($record)
            );
        }

        $temporaryDirectory = storage_path('app/temp');

        if (! is_dir($temporaryDirectory)) {
            mkdir($temporaryDirectory, 0755, true);
        }

        $archivePath = $temporaryDirectory.'/'.uniqid('tramite-', true).'.zip';
        $archive = new ZipArchive();
        $archive->open($archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $originalNames = $record->attachment_file_names ?? [];

        foreach ($attachments as $index => $path) {
            $archive->addFile(
                Storage::disk('public')->path($path),
                $originalNames[$index] ?? basename((string) $path)
            );
        }

        $archive->close();

        return response()
            ->download($archivePath, $this->buildArchiveName($record))
            ->deleteFileAfterSend(true);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function normalizeTramiteData(array $data): array
    {
        $data['title'] = trim((string) ($data['title'] ?? ''));
        $data['attachments'] = array_values(array_filter((array) ($data['attachments'] ?? [])));
        $data['attachment_file_names'] = array_values(array_filter((array) ($data['attachment_file_names'] ?? [])));

        return $data;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Datos del tramite')
                    ->schema([
                        TextInput::make('title')
                            ->label('Titulo del tramite')
                            ->required()
                            ->maxLength(255)
                            ->datalist(array_values(OperacionTramite::frequentTitleOptions()))
                            ->placeholder('Escribe un titulo propio o usa uno sugerido')
                            ->helperText('Puedes usar uno de los titulos frecuentes o escribir otro personalizado si no aparece en la lista.'),

                        \Filament\Forms\Components\Select::make('status')
                            ->label('Estado')
                            ->options(OperacionTramite::statusOptions())
                            ->default(OperacionTramite::STATUS_PENDING)
                            ->required()
                            ->native(false),

                        TextInput::make('deadline_date')
                            ->label('Fecha limite de tramitacion')
                            ->type('date'),

                        TextInput::make('processed_at')
                            ->label('Fecha de tramitacion')
                            ->type('date'),

                        TextInput::make('request_code')
                            ->label('Codigo de solicitud')
                            ->maxLength(255),

                        Textarea::make('extra_information')
                            ->label('Informacion extra')
                            ->rows(4)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('Documentacion')
                    ->schema([
                        FileUpload::make('attachments')
                            ->label('PDFs del tramite')
                            ->acceptedFileTypes(['application/pdf'])
                            ->disk('public')
                            ->directory(fn (): string => $this->buildDirectory())
                            ->downloadable()
                            ->multiple()
                            ->openable()
                            ->storeFileNamesIn('attachment_file_names')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label('Tramite')
                    ->searchable()
                    ->weight('semibold'),

                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (OperacionTramite $record): string => $record->statusColor())
                    ->formatStateUsing(fn (?string $state): string => OperacionTramite::statusOptions()[$state] ?? 'Sin definir'),

                TextColumn::make('deadline_date')
                    ->label('Fecha limite')
                    ->date('d/m/Y')
                    ->placeholder('Sin definir'),

                TextColumn::make('processed_at')
                    ->label('Fecha tramitacion')
                    ->date('d/m/Y')
                    ->placeholder('Sin definir'),

                TextColumn::make('attachments_count')
                    ->label('PDFs')
                    ->badge()
                    ->color('gray')
                    ->state(fn (OperacionTramite $record): string => (string) count($record->attachments ?? [])),

                TextColumn::make('request_code')
                    ->label('Codigo')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('extra_information')
                    ->label('Informacion extra')
                    ->limit(50)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('deadline_date')
            ->headerActions([
                CreateAction::make()
                    ->label('Anadir tramite')
                    ->using(function (array $data): OperacionTramite {
                        $tramite = new OperacionTramite();
                        $tramite->fill($this->normalizeTramiteData($data));

                        $this->getOwnerRecord()->tramites()->save($tramite);

                        return $tramite;
                    }),
            ])
            ->recordActions([
                Action::make('downloadAttachments')
                    ->label('Descargar PDFs')
                    ->icon('heroicon-m-arrow-down-tray')
                    ->color('gray')
                    ->button()
                    ->size('sm')
                    ->visible(fn (OperacionTramite $record): bool => filled($record->attachments))
                    ->action(fn (OperacionTramite $record) => $this->downloadAttachments($record)),

                EditAction::make()
                    ->label('Editar')
                    ->using(function (OperacionTramite $record, array $data): OperacionTramite {
                        $record->fill($this->normalizeTramiteData($data));
                        $record->save();

                        return $record;
                    }),

                DeleteAction::make(),
            ])
            ->recordAction('edit');
    }
}
