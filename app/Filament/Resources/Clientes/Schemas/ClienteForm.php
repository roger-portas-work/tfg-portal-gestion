<?php

namespace App\Filament\Resources\Clientes\Schemas;

use App\Models\Cliente;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rule;

class ClienteForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Datos basicos')
                    ->description('El gestor solo da de alta los datos minimos. El resto de la ficha la completara despues el cliente.')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                // El nombre sera la referencia principal del cliente
                                // en el panel y en futuros modulos del MVP.
                                TextInput::make('name')
                                    ->label('Nombre')
                                    ->required()
                                    ->maxLength(255),

                                // El apellido forma parte del alta minima del gestor
                                // para que luego el cliente ya entre con sus datos base correctos.
                                TextInput::make('last_name')
                                    ->label('Apellido')
                                    ->required()
                                    ->maxLength(255),

                                // Este campo prepara la futura diferencia entre
                                // cliente fisico y juridico sin complicar todavia la logica.
                                Select::make('client_type')
                                    ->label('Tipo de cliente')
                                    ->options(Cliente::typeOptions())
                                    ->required()
                                    ->default(Cliente::TYPE_FISICO)
                                    ->native(false),

                                TextInput::make('email')
                                    ->label('Email')
                                    ->email()
                                    ->required()
                                    // El email debe ser valido tambien como usuario de acceso,
                                    // por eso comprobamos unicidad contra la tabla users.
                                    ->rule(fn (?Cliente $record) => Rule::unique(User::class, 'email')->ignore($record?->user_id))
                                    ->maxLength(255),

                                TextInput::make('phone')
                                    ->label('Telefono')
                                    ->tel()
                                    ->required()
                                    ->maxLength(30),
                            ]),
                    ]),
            ]);
    }
}
