<?php

namespace App\Filament\Resources\Clientes\Pages;

use App\Filament\Resources\Clientes\ClienteResource;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CreateCliente extends CreateRecord
{
    protected static string $resource = ClienteResource::class;

    /**
     * Cuando el gestor crea un cliente, tambien creamos su usuario de acceso.
     * Asi el MVP refleja el flujo real del negocio desde el primer alta.
     *
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        return DB::transaction(function () use ($data): Model {
            $password = $data['password'];

            // Estos campos pertenecen al usuario de acceso y no deben
            // terminar guardados en la tabla clientes.
            unset($data['password'], $data['password_confirmation']);

            $user = User::create([
                // El usuario usa nombre completo para que la parte autenticada
                // del cliente muestre una identidad coherente desde el principio.
                'name' => trim("{$data['name']} {$data['last_name']}"),
                'email' => $data['email'],
                'password' => $password,
                'role' => User::ROLE_CLIENTE,
            ]);

            $data['user_id'] = $user->id;
            $data['profile_completed'] = false;

            return static::getModel()::create($data);
        });
    }

    protected function getRedirectUrl(): string
    {
        // Primero se crea el cliente y, justo despues, llevamos al gestor
        // a la pantalla de edicion para que defina requisitos de operadora.
        return static::getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
