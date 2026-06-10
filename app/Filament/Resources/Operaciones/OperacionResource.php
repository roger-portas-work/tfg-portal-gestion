<?php

namespace App\Filament\Resources\Operaciones;

use App\Filament\Resources\Clientes\ClienteResource;
use App\Filament\Resources\Operaciones\Pages\ListOperaciones;
use App\Filament\Resources\Operaciones\Pages\ViewOperacion;
use App\Filament\Resources\Operaciones\RelationManagers\OperacionTramitesRelationManager;
use App\Filament\Resources\Operaciones\Schemas\OperacionForm;
use App\Models\Dron;
use App\Models\Operacion;
use App\Models\OperacionTramite;
use App\Models\OperadoraRequirement;
use App\Models\Piloto;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class OperacionResource extends Resource
{
    protected static ?string $model = Operacion::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationLabel = 'Operaciones';

    protected static ?string $modelLabel = 'Operacion';

    protected static ?string $pluralModelLabel = 'Operaciones';

    protected static ?string $recordTitleAttribute = 'reference';

    protected static ?string $slug = 'operaciones';

    protected static ?int $navigationSort = 2;

    protected static function activeOperationsFrom(): string
    {
        return Carbon::today(config('app.timezone'))->subDays(2)->toDateString();
    }

    protected static function todayDate(): string
    {
        return Carbon::today(config('app.timezone'))->toDateString();
    }

    public static function form(Schema $schema): Schema
    {
        return OperacionForm::configure($schema);
    }

    public static function buildOperationsTable(Table $table, bool $onlyUpcoming = false, ?string $heading = null): Table
    {
        $activeFrom = static::activeOperationsFrom();

        return $table
            ->modifyQueryUsing(function (Builder $query) use ($onlyUpcoming, $activeFrom): void {
                $query->withTramiteWorkflowCounts();

                if ($onlyUpcoming) {
                    $query
                        ->whereDate('operation_date', '>=', $activeFrom)
                        ->where(function (Builder $query): void {
                            $query
                                ->whereNull('status')
                                ->orWhere('status', '!=', Operacion::STATUS_REJECTED);
                        });
                }
            })
            ->heading($heading)
            ->columns([
                TextColumn::make('reference')
                    ->label('Operacion')
                    ->searchable()
                    ->sortable()
                    ->description(fn (Operacion $record): ?string => $record->estimated_filming_schedule ?: null),

                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (Operacion $record): string => $record->statusColor())
                    ->formatStateUsing(fn (?string $state): string => Operacion::statusOptions()[$state] ?? 'Pendiente'),

                TextColumn::make('workflow_priority')
                    ->label('Prioridad')
                    ->badge()
                    ->state(fn (Operacion $record): string => $record->workflowPriorityLabel())
                    ->color(fn (Operacion $record): string => $record->workflowPriorityColor()),

                TextColumn::make('documentation_status')
                    ->label('Documentacion')
                    ->badge()
                    ->state(fn (Operacion $record): string => $record->documentationStatusLabel())
                    ->color(fn (Operacion $record): string => $record->documentationStatusColor()),

                TextColumn::make('operation_date')
                    ->label('Fecha')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('cliente_name')
                    ->label('Cliente')
                    ->state(fn (Operacion $record): string => $record->cliente?->fullName() ?: 'Sin cliente')
                    ->searchable(query: function (Builder $query, string $search): void {
                        $query->whereHas('cliente', function (Builder $clienteQuery) use ($search): void {
                            $clienteQuery
                                ->where('name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%")
                                ->orWhere('second_last_name', 'like', "%{$search}%");
                        });
                    }),

                TextColumn::make('piloto_name')
                    ->label('Piloto')
                    ->state(fn (Operacion $record): string => $record->piloto?->fullName() ?: 'Sin piloto')
                    ->searchable(query: function (Builder $query, string $search): void {
                        $query->whereHas('piloto', function (Builder $pilotoQuery) use ($search): void {
                            $pilotoQuery
                                ->where('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%")
                                ->orWhere('second_last_name', 'like', "%{$search}%")
                                ->orWhere('dni_nie', 'like', "%{$search}%");
                        });
                    })
                    ->toggleable(),

                TextColumn::make('dron_label')
                    ->label('Dron')
                    ->state(function (Operacion $record): string {
                        $label = trim(($record->dron?->manufacturer_name ?? '').' '.($record->dron?->model ?? ''));

                        if ($record->dron && (filled($record->dron->registration_number) || $record->dron->registration_not_applicable)) {
                            $label .= ' - '.$record->dron->registrationLabel();
                        }

                        return $label ?: 'Sin dron';
                    })
                    ->searchable(query: function (Builder $query, string $search): void {
                        $query->whereHas('dron', function (Builder $dronQuery) use ($search): void {
                            $dronQuery
                                ->where('manufacturer_name', 'like', "%{$search}%")
                                ->orWhere('model', 'like', "%{$search}%")
                                ->orWhere('registration_number', 'like', "%{$search}%")
                                ->orWhere('drone_serial_number', 'like', "%{$search}%");
                        });
                    })
                    ->toggleable(),

                TextColumn::make('tramites_count')
                    ->label('Tramites')
                    ->badge()
                    ->color('gray')
                    ->state(fn (Operacion $record): string => (string) ($record->tramites_count ?? 0))
                    ->toggleable(),

                TextColumn::make('operation_cost')
                    ->label('Coste')
                    ->state(fn (Operacion $record): string => filled($record->operation_cost)
                        ? number_format((float) $record->operation_cost, 2, ',', '.').' EUR'
                        : 'Sin definir')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('address')
                    ->label('Ubicacion')
                    ->state(fn (Operacion $record): string => $record->address ?: $record->location ?: 'Sin definir')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('environment_type')
                    ->label('Entorno')
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'interior' => 'Interior',
                        'exterior' => 'Exterior',
                        default => 'Sin definir',
                    })
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters(static::tableFilters())
            ->defaultSort('operation_date')
            ->recordActions([
                Action::make('confirmarOperacion')
                    ->label(fn (Operacion $record): string => $record->isConfirmed() ? 'Actualizar' : 'Confirmar')
                    ->icon('heroicon-m-check-circle')
                    ->color('success')
                    ->visible(fn (Operacion $record): bool => $record->isPending() || $record->isConfirmed())
                    ->fillForm(fn (Operacion $record): array => [
                        'operation_cost' => $record->operation_cost,
                        'operational_conditions' => $record->operational_conditions,
                    ])
                    ->form(static::confirmationForm())
                    ->action(function (Operacion $record, array $data): void {
                        $record->update([
                            'status' => Operacion::STATUS_CONFIRMED,
                            'operation_cost' => $data['operation_cost'],
                            'operational_conditions' => $data['operational_conditions'],
                        ]);
                    })
                    ->successNotificationTitle('Operacion confirmada'),

                ViewAction::make()
                    ->label('Gestionar'),

                EditAction::make()
                    ->label('Editar')
                    ->mutateDataUsing(fn (array $data): array => OperacionForm::mutateData($data)),

                Action::make('rechazarOperacion')
                    ->label('Rechazar')
                    ->icon('heroicon-m-x-circle')
                    ->color('danger')
                    ->visible(fn (Operacion $record): bool => $record->isPending())
                    ->requiresConfirmation()
                    ->action(function (Operacion $record): void {
                        $record->update([
                            'status' => Operacion::STATUS_REJECTED,
                            'operation_cost' => null,
                            'operational_conditions' => null,
                        ]);
                    })
                    ->successNotificationTitle('Operacion rechazada'),

                DeleteAction::make()
                    ->visible(fn (Operacion $record): bool => $record->isPending()),
            ])
            ->recordAction('view');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with([
                'cliente.operadoraProfile',
                'cliente.operadoraRequirements',
                'piloto',
                'dron',
            ])
            ->withTramiteWorkflowCounts();
    }

    public static function table(Table $table): Table
    {
        return static::buildOperationsTable($table, onlyUpcoming: false, heading: 'Todas las operaciones');
    }

    /**
     * @return array<int, mixed>
     */
    public static function confirmationForm(): array
    {
        return [
            TextInput::make('operation_cost')
                ->label('Coste de operacion')
                ->numeric()
                ->required()
                ->minValue(0)
                ->suffix('EUR'),
            Textarea::make('operational_conditions')
                ->label('Condiciones operativas')
                ->required()
                ->rows(5),
        ];
    }

    /**
     * @return array<int, mixed>
     */
    protected static function tableFilters(): array
    {
        return [
            Filter::make('assignment')
                ->label('Cliente, piloto y dron')
                ->form([
                    Select::make('cliente_id')
                        ->label('Cliente')
                        ->options(fn (): array => static::clienteFilterOptions())
                        ->searchable()
                        ->native(false)
                        ->live()
                        ->afterStateUpdated(function (Set $set): void {
                            $set('piloto_id', null);
                            $set('dron_id', null);
                        }),

                    Select::make('piloto_id')
                        ->label('Piloto')
                        ->options(fn (Get $get): array => static::pilotoFilterOptions($get('cliente_id')))
                        ->searchable()
                        ->native(false),

                    Select::make('dron_id')
                        ->label('Dron')
                        ->options(fn (Get $get): array => static::dronFilterOptions($get('cliente_id')))
                        ->searchable()
                        ->native(false),
                ])
                ->query(function (Builder $query, array $data): Builder {
                    return $query
                        ->when(
                            filled($data['cliente_id'] ?? null),
                            fn (Builder $query): Builder => $query->where('cliente_id', $data['cliente_id'])
                        )
                        ->when(
                            filled($data['piloto_id'] ?? null),
                            fn (Builder $query): Builder => $query->where('piloto_id', $data['piloto_id'])
                        )
                        ->when(
                            filled($data['dron_id'] ?? null),
                            fn (Builder $query): Builder => $query->where('dron_id', $data['dron_id'])
                        );
                }),

            Filter::make('operation_range')
                ->label('Fecha operacion')
                ->form([
                    Fieldset::make('Fecha operacion')
                        ->schema([
                            DatePicker::make('operation_from')
                                ->label('Desde')
                                ->displayFormat('d/m/Y')
                                ->native(false)
                                ->closeOnDateSelection(),
                            DatePicker::make('operation_until')
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
                            filled($data['operation_from'] ?? null),
                            fn (Builder $query): Builder => $query->whereDate('operation_date', '>=', $data['operation_from'])
                        )
                        ->when(
                            filled($data['operation_until'] ?? null),
                            fn (Builder $query): Builder => $query->whereDate('operation_date', '<=', $data['operation_until'])
                        );
                }),

        ];
    }

    /**
     * @return array<int|string, string>
     */
    protected static function clienteFilterOptions(): array
    {
        return Cliente::query()
            ->orderBy('name')
            ->orderBy('last_name')
            ->get()
            ->mapWithKeys(fn (Cliente $cliente): array => [
                $cliente->getKey() => $cliente->fullName() ?: 'Cliente #'.$cliente->getKey(),
            ])
            ->all();
    }

    /**
     * @return array<int|string, string>
     */
    protected static function pilotoFilterOptions(mixed $clienteId = null): array
    {
        return Piloto::query()
            ->when(filled($clienteId), fn (Builder $query): Builder => $query->where('cliente_id', $clienteId))
            ->with('cliente')
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get()
            ->mapWithKeys(fn (Piloto $piloto): array => [
                $piloto->getKey() => $piloto->displayNameWithIdentification(),
            ])
            ->all();
    }

    /**
     * @return array<int|string, string>
     */
    protected static function dronFilterOptions(mixed $clienteId = null): array
    {
        return Dron::query()
            ->when(filled($clienteId), fn (Builder $query): Builder => $query->where('cliente_id', $clienteId))
            ->with('cliente')
            ->orderBy('manufacturer_name')
            ->orderBy('model')
            ->get()
            ->mapWithKeys(fn (Dron $dron): array => [
                $dron->getKey() => $dron->displayNameWithSerial(),
            ])
            ->all();
    }

    public static function applyPriorityTabQuery(Builder $query): Builder
    {
        return $query
            ->whereDate('operation_date', '>=', static::todayDate())
            ->where(function (Builder $query): void {
                $query
                    ->whereNull('status')
                    ->orWhere('status', Operacion::STATUS_PENDING)
                    ->orWhere(function (Builder $query): void {
                        static::applyConfirmedIssuesConditions($query);
                    });
            })
            ->orderBy('operation_date')
            ->orderBy('id');
    }

    public static function applyPendingTabQuery(Builder $query): Builder
    {
        return $query
            ->whereDate('operation_date', '>=', static::todayDate())
            ->where(function (Builder $query): void {
                $query
                    ->whereNull('status')
                    ->orWhere('status', Operacion::STATUS_PENDING);
            })
            ->orderBy('operation_date')
            ->orderBy('id');
    }

    public static function applyTodayTabQuery(Builder $query): Builder
    {
        return $query
            ->whereDate('operation_date', static::todayDate())
            ->where(function (Builder $query): void {
                $query
                    ->whereNull('status')
                    ->orWhere('status', '!=', Operacion::STATUS_REJECTED);
            })
            ->orderBy('estimated_filming_schedule')
            ->orderBy('id');
    }

    public static function applyConfirmedIssuesTabQuery(Builder $query): Builder
    {
        return static::applyConfirmedIssuesConditions($query)
            ->whereDate('operation_date', '>=', static::todayDate())
            ->orderBy('operation_date')
            ->orderBy('id');
    }

    public static function applyUpcomingTabQuery(Builder $query): Builder
    {
        return $query
            ->whereDate('operation_date', '>=', static::todayDate())
            ->where(function (Builder $query): void {
                $query
                    ->whereNull('status')
                    ->orWhere('status', '!=', Operacion::STATUS_REJECTED);
            })
            ->orderBy('operation_date')
            ->orderBy('id');
    }

    public static function applyRejectedTabQuery(Builder $query): Builder
    {
        return $query
            ->where('status', Operacion::STATUS_REJECTED)
            ->whereDate('operation_date', '>=', static::todayDate())
            ->orderBy('operation_date')
            ->orderBy('id');
    }

    public static function applyPastTabQuery(Builder $query): Builder
    {
        return $query
            ->whereDate('operation_date', '<', static::todayDate())
            ->orderByDesc('operation_date')
            ->orderByDesc('id');
    }

    protected static function applyConfirmedIssuesConditions(Builder $query): Builder
    {
        return $query
            ->where('status', Operacion::STATUS_CONFIRMED)
            ->where(function (Builder $query): void {
                $query
                    ->doesntHave('tramites')
                    ->orWhereHas('tramites', fn (Builder $tramitesQuery): Builder => $tramitesQuery
                        ->where('status', '!=', OperacionTramite::STATUS_APPROVED));
            });
    }

    protected static function downloadStoredDocument(?string $path, string $fileName, string $missingBody)
    {
        if (blank($path) || ! Storage::disk('public')->exists($path)) {
            Notification::make()
                ->title('Archivo no encontrado')
                ->body($missingBody)
                ->danger()
                ->send();

            return null;
        }

        return response()->download(
            Storage::disk('public')->path($path),
            $fileName
        );
    }

    protected static function downloadPilotDocument(Operacion $record, string $field)
    {
        return static::downloadStoredDocument(
            $record->piloto?->{$field},
            static::pilotDocumentFileName($record, $field),
            'El documento del piloto ya no existe en el almacenamiento.'
        );
    }

    protected static function pilotDocumentFileName(Operacion $record, string $field): string
    {
        $clienteName = Str::slug($record->cliente?->fullName() ?: 'cliente');
        $pilotName = Str::slug($record->piloto?->fullName() ?: 'piloto');
        $documentKey = match ($field) {
            'radiofonista_certificate_path' => 'radiofonista',
            'dni_front_path' => 'dni-frontal',
            'dni_back_path' => 'dni-trasero',
            'theoretical_certificate_path' => 'certificado-teorico',
            'practical_certificate_path' => 'certificado-practico',
            default => 'documento',
        };
        $extension = pathinfo($record->piloto?->{$field} ?? 'pdf', PATHINFO_EXTENSION) ?: 'pdf';

        return "piloto-{$clienteName}-{$pilotName}-{$documentKey}.{$extension}";
    }

    protected static function downloadDroneInsuranceDocument(Operacion $record)
    {
        return static::downloadStoredDocument(
            $record->dron?->insurance_coverage_policy_path,
            static::droneInsuranceFileName($record),
            'El PDF del seguro ya no existe en el almacenamiento.'
        );
    }

    protected static function droneInsuranceFileName(Operacion $record): string
    {
        if (filled($record->dron?->insurance_coverage_policy_original_name)) {
            return $record->dron->insurance_coverage_policy_original_name;
        }

        $clienteName = Str::slug($record->cliente?->fullName() ?: 'cliente');
        $dronName = Str::slug($record->dron?->displayName() ?: 'dron');
        $extension = pathinfo($record->dron?->insurance_coverage_policy_path ?? 'pdf', PATHINFO_EXTENSION) ?: 'pdf';

        return "dron-{$clienteName}-{$dronName}-seguro.{$extension}";
    }

    protected static function operadoraRequirementDocument(Operacion $record): ?OperadoraRequirement
    {
        $requirements = $record->cliente?->operadoraRequirements;

        if (! $requirements) {
            return null;
        }

        return $requirements
            ->filter(fn (OperadoraRequirement $requirement): bool => filled($requirement->file_path))
            ->sortByDesc(fn (OperadoraRequirement $requirement): bool => $requirement->is_system_default)
            ->first();
    }

    protected static function downloadOperadoraRequirementDocument(Operacion $record)
    {
        $requirement = static::operadoraRequirementDocument($record);

        return static::downloadStoredDocument(
            $requirement?->file_path,
            static::operadoraRequirementFileName($record, $requirement),
            'La entrega de operadora ya no existe en el almacenamiento.'
        );
    }

    protected static function operadoraRequirementFileName(Operacion $record, ?OperadoraRequirement $requirement): string
    {
        $clienteName = Str::slug($record->cliente?->fullName() ?: 'cliente');
        $requirementName = Str::slug($requirement?->name ?: 'requisito');
        $date = $requirement?->submitted_at?->format('Y-m-d') ?? now()->format('Y-m-d');
        $extension = pathinfo($requirement?->original_file_name ?? $requirement?->file_path ?? 'pdf', PATHINFO_EXTENSION) ?: 'pdf';

        return "operadora-{$clienteName}-{$requirementName}-{$date}.{$extension}";
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make('Incidencia destacada')
                    ->description('Has llegado desde el dashboard del gestor. Revisa el punto indicado antes de cerrar esta operacion.')
                    ->visible(fn (): bool => filled(request()->query('focus')))
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('dashboard_focus')
                            ->label('Prioridad')
                            ->state(fn (Operacion $record): string => static::dashboardFocusMessage($record))
                            ->badge()
                            ->color(fn (): string => match (request()->query('focus')) {
                                'documentacion-completa' => 'success',
                                'operacion-hoy', 'tramites-7-dias', 'tramite-7-dias' => 'warning',
                                'pendiente-confirmar' => 'warning',
                                'operacion-rechazada' => 'gray',
                                'sin-tramites', 'tramites-vencidos' => 'danger',
                                default => 'info',
                            }),
                    ]),

                Section::make('Resumen de la operacion')
                    ->description('Informacion principal de la operacion que el gestor necesita revisar primero.')
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('reference')
                            ->label('Operacion')
                            ->weight('bold'),

                        TextEntry::make('status')
                            ->label('Estado')
                            ->badge()
                            ->color(fn (Operacion $record): string => $record->statusColor())
                            ->formatStateUsing(fn (?string $state): string => Operacion::statusOptions()[$state] ?? 'Pendiente'),

                        TextEntry::make('documentation_status')
                            ->label('Documentacion')
                            ->badge()
                            ->state(fn (Operacion $record): string => $record->documentationStatusLabel())
                            ->color(fn (Operacion $record): string => $record->documentationStatusColor()),

                        TextEntry::make('operation_date')
                            ->label('Fecha de la operacion')
                            ->date('d/m/Y')
                            ->placeholder('Sin definir'),

                        TextEntry::make('estimated_filming_schedule')
                            ->label('Horario de rodaje estimado')
                            ->placeholder('Sin definir'),

                        TextEntry::make('google_maps_link')
                            ->label('Google Maps')
                            ->state('Abrir mapa')
                            ->url(fn (Operacion $record): ?string => $record->google_maps_link)
                            ->placeholder('Sin definir')
                            ->visible(fn (Operacion $record): bool => filled($record->google_maps_link)),

                        TextEntry::make('operation_cost')
                            ->label('Coste de operacion')
                            ->state(fn (Operacion $record): string => filled($record->operation_cost)
                                ? number_format((float) $record->operation_cost, 2, ',', '.').' EUR'
                                : 'Sin definir'),

                        TextEntry::make('address')
                            ->label('Direccion completa')
                            ->placeholder('Sin definir')
                            ->columnSpanFull(),

                        TextEntry::make('country')
                            ->label('Pais')
                            ->placeholder('Sin definir'),

                        TextEntry::make('city')
                            ->label('Ciudad')
                            ->placeholder('Sin definir'),

                        TextEntry::make('province')
                            ->label('Provincia')
                            ->placeholder('Sin definir'),

                        TextEntry::make('postal_code')
                            ->label('Codigo postal')
                            ->placeholder('Sin definir'),

                        TextEntry::make('altitude')
                            ->label('Altitud')
                            ->suffix(' m')
                            ->numeric(maxDecimalPlaces: 2)
                            ->placeholder('Sin definir'),

                        TextEntry::make('operation_radius')
                            ->label('Radio operacion')
                            ->suffix(' m')
                            ->numeric(maxDecimalPlaces: 2)
                            ->placeholder('Sin definir'),

                        TextEntry::make('video_objective')
                            ->label('Objetivo del video')
                            ->placeholder('Sin definir'),

                        TextEntry::make('end_client')
                            ->label('Cliente final')
                            ->placeholder('Sin definir'),

                        TextEntry::make('production_company_name')
                            ->label('Productora')
                            ->placeholder('Sin definir'),

                        TextEntry::make('production_contact_phone')
                            ->label('Contacto en set')
                            ->placeholder('Sin definir'),

                        TextEntry::make('environment_type')
                            ->label('Interior o exterior')
                            ->formatStateUsing(fn (?string $state): string => match ($state) {
                                'interior' => 'Interior',
                                'exterior' => 'Exterior',
                                default => 'Sin definir',
                            }),

                        IconEntry::make('people_present')
                            ->label('Hay gente')
                            ->boolean()
                            ->placeholder('Sin definir'),

                        TextEntry::make('prior_permits_notes')
                            ->label('Permisos previos necesarios')
                            ->placeholder('Sin definir')
                            ->columnSpanFull(),

                        TextEntry::make('operational_conditions')
                            ->label('Condiciones operativas')
                            ->placeholder('Sin definir')
                            ->columnSpanFull(),
                    ])
                    ->columns(3),

                Section::make('Cliente')
                    ->description('Ficha completa del cliente vinculada a esta operacion.')
                    ->schema([
                        TextEntry::make('cliente_full_name')
                            ->label('Cliente')
                            ->state(fn (Operacion $record): ?string => $record->cliente?->fullName())
                            ->url(fn (Operacion $record): ?string => $record->cliente ? ClienteResource::getUrl('edit', ['record' => $record->cliente_id]) : null)
                            ->weight('bold')
                            ->placeholder('Sin definir'),

                        TextEntry::make('cliente.email')
                            ->label('Email de acceso')
                            ->placeholder('Sin definir'),

                        TextEntry::make('cliente.personal_email')
                            ->label('Correo personal')
                            ->placeholder('Sin definir'),

                        TextEntry::make('cliente.phone')
                            ->label('Telefono')
                            ->placeholder('Sin definir'),

                        TextEntry::make('cliente.dni')
                            ->label('DNI / NIE')
                            ->placeholder('Sin definir'),

                        TextEntry::make('cliente.birth_date')
                            ->label('Fecha de nacimiento')
                            ->date('d/m/Y')
                            ->placeholder('Sin definir'),

                        TextEntry::make('cliente.address')
                            ->label('Direccion')
                            ->placeholder('Sin definir')
                            ->columnSpanFull(),

                        TextEntry::make('cliente.country')
                            ->label('Pais')
                            ->placeholder('Sin definir'),

                        TextEntry::make('cliente.city')
                            ->label('Ciudad')
                            ->placeholder('Sin definir'),

                        TextEntry::make('cliente.province')
                            ->label('Provincia')
                            ->placeholder('Sin definir'),

                        TextEntry::make('cliente.postal_code')
                            ->label('Codigo postal')
                            ->placeholder('Sin definir'),
                    ])
                    ->columns(2),

                Section::make('Operadora')
                    ->description('Datos del certificado operador y estado general del expediente.')
                    ->headerActions([
                        Action::make('downloadOperadoraCertificate')
                            ->label('Certificado operador')
                            ->icon('heroicon-m-document-arrow-down')
                            ->color('gray')
                            ->visible(fn (Operacion $record): bool => static::operadoraRequirementDocument($record) !== null)
                            ->action(fn (Operacion $record) => static::downloadOperadoraRequirementDocument($record)),
                    ])
                    ->schema([
                        TextEntry::make('operadora_full_name')
                            ->label('Titular del certificado')
                            ->state(fn (Operacion $record): ?string => trim(implode(' ', array_filter([
                                $record->cliente?->operadoraProfile?->first_name,
                                $record->cliente?->operadoraProfile?->last_name,
                                $record->cliente?->operadoraProfile?->second_last_name,
                            ]))) ?: null)
                            ->placeholder('Sin definir'),

                        TextEntry::make('operadora_registration_number')
                            ->label('Numero de registro')
                            ->state(fn (Operacion $record): ?string => $record->cliente?->operadoraProfile?->registration_number)
                            ->placeholder('Sin definir'),

                        TextEntry::make('operadora_expiration_date')
                            ->label('Fecha de caducidad')
                            ->state(fn (Operacion $record): mixed => $record->cliente?->operadoraProfile?->expiration_date)
                            ->date('d/m/Y')
                            ->placeholder('Sin definir'),

                        TextEntry::make('operadora_requirements_summary')
                            ->label('Expediente operadora')
                            ->state(function (Operacion $record): string {
                                $requirements = $record->cliente?->operadoraRequirements ?? collect();

                                $pending = $requirements->where('status', OperadoraRequirement::STATUS_PENDING)->count();
                                $review = $requirements->where('status', OperadoraRequirement::STATUS_IN_REVIEW)->count();
                                $changes = $requirements->where('status', OperadoraRequirement::STATUS_NEEDS_CHANGES)->count();
                                $approved = $requirements->where('status', OperadoraRequirement::STATUS_APPROVED)->count();

                                return "Pendientes {$pending} - Revision {$review} - Corregir {$changes} - Aprobados {$approved}";
                            })
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('Piloto vinculado')
                    ->description('Informacion completa del piloto asignado a esta operacion.')
                    ->headerActions([
                        Action::make('downloadPilotDniFront')
                            ->label('DNI frontal')
                            ->icon('heroicon-m-identification')
                            ->color('gray')
                            ->visible(fn (Operacion $record): bool => filled($record->piloto?->dni_front_path))
                            ->action(fn (Operacion $record) => static::downloadPilotDocument($record, 'dni_front_path')),

                        Action::make('downloadPilotDniBack')
                            ->label('DNI trasero')
                            ->icon('heroicon-m-identification')
                            ->color('gray')
                            ->visible(fn (Operacion $record): bool => filled($record->piloto?->dni_back_path))
                            ->action(fn (Operacion $record) => static::downloadPilotDocument($record, 'dni_back_path')),

                        Action::make('downloadPilotRadiofonista')
                            ->label('Radiofonista')
                            ->icon('heroicon-m-arrow-down-tray')
                            ->color('info')
                            ->visible(fn (Operacion $record): bool => filled($record->piloto?->radiofonista_certificate_path))
                            ->action(fn (Operacion $record) => static::downloadPilotDocument($record, 'radiofonista_certificate_path')),

                        Action::make('downloadPilotTheory')
                            ->label('Teorico')
                            ->icon('heroicon-m-document-arrow-down')
                            ->color('success')
                            ->visible(fn (Operacion $record): bool => filled($record->piloto?->theoretical_certificate_path))
                            ->action(fn (Operacion $record) => static::downloadPilotDocument($record, 'theoretical_certificate_path')),

                        Action::make('downloadPilotPractical')
                            ->label('Practico')
                            ->icon('heroicon-m-document-arrow-down')
                            ->color('warning')
                            ->visible(fn (Operacion $record): bool => filled($record->piloto?->practical_certificate_path))
                            ->action(fn (Operacion $record) => static::downloadPilotDocument($record, 'practical_certificate_path')),
                    ])
                    ->schema([
                        TextEntry::make('piloto_full_name')
                            ->label('Piloto')
                            ->state(fn (Operacion $record): ?string => $record->piloto?->fullName())
                            ->weight('bold')
                            ->placeholder('Sin definir'),

                        TextEntry::make('piloto.dni_nie')
                            ->label('DNI / NIE')
                            ->placeholder('Sin definir'),

                        TextEntry::make('piloto.birth_date')
                            ->label('Fecha de nacimiento')
                            ->date('d/m/Y')
                            ->placeholder('Sin definir'),

                        TextEntry::make('piloto.pilot_identification_number')
                            ->label('Numero identificacion piloto')
                            ->placeholder('Sin definir'),

                        TextEntry::make('piloto.theoretical_certificate_level')
                            ->label('Certificado teorico')
                            ->formatStateUsing(fn (?string $state): string => Piloto::theoreticalCertificateOptions()[$state] ?? 'Sin definir'),

                        IconEntry::make('piloto.has_radiofonista_certificate')
                            ->label('Radiofonista')
                            ->boolean()
                            ->placeholder('Sin definir'),

                        TextEntry::make('piloto.phone')
                            ->label('Telefono')
                            ->placeholder('Sin definir'),

                        TextEntry::make('piloto.address')
                            ->label('Direccion')
                            ->placeholder('Sin definir')
                            ->columnSpanFull(),

                        TextEntry::make('piloto.country')
                            ->label('Pais')
                            ->placeholder('Sin definir'),

                        TextEntry::make('piloto.city')
                            ->label('Ciudad')
                            ->placeholder('Sin definir'),

                        TextEntry::make('piloto.province')
                            ->label('Provincia')
                            ->placeholder('Sin definir'),

                        TextEntry::make('piloto.postal_code')
                            ->label('Codigo postal')
                            ->placeholder('Sin definir'),
                    ])
                    ->columns(2),

                Section::make('Dron vinculado')
                    ->description('Datos tecnicos y de seguro del dron asociado a la operacion.')
                    ->headerActions([
                        Action::make('downloadDroneInsurance')
                            ->label('Descargar seguro')
                            ->icon('heroicon-m-document-arrow-down')
                            ->color('gray')
                            ->visible(fn (Operacion $record): bool => filled($record->dron?->insurance_coverage_policy_path))
                            ->action(fn (Operacion $record) => static::downloadDroneInsuranceDocument($record)),
                    ])
                    ->schema([
                        TextEntry::make('dron_label')
                            ->label('Dron')
                            ->state(fn (Operacion $record): string => trim(($record->dron?->manufacturer_name ?? '').' '.($record->dron?->model ?? '')) ?: 'Sin definir')
                            ->weight('bold'),

                        TextEntry::make('dron.uas_class')
                            ->label('Clase de UAS')
                            ->formatStateUsing(fn (?string $state): string => Dron::uasClassOptions()[$state] ?? 'Sin definir'),

                        TextEntry::make('dron.drone_serial_number')
                            ->label('Numero de serie dron')
                            ->placeholder('Sin definir'),

                        TextEntry::make('dron.controller_serial_number')
                            ->label('Numero de serie controladora')
                            ->placeholder('Sin definir'),

                        TextEntry::make('dron.registration_number')
                            ->label('Matricula')
                            ->state(fn (Operacion $record): string => $record->dron?->registrationLabel() ?? 'Sin definir'),

                        TextEntry::make('dron.remote_id_number')
                            ->label('Numero ID remoto')
                            ->state(fn (Operacion $record): string => $record->dron?->remoteIdLabel() ?? 'Sin definir'),

                        TextEntry::make('dron.mtom_weight')
                            ->label('Peso MTOM')
                            ->suffix(' g')
                            ->numeric(maxDecimalPlaces: 2)
                            ->placeholder('Sin definir'),

                        TextEntry::make('dron.class_marking')
                            ->label('Marcado de clase')
                            ->placeholder('Sin definir'),

                        TextEntry::make('dron.band_frequency')
                            ->label('Banda y frecuencia')
                            ->placeholder('Sin definir'),

                        TextEntry::make('dron.color')
                            ->label('Color')
                            ->placeholder('Sin definir'),

                        TextEntry::make('dron.payload')
                            ->label('Carga de pago')
                            ->state(fn (Operacion $record): ?string => $record->dron?->payload_not_applicable ? 'No aplica' : $record->dron?->payload)
                            ->placeholder('Sin definir')
                            ->columnSpanFull(),

                        TextEntry::make('dron.vhf_equipment')
                            ->label('Equipo comunicaciones VHF')
                            ->state(fn (Operacion $record): ?string => $record->dron?->vhf_not_applicable ? 'No aplica' : $record->dron?->vhf_equipment)
                            ->placeholder('Sin definir'),

                        TextEntry::make('dron.emergency_equipment')
                            ->label('Equipo de emergencia')
                            ->state(fn (Operacion $record): ?string => $record->dron?->emergency_not_applicable ? 'No aplica' : $record->dron?->emergency_equipment)
                            ->placeholder('Sin definir'),

                        TextEntry::make('dron.insurance_policy_number')
                            ->label('Numero poliza seguro')
                            ->placeholder('Sin definir'),

                        TextEntry::make('dron.insurance_valid_until')
                            ->label('Validez seguro')
                            ->date('d/m/Y')
                            ->placeholder('Sin definir'),

                        TextEntry::make('dron.insurance_company_name')
                            ->label('Entidad aseguradora')
                            ->placeholder('Sin definir'),

                        TextEntry::make('dron.aesa_registration_status')
                            ->label('Registrado en AESA')
                            ->state(fn (Operacion $record): string => $record->dron?->aesaRegistrationLabel() ?? 'Sin definir'),
                    ])
                    ->columns(2),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            OperacionTramitesRelationManager::class,
        ];
    }

    protected static function dashboardFocusMessage(Operacion $record): string
    {
        $tramiteId = request()->query('tramite');

        if ($tramiteId) {
            $tramite = $record->tramites()->find($tramiteId);

            if ($tramite) {
                $deadline = $tramite->deadline_date?->format('d/m/Y') ?? 'sin fecha límite';

                return "Trámite a gestionar: {$tramite->title}. Fecha límite para tramitar: {$deadline}.";
            }
        }

        return match (request()->query('focus')) {
            'sin-tramites' => 'Operación confirmada sin trámites. Crea los trámites necesarios en el bloque Trámites de operación.',
            'tramites-vencidos' => 'Hay trámites vencidos sin fecha de tramitación. Revisa el bloque Trámites de operación.',
            'tramites-7-dias', 'tramite-7-dias' => 'Hay trámites con fecha límite dentro de los próximos 7 días.',
            'tramites-pendientes' => 'Hay trámites pendientes de tramitar.',
            'operacion-hoy' => 'Esta operación está programada para hoy. Revisa horario, piloto, dron y trámites antes de cerrarla.',
            'pendiente-confirmar' => 'Esta operación está pendiente de decisión. Revisa los datos y cambia el estado cuando el gestor la cierre con el cliente.',
            'operacion-rechazada' => 'Esta operación está rechazada. Revisa el historial solo si necesitas recuperar contexto.',
            'documentacion-completa' => 'La documentación de esta operación aparece completa.',
            default => 'Revisa esta operación desde el dashboard del gestor.',
        };
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOperaciones::route('/'),
            'view' => ViewOperacion::route('/{record}'),
        ];
    }
}
