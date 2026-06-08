<?php

namespace App\Filament\Resources\Clientes\Pages;

use App\Filament\Resources\Clientes\ClienteResource;
use App\Models\Cliente;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class EditCliente extends EditRecord
{
    protected static string $resource = ClienteResource::class;

    /**
     * Si el gestor cambia nombre o email del cliente,
     * sincronizamos tambien su usuario de acceso.
     *
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return DB::transaction(function () use ($record, $data): Model {
            $newPassword = $data['new_password'] ?? null;

            // Estos campos solo afectan al usuario autenticado del cliente,
            // asi que no deben terminar guardados sobre la ficha cliente.
            unset($data['new_password'], $data['new_password_confirmation']);

            $data['profile_completed'] = $record->profileIsComplete($data);

            $record->update($data);

            if ($record->user) {
                $userData = [
                    'name' => trim(implode(' ', array_filter([
                        $data['name'] ?? null,
                        $data['last_name'] ?? null,
                        $data['second_last_name'] ?? null,
                    ]))),
                    'email' => $data['email'],
                ];

                // Si el gestor informa una nueva contrasena, la reemplazamos
                // para que el cliente pueda acceder con ese nuevo valor.
                if (filled($newPassword)) {
                    $userData['password'] = $newPassword;
                }

                $record->user->update($userData);
            }

            return $record;
        });
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn (Cliente $record): bool => $record->canBeDeletedSafely()),
        ];
    }
}
