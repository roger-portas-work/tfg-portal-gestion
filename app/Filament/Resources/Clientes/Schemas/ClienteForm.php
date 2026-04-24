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

                                // La contrasena pertenece al usuario de acceso, no a la ficha
                                // del cliente. La pedimos aqui solo cuando el gestor da el alta
                                // inicial para que el cliente salga ya con acceso definitivo.
                                TextInput::make('password')
                                    ->label('Contrasena de acceso')
                                    ->password()
                                    ->revealable()
                                    ->required(fn (string $operation): bool => $operation === 'create')
                                    ->visible(fn (string $operation): bool => $operation === 'create')
                                    ->minLength(8)
                                    ->maxLength(255),

                                // Confirmamos la contrasena en el alta para evitar errores
                                // del gestor al crear el acceso del cliente.
                                TextInput::make('password_confirmation')
                                    ->label('Confirmar contrasena')
                                    ->password()
                                    ->revealable()
                                    ->required(fn (string $operation): bool => $operation === 'create')
                                    ->visible(fn (string $operation): bool => $operation === 'create')
                                    ->same('password')
                                    ->dehydrated(false)
                                    ->maxLength(255),

                                TextInput::make('new_password')
                                    ->label('Nueva contrasena de acceso')
                                    ->password()
                                    ->revealable()
                                    ->visible(fn (string $operation): bool => $operation === 'edit')
                                    ->dehydrated(fn (?string $state): bool => filled($state))
                                    ->minLength(8)
                                    ->maxLength(255),

                                TextInput::make('new_password_confirmation')
                                    ->label('Confirmar nueva contrasena')
                                    ->password()
                                    ->revealable()
                                    ->visible(fn (string $operation): bool => $operation === 'edit')
                                    ->same('new_password')
                                    ->dehydrated(false)
                                    ->maxLength(255),
                            ]),
                    ]),

                Section::make('Ficha cliente')
                    ->description('Aqui el gestor puede consultar y corregir la informacion real que el cliente ha rellenado en su ficha.')
                    ->visible(fn (string $operation): bool => $operation === 'edit')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('second_last_name')
                                    ->label('Segundo apellido')
                                    ->maxLength(255),

                                TextInput::make('personal_email')
                                    ->label('Correo personal')
                                    ->email()
                                    ->maxLength(255),

                                TextInput::make('dni')
                                    ->label('DNI / NIE')
                                    ->maxLength(30),

                                TextInput::make('birth_date')
                                    ->label('Fecha de nacimiento')
                                    ->type('date'),

                                TextInput::make('address')
                                    ->label('Direccion completa')
                                    ->maxLength(255)
                                    ->columnSpanFull(),

                                TextInput::make('country')
                                    ->label('Pais')
                                    ->maxLength(255),

                                TextInput::make('city')
                                    ->label('Ciudad')
                                    ->maxLength(255),

                                TextInput::make('province')
                                    ->label('Provincia')
                                    ->maxLength(255),

                                TextInput::make('postal_code')
                                    ->label('Codigo postal')
                                    ->maxLength(20),
                            ]),
                    ]),
            ]);
    }
}
