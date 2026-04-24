<?php

namespace App\Filament\Resources\Operaciones;

use App\Filament\Resources\Clientes\ClienteResource;
use App\Filament\Resources\Operaciones\Pages\ListOperaciones;
use App\Filament\Resources\Operaciones\Pages\ViewOperacion;
use App\Models\Cliente;
use App\Models\Dron;
use App\Models\Operacion;
use App\Models\OperadoraRequirement;
use App\Models\Piloto;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class OperacionResource extends Resource
{
    protected static ?string $model = Operacion::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationLabel = 'Operaciones';

    protected static ?string $modelLabel = 'Operacion';

    protected static ?string $pluralModelLabel = 'Operaciones';

    protected static ?string $recordTitleAttribute = 'reference';

    protected static ?int $navigationSort = 2;

    protected static function activeOperationsFrom(): string
    {
        return Carbon::today(config('app.timezone'))->subDays(2)->toDateString();
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with([
            'cliente.operadoraProfile',
            'cliente.operadoraRequirements',
            'piloto',
            'dron',
        ]);
    }

    public static function table(Table $table): Table
    {
        $activeFrom = static::activeOperationsFrom();

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->whereDate('operation_date', '>=', $activeFrom))
            ->columns([
                TextColumn::make('reference')
                    ->label('Operacion')
                    ->searchable()
                    ->sortable()
                    ->description(fn (Operacion $record): ?string => $record->estimated_filming_schedule ?: null),

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
                    ->toggleable(),

                TextColumn::make('address')
                    ->label('Ubicacion')
                    ->state(fn (Operacion $record): string => $record->address ?: $record->location ?: 'Sin definir')
                    ->toggleable(),

                TextColumn::make('environment_type')
                    ->label('Entorno')
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'interior' => 'Interior',
                        'exterior' => 'Exterior',
                        default => 'Sin definir',
                    })
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('operation_date')
            ->recordActions([
                ViewAction::make()
                    ->label('Gestionar'),
            ])
            ->recordAction('view');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Resumen de la operacion')
                    ->description('Informacion principal de la operacion que el gestor necesita revisar primero.')
                    ->schema([
                        TextEntry::make('reference')
                            ->label('Operacion')
                            ->weight('bold'),
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
                    ])
                    ->columns(2),

                Section::make('Cliente')
                    ->description('Ficha completa del cliente vinculada a esta operacion.')
                    ->schema([
                        TextEntry::make('cliente_full_name')
                            ->label('Cliente')
                            ->state(fn (Operacion $record): ?string => $record->cliente?->fullName())
                            ->url(fn (Operacion $record): ?string => $record->cliente ? ClienteResource::getUrl('edit', ['record' => $record->cliente_id]) : null)
                            ->weight('bold')
                            ->placeholder('Sin definir'),
                        TextEntry::make('cliente.client_type')
                            ->label('Tipo')
                            ->formatStateUsing(fn (?string $state): string => Cliente::typeOptions()[$state] ?? 'Sin definir')
                            ->badge(),
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
                            ->formatStateUsing(fn (?string $state): string => Dron::aesaRegistrationOptions()[$state] ?? 'Sin definir'),
                    ])
                    ->columns(2),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOperaciones::route('/'),
            'view' => ViewOperacion::route('/{record}'),
        ];
    }
}
