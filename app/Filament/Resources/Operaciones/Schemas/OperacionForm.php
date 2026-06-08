<?php

namespace App\Filament\Resources\Operaciones\Schemas;

use App\Models\Cliente;
use App\Models\Dron;
use App\Models\Piloto;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rule;

class OperacionForm
{
    public static function configure(Schema $schema, ?Cliente $cliente = null): Schema
    {
        return $schema
            ->components(static::components($cliente))
            ->columns(2);
    }

    /**
     * @return array<int, mixed>
     */
    public static function components(?Cliente $cliente = null): array
    {
        return [
            Section::make('Datos principales')
                ->schema([
                    Select::make('cliente_id')
                        ->label('Cliente')
                        ->options(fn (): array => static::clienteOptions())
                        ->searchable()
                        ->required()
                        ->native(false)
                        ->live()
                        ->afterStateUpdated(function (Set $set): void {
                            $set('piloto_id', null);
                            $set('dron_id', null);
                        })
                        ->visible(fn (): bool => $cliente === null),

                    TextInput::make('reference')
                        ->label('Nombre de la operacion')
                        ->required()
                        ->maxLength(255),

                    DatePicker::make('operation_date')
                        ->label('Fecha de la operacion')
                        ->displayFormat('d/m/Y')
                        ->native(false)
                        ->closeOnDateSelection()
                        ->required(),

                    TextInput::make('estimated_filming_schedule')
                        ->label('Horario de rodaje estimado')
                        ->required()
                        ->maxLength(255),

                    Select::make('piloto_id')
                        ->label('Piloto')
                        ->options(fn (Get $get): array => static::pilotoOptions($cliente?->getKey() ?? $get('cliente_id')))
                        ->searchable()
                        ->required()
                        ->rule(fn (Get $get) => static::clienteScopedExistsRule('pilotos', $cliente?->getKey() ?? $get('cliente_id')))
                        ->native(false),

                    Select::make('dron_id')
                        ->label('Dron')
                        ->options(fn (Get $get): array => static::dronOptions($cliente?->getKey() ?? $get('cliente_id')))
                        ->searchable()
                        ->required()
                        ->rule(fn (Get $get) => static::clienteScopedExistsRule('drones', $cliente?->getKey() ?? $get('cliente_id')))
                        ->native(false),
                ])
                ->columns(2),

            Section::make('Ubicacion y parametros')
                ->schema([
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
                ])
                ->columns(2),

            Section::make('Briefing')
                ->schema([
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
                ->columns(2),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function mutateData(array $data, mixed $clienteId = null): array
    {
        if (filled($clienteId)) {
            $data['cliente_id'] = $clienteId;
        }

        $data['location'] = $data['address'] ?? null;
        $data['description'] = $data['prior_permits_notes'] ?? ($data['extra_information'] ?? null);

        return $data;
    }

    protected static function clienteScopedExistsRule(string $table, mixed $clienteId): mixed
    {
        return Rule::exists($table, 'id')
            ->where('cliente_id', filled($clienteId) ? $clienteId : 0);
    }

    /**
     * @return array<int|string, string>
     */
    protected static function clienteOptions(): array
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
    protected static function pilotoOptions(mixed $clienteId): array
    {
        if (blank($clienteId)) {
            return [];
        }

        return Piloto::query()
            ->where('cliente_id', $clienteId)
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
    protected static function dronOptions(mixed $clienteId): array
    {
        if (blank($clienteId)) {
            return [];
        }

        return Dron::query()
            ->where('cliente_id', $clienteId)
            ->orderBy('manufacturer_name')
            ->orderBy('model')
            ->get()
            ->mapWithKeys(fn (Dron $dron): array => [
                $dron->getKey() => $dron->displayNameWithSerial(),
            ])
            ->all();
    }
}
