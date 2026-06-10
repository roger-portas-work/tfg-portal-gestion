<?php

namespace App\Filament\Resources\Clientes\RelationManagers;

use App\Models\OperadoraRequirement;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OperadoraRequirementsRelationManager extends RelationManager
{
    protected static string $relationship = 'operadoraRequirements';

    protected static ?string $title = 'Expediente Operadora';

    protected static bool $isLazy = false;

    protected string $view = 'filament.resources.clientes.relation-managers.operadora-requirements-relation-manager';

    protected function buildDownloadFileName(OperadoraRequirement $record): string
    {
        $clienteName = Str::slug($record->cliente?->fullName() ?: 'cliente');
        $requirementName = Str::slug($record->name ?: 'requisito');
        $date = $record->submitted_at?->format('Y-m-d') ?? now()->format('Y-m-d');
        $extension = pathinfo($record->original_file_name ?? $record->file_path ?? 'pdf', PATHINFO_EXTENSION) ?: 'pdf';

        return "operadora-{$clienteName}-{$requirementName}-{$date}.{$extension}";
    }

    protected function downloadRequirementFile(OperadoraRequirement $record)
    {
        if (blank($record->file_path) || ! Storage::disk('public')->exists($record->file_path)) {
            Notification::make()
                ->title('Archivo no encontrado')
                ->body('La entrega del cliente ya no existe en el almacenamiento.')
                ->danger()
                ->send();

            return null;
        }

        return response()->download(
            Storage::disk('public')->path($record->file_path),
            $this->buildDownloadFileName($record)
        );
    }

    protected function requestedRequirementId(): ?string
    {
        return $this->queryParameter('requirement');
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
                // Cada requisito se gestiona por separado para que el gestor
                // pueda crearlo, editarlo o borrarlo sin pelear con un repeater largo.
                TextInput::make('name')
                    ->label('Nombre del requisito')
                    ->required()
                    ->disabled(fn (?OperadoraRequirement $record): bool => (bool) $record?->is_system_default)
                    ->maxLength(255),

                Select::make('input_type')
                    ->label('Tipo')
                    ->options(OperadoraRequirement::inputTypeOptions())
                    ->required()
                    ->default(OperadoraRequirement::TYPE_PDF)
                    ->native(false)
                    ->visible(fn (?OperadoraRequirement $record): bool => $record === null),

                Placeholder::make('input_type_summary')
                    ->label('')
                    ->hiddenLabel()
                    ->content(function (?OperadoraRequirement $record): HtmlString {
                        if (! $record) {
                            return new HtmlString('');
                        }

                        $isText = $record->input_type === OperadoraRequirement::TYPE_TEXT;
                        $label = OperadoraRequirement::inputTypeOptions()[$record->input_type] ?? $record->input_type;
                        $background = $isText ? '#ecfeff' : '#ecfdf5';
                        $border = $isText ? '#67e8f9' : '#6ee7b7';
                        $text = $isText ? '#0e7490' : '#047857';
                        $badge = $isText ? '#06b6d4' : '#10b981';
                        $description = $isText
                            ? 'El cliente debe responder escribiendo texto.'
                            : 'El cliente debe entregar un documento PDF.';
                        $icon = $isText
                            ? '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width: 18px; height: 18px;"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487 19.5 7.125m-2.638-2.638L9 12.348V15h2.652l7.862-7.875m-2.652-2.638L7.5 13.875" /></svg>'
                            : '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width: 18px; height: 18px;"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5A3.375 3.375 0 0 0 10.125 2.25H8.25m2.25 0H5.625A1.125 1.125 0 0 0 4.5 3.375v17.25A1.125 1.125 0 0 0 5.625 21.75h12.75A1.125 1.125 0 0 0 19.5 20.625V14.25" /></svg>';

                        return new HtmlString(<<<HTML
                            <div style="border: 1px solid {$border}; background: {$background}; border-radius: 18px; padding: 14px 16px;">
                                <div style="display: flex; align-items: center; justify-content: space-between; gap: 12px;">
                                    <div style="display: flex; align-items: center; gap: 10px; color: {$text};">
                                        <div style="display: flex; align-items: center; justify-content: center; width: 36px; height: 36px; border-radius: 12px; background: white; border: 1px solid {$border};">
                                            {$icon}
                                        </div>
                                        <div>
                                            <div style="font-size: 14px; font-weight: 700; color: {$text};">{$label}</div>
                                            <div style="margin-top: 4px; font-size: 13px; color: {$text}; opacity: .88;">{$description}</div>
                                        </div>
                                    </div>
                                    <div style="background: {$badge}; color: white; border-radius: 999px; padding: 7px 12px; font-size: 12px; font-weight: 700; white-space: nowrap;">
                                        {$label}
                                    </div>
                                </div>
                            </div>
                        HTML);
                    })
                    ->visible(fn (?OperadoraRequirement $record): bool => $record !== null),

                Toggle::make('is_required')
                    ->label('Obligatorio')
                    ->disabled(fn (?OperadoraRequirement $record): bool => (bool) $record?->is_system_default)
                    ->default(true),

                Textarea::make('instructions')
                    ->label('Instrucciones')
                    ->rows(4)
                    ->columnSpanFull(),

                Placeholder::make('submitted_summary')
                    ->label('Situacion actual')
                    ->content(function (?OperadoraRequirement $record): HtmlString {
                        if (! $record) {
                            return new HtmlString('');
                        }

                        $label = OperadoraRequirement::statusOptions()[$record->status] ?? $record->status;

                        [$background, $border, $badge, $title, $message] = match ($record->status) {
                            OperadoraRequirement::STATUS_PENDING => [
                                '#fffbeb',
                                '#fcd34d',
                                '#d97706',
                                '#92400e',
                                'Todavia no hay entrega del cliente para este requisito.',
                            ],
                            OperadoraRequirement::STATUS_IN_REVIEW => [
                                '#fff1f2',
                                '#fda4af',
                                '#e11d48',
                                '#9f1239',
                                $record->input_type === OperadoraRequirement::TYPE_TEXT
                                    ? 'El cliente ha enviado el texto y el requisito esta pendiente de revision.'
                                    : 'El cliente ha subido un archivo y el requisito esta pendiente de revision.',
                            ],
                            OperadoraRequirement::STATUS_APPROVED => [
                                '#ecfdf5',
                                '#6ee7b7',
                                '#059669',
                                '#065f46',
                                'Este requisito ya ha sido revisado y aprobado por el gestor.',
                            ],
                            OperadoraRequirement::STATUS_NEEDS_CHANGES => [
                                '#f8fafc',
                                '#94a3b8',
                                '#475569',
                                '#0f172a',
                                'Se ha solicitado una correccion. Ahora se esta esperando a que el cliente vuelva a entregar una version corregida.',
                            ],
                            default => [
                                '#f5f5f5',
                                '#d4d4d8',
                                '#52525b',
                                '#18181b',
                                'Estado del requisito no disponible.',
                            ],
                        };

                        return new HtmlString(<<<HTML
                            <div style="border: 1px solid {$border}; background: {$background}; border-radius: 20px; padding: 18px 20px;">
                                <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: 16px;">
                                    <div>
                                        <div style="font-size: 12px; text-transform: uppercase; letter-spacing: .14em; color: {$badge}; font-weight: 700;">Estado del requisito</div>
                                        <div style="margin-top: 8px; font-size: 20px; font-weight: 700; color: {$title};">{$label}</div>
                                        <div style="margin-top: 10px; font-size: 14px; line-height: 1.6; color: {$title}; opacity: .92;">{$message}</div>
                                    </div>
                                    <div style="background: {$badge}; color: white; border-radius: 999px; padding: 8px 12px; font-size: 12px; font-weight: 700; white-space: nowrap;">
                                        {$label}
                                    </div>
                                </div>
                            </div>
                        HTML);
                    })
                    ->helperText(function (?OperadoraRequirement $record): ?string {
                        if (! $record) {
                            return null;
                        }

                        if ($record->status === OperadoraRequirement::STATUS_IN_REVIEW && $record->submitted_at) {
                            return 'Entrega recibida el '.$record->submitted_at->format('d/m/Y H:i');
                        }

                        if ($record->status === OperadoraRequirement::STATUS_APPROVED && $record->reviewed_at) {
                            return 'Revision cerrada el '.$record->reviewed_at->format('d/m/Y H:i');
                        }

                        if ($record->status === OperadoraRequirement::STATUS_NEEDS_CHANGES && $record->reviewed_at) {
                            return 'Correccion solicitada el '.$record->reviewed_at->format('d/m/Y H:i');
                        }

                        return null;
                    })
                    ->visible(fn (?OperadoraRequirement $record): bool => $record !== null)
                    ->columnSpanFull(),

                Select::make('review_decision')
                    ->label('Decision de revision')
                    ->options([
                        OperadoraRequirement::STATUS_APPROVED => 'Aprobar requisito',
                        OperadoraRequirement::STATUS_NEEDS_CHANGES => 'Pedir correccion',
                    ])
                    ->placeholder('Mantener estado actual')
                    ->native(false)
                    ->visible(fn (?OperadoraRequirement $record): bool => $record?->status === OperadoraRequirement::STATUS_IN_REVIEW),

                Textarea::make('review_notes')
                    ->label('Indicaciones de revision para el cliente')
                    ->helperText('Escribe aqui las observaciones del gestor. Si pides correccion, este texto debe ayudar al cliente a saber que debe cambiar.')
                    ->required(fn (Get $get, ?OperadoraRequirement $record): bool => $record?->status === OperadoraRequirement::STATUS_NEEDS_CHANGES
                        || ($record?->status === OperadoraRequirement::STATUS_IN_REVIEW
                            && $get('review_decision') === OperadoraRequirement::STATUS_NEEDS_CHANGES))
                    ->validationMessages([
                        'required' => 'Explica que debe corregir el cliente antes de pedir correccion.',
                    ])
                    ->rows(4)
                    ->visible(fn (?OperadoraRequirement $record): bool => $record !== null)
                    ->columnSpanFull(),

                Hidden::make('status')
                    ->default(OperadoraRequirement::STATUS_PENDING),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Requisito')
                    ->searchable()
                    ->weight('semibold')
                    ->description(fn (OperadoraRequirement $record): ?string => $record->is_system_default ? 'Requisito base del sistema' : null),

                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (OperadoraRequirement $record): string => $record->gestorStatusColor())
                    ->icon(fn (string $state): string => match ($state) {
                        OperadoraRequirement::STATUS_PENDING => 'heroicon-m-clock',
                        OperadoraRequirement::STATUS_IN_REVIEW => 'heroicon-m-eye',
                        OperadoraRequirement::STATUS_APPROVED => 'heroicon-m-check-badge',
                        OperadoraRequirement::STATUS_NEEDS_CHANGES => 'heroicon-m-arrow-path',
                        default => 'heroicon-m-clock',
                    })
                    ->formatStateUsing(fn (string $state): string => OperadoraRequirement::statusOptions()[$state] ?? $state)
                    ->description(function (OperadoraRequirement $record): ?string {
                        return match ($record->status) {
                            OperadoraRequirement::STATUS_PENDING => $record->created_at
                                ? 'Desde '.$record->created_at->format('d/m/Y')
                                : null,
                            OperadoraRequirement::STATUS_IN_REVIEW => $record->submitted_at
                                ? 'Desde '.$record->submitted_at->format('d/m/Y')
                                : null,
                            OperadoraRequirement::STATUS_NEEDS_CHANGES => $record->reviewed_at
                                ? 'Desde '.$record->reviewed_at->format('d/m/Y')
                                : null,
                            default => null,
                        };
                    }),

                TextColumn::make('is_required')
                    ->label('Obligatorio')
                    ->badge()
                    ->color(fn (bool $state): string => $state ? 'warning' : 'gray')
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Si' : 'No')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('input_type')
                    ->label('Tipo')
                    ->badge()
                    ->color(fn (string $state): string => $state === OperadoraRequirement::TYPE_TEXT ? 'info' : 'success')
                    ->formatStateUsing(fn (string $state): string => OperadoraRequirement::inputTypeOptions()[$state] ?? $state)
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('submitted_at')
                    ->label('Entregado')
                    ->date('d/m/Y')
                    ->placeholder('Sin entrega')
                    ->toggleable(),
            ])
            ->defaultSort('id', 'desc')
            ->recordClasses(function (OperadoraRequirement $record): string {
                $classes = match ($record->status) {
                    OperadoraRequirement::STATUS_IN_REVIEW => 'border-s-4 border-rose-400 bg-rose-50/40 dark:bg-rose-500/5',
                    OperadoraRequirement::STATUS_APPROVED => 'border-s-4 border-emerald-400 bg-emerald-50/40 dark:bg-emerald-500/5',
                    OperadoraRequirement::STATUS_NEEDS_CHANGES => 'border-s-4 border-slate-400 bg-slate-50/70 dark:bg-slate-500/5',
                    default => 'border-s-4 border-amber-300 bg-amber-50/40 dark:bg-amber-500/5',
                };

                if ($this->requestedRequirementId() === (string) $record->getKey()) {
                    return $classes.' idrx-highlighted-requirement-row';
                }

                return $classes;
            })
            ->emptyStateHeading('Todavia no hay requisitos de operadora')
            ->emptyStateDescription('Crea los requisitos que el cliente debera completar para este expediente.')
            ->headerActions([
                CreateAction::make()
                    ->label('Anadir requisito'),
            ])
            ->recordActions([
                Action::make('downloadFile')
                    ->label('Descargar')
                    ->icon('heroicon-m-arrow-down-tray')
                    ->color('info')
                    ->button()
                    ->size('sm')
                    ->tooltip('Descargar la entrega actual del cliente')
                    ->visible(fn (OperadoraRequirement $record): bool => filled($record->file_path))
                    ->action(fn (OperadoraRequirement $record) => $this->downloadRequirementFile($record)),
                EditAction::make()
                    ->label('Editar')
                    ->hiddenLabel()
                    ->icon('heroicon-m-pencil-square')
                    ->extraAttributes(['class' => 'hidden'])
                    ->mutateDataUsing(function (array $data, OperadoraRequirement $record): array {
                        $decision = $data['review_decision'] ?? null;
                        $data['review_notes'] = filled($data['review_notes'] ?? null)
                            ? trim((string) $data['review_notes'])
                            : null;

                        if ($record->status === OperadoraRequirement::STATUS_IN_REVIEW && in_array($decision, [
                            OperadoraRequirement::STATUS_APPROVED,
                            OperadoraRequirement::STATUS_NEEDS_CHANGES,
                        ], true)) {
                            if ($decision === OperadoraRequirement::STATUS_NEEDS_CHANGES && blank($data['review_notes'])) {
                                throw ValidationException::withMessages([
                                    'review_notes' => 'Explica que debe corregir el cliente antes de pedir correccion.',
                                ]);
                            }

                            $data['status'] = $decision;
                            $data['reviewed_at'] = now();
                        } else {
                            $data['status'] = $record->status;

                            if (! in_array($record->status, [
                                OperadoraRequirement::STATUS_APPROVED,
                                OperadoraRequirement::STATUS_NEEDS_CHANGES,
                            ], true)) {
                                $data['reviewed_at'] = null;
                            }
                        }

                        unset($data['review_decision']);

                        return $data;
                    }),
                DeleteAction::make()
                    ->visible(fn (OperadoraRequirement $record): bool => ! $record->is_system_default),
            ])
            ->recordAction('edit');
    }
}
