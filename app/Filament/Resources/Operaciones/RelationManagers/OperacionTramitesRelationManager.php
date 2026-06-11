<?php

namespace App\Filament\Resources\Operaciones\RelationManagers;

use App\Models\Operacion;
use App\Models\OperacionTramite;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

class OperacionTramitesRelationManager extends RelationManager
{
    protected static string $relationship = 'tramites';

    protected static ?string $title = 'Tramites de operacion';

    protected static bool $isLazy = false;

    protected string $view = 'filament.resources.operaciones.relation-managers.operacion-tramites-relation-manager';

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

    protected function notifyMissingAttachments(): void
    {
        Notification::make()
            ->title('Archivo no encontrado')
            ->body('Uno o varios PDFs ya no existen en el almacenamiento.')
            ->danger()
            ->send();
    }

    protected function notifyOperationWorkflowUpdated(): void
    {
        $this->dispatch('operacion-tramites-updated');
    }

    protected function buildArchiveName(OperacionTramite $record): string
    {
        $operationReference = Str::slug($record->operacion?->reference ?: 'operacion');
        $tramiteTitle = Str::slug($record->title ?: 'tramite');

        return "{$operationReference}-{$tramiteTitle}.zip";
    }

    protected function downloadAttachments(OperacionTramite $record)
    {
        $disk = Storage::disk('public');
        $originalNames = $record->attachment_file_names ?? [];
        $attachments = collect($record->attachments ?? [])
            ->filter()
            ->map(fn (string $path, int $index): array => [
                'path' => $path,
                'name' => $originalNames[$index] ?? basename($path),
            ])
            ->values();

        if ($attachments->isEmpty()) {
            return null;
        }

        $existingAttachments = $attachments
            ->filter(fn (array $attachment): bool => $disk->exists($attachment['path']))
            ->values();

        if ($existingAttachments->count() !== $attachments->count()) {
            $this->notifyMissingAttachments();
        }

        if ($existingAttachments->isEmpty()) {
            return null;
        }

        if ($existingAttachments->count() === 1 || ! class_exists(ZipArchive::class)) {
            $attachment = $existingAttachments->first();

            return response()->download(
                $disk->path($attachment['path']),
                $attachment['name'] ?: $this->firstAttachmentName($record)
            );
        }

        $temporaryDirectory = storage_path('app/temp');

        if (! is_dir($temporaryDirectory)) {
            mkdir($temporaryDirectory, 0755, true);
        }

        $archivePath = $temporaryDirectory.'/'.uniqid('tramite-', true).'.zip';
        $archive = new ZipArchive();
        $archive->open($archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach ($existingAttachments as $attachment) {
            $archive->addFile(
                $disk->path($attachment['path']),
                $attachment['name']
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
        $data['processed_at'] = filled($data['processed_at'] ?? null) ? $data['processed_at'] : null;
        $data['deadline_date'] = filled($data['processed_at'])
            ? null
            : (filled($data['deadline_date'] ?? null) ? $data['deadline_date'] : null);
        $data['status'] = $this->normalizeTramiteStatus($data['status'] ?? null, $data['processed_at']);

        return $data;
    }

    protected function normalizeTramiteStatus(?string $status, mixed $processedAt): string
    {
        if (blank($processedAt)) {
            return OperacionTramite::STATUS_PENDING;
        }

        return in_array($status, [
            OperacionTramite::STATUS_PROCESSED,
            OperacionTramite::STATUS_APPROVED,
            OperacionTramite::STATUS_DENIED,
        ], true)
            ? $status
            : OperacionTramite::STATUS_PROCESSED;
    }

    /**
     * @return array<string, string>
     */
    protected function statusOptionsForProcessedDate(mixed $processedAt): array
    {
        if (blank($processedAt)) {
            return [
                OperacionTramite::STATUS_PENDING => OperacionTramite::statusOptions()[OperacionTramite::STATUS_PENDING],
            ];
        }

        return [
            OperacionTramite::STATUS_PROCESSED => OperacionTramite::statusOptions()[OperacionTramite::STATUS_PROCESSED],
            OperacionTramite::STATUS_APPROVED => OperacionTramite::statusOptions()[OperacionTramite::STATUS_APPROVED],
            OperacionTramite::STATUS_DENIED => OperacionTramite::statusOptions()[OperacionTramite::STATUS_DENIED],
        ];
    }

    protected function requestedTramiteId(): ?string
    {
        return $this->queryParameter('tramite');
    }

    protected function queryParameter(string $key): ?string
    {
        $value = request()->query($key);

        if (! is_array($value) && filled($value)) {
            return (string) $value;
        }

        $referer = request()->headers->get('referer');

        if (blank($referer)) {
            return null;
        }

        $query = parse_url((string) $referer, PHP_URL_QUERY);

        if (blank($query)) {
            return null;
        }

        parse_str($query, $parameters);

        $value = $parameters[$key] ?? null;

        return (! is_array($value) && filled($value)) ? (string) $value : null;
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

                        DatePicker::make('deadline_date')
                            ->label('Fecha límite para tramitar')
                            ->displayFormat('d/m/Y')
                            ->native(false)
                            ->closeOnDateSelection()
                            ->hidden(fn (Get $get): bool => filled($get('processed_at')))
                            ->hintAction(
                                Action::make('clearDeadlineDate')
                                    ->label('Sin definir')
                                    ->color('gray')
                                    ->button()
                                    ->outlined()
                                    ->size('sm')
                                    ->action(fn (Set $set): null => $set('deadline_date', null))
                            )
                            ->nullable()
                            ->helperText('Deja este campo vacío si todavía no hay fecha límite definida.'),

                        DatePicker::make('processed_at')
                            ->label('Fecha de tramitación')
                            ->displayFormat('d/m/Y')
                            ->native(false)
                            ->closeOnDateSelection()
                            ->live()
                            ->afterStateUpdated(function (Set $set, mixed $state): void {
                                if (filled($state)) {
                                    $set('deadline_date', null);
                                    $set('status', OperacionTramite::STATUS_PROCESSED);

                                    return;
                                }

                                $set('status', OperacionTramite::STATUS_PENDING);
                            })
                            ->hintAction(
                                Action::make('clearProcessedAt')
                                    ->label('Sin definir')
                                    ->color('gray')
                                    ->button()
                                    ->outlined()
                                    ->size('sm')
                                    ->action(function (Set $set): void {
                                        $set('processed_at', null);
                                        $set('status', OperacionTramite::STATUS_PENDING);
                                    })
                            )
                            ->nullable()
                            ->helperText('Deja este campo vacío si el trámite todavía no se ha tramitado.'),

                        Select::make('status')
                            ->label('Estado')
                            ->options(fn (Get $get): array => $this->statusOptionsForProcessedDate($get('processed_at')))
                            ->default(OperacionTramite::STATUS_PENDING)
                            ->required()
                            ->native(false)
                            ->live()
                            ->afterStateHydrated(function (Set $set, Get $get, ?string $state): void {
                                $set('status', $this->normalizeTramiteStatus($state, $get('processed_at')));
                            })
                            ->helperText('Sin fecha de tramitación queda pendiente. Con fecha pasa a procesado y después se puede aprobar o denegar.'),

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
                    ->label('Fecha límite para tramitar')
                    ->state(fn (OperacionTramite $record): mixed => $record->isProcessedForGestor() ? null : $record->deadline_date)
                    ->date('d/m/Y')
                    ->placeholder(fn (OperacionTramite $record): string => $record->isProcessedForGestor() ? 'No aplica' : 'Sin definir'),

                TextColumn::make('deadline_countdown')
                    ->label('Dias restantes')
                    ->badge()
                    ->state(fn (OperacionTramite $record): string => $record->deadlineCountdownLabel())
                    ->color(fn (OperacionTramite $record): string => $record->deadlineCountdownColor()),

                TextColumn::make('processed_at')
                    ->label('Fecha de tramitación')
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
            ->defaultSort(fn (Builder $query): Builder => $query
                ->orderByRaw('case when processed_at is null then 0 else 1 end')
                ->orderBy('deadline_date')
                ->orderBy('id'))
            ->recordClasses(function (OperacionTramite $record): string {
                if ($this->requestedTramiteId() === (string) $record->getKey()) {
                    return 'idrx-highlighted-table-row';
                }

                return '';
            })
            ->headerActions([
                CreateAction::make()
                    ->label('Anadir tramite')
                    ->using(function (array $data): OperacionTramite {
                        $tramite = new OperacionTramite();
                        $tramite->fill($this->normalizeTramiteData($data));

                        $this->getOwnerRecord()->tramites()->save($tramite);

                        return $tramite;
                    })
                    ->after(function (): void {
                        $this->notifyOperationWorkflowUpdated();
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
                    })
                    ->after(function (): void {
                        $this->notifyOperationWorkflowUpdated();
                    }),

                DeleteAction::make()
                    ->after(function (): void {
                        $this->notifyOperationWorkflowUpdated();
                    }),
            ])
            ->recordAction('edit');
    }
}
