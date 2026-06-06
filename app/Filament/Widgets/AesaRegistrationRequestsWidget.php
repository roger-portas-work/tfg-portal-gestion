<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Clientes\ClienteResource;
use App\Models\Dron;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class AesaRegistrationRequestsWidget extends TableWidget
{
    protected int | string | array $columnSpan = 'full';

    protected static ?int $sort = 60;

    protected static ?string $heading = 'Solicitudes de registro AESA';

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Dron::query()
                ->with('cliente')
                ->where('aesa_registration_status', Dron::AESA_STATUS_MANAGER)
                ->latest('updated_at'))
            ->heading(static::$heading)
            ->columns([
                TextColumn::make('cliente_name')
                    ->label('Cliente')
                    ->state(fn (Dron $record): string => $record->cliente?->fullName() ?: 'Sin cliente')
                    ->url(fn (Dron $record): string => ClienteResource::getUrl('edit', ['record' => $record->cliente_id]))
                    ->searchable(query: function (Builder $query, string $search): void {
                        $query->whereHas('cliente', function (Builder $clienteQuery) use ($search): void {
                            $clienteQuery
                                ->where('name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%")
                                ->orWhere('second_last_name', 'like', "%{$search}%");
                        });
                    })
                    ->weight('semibold'),

                TextColumn::make('drone_name')
                    ->label('Dron')
                    ->state(function (Dron $record): string {
                        $label = trim(($record->manufacturer_name ?? '').' '.($record->model ?? ''));

                        if (filled($record->registration_number) || $record->registration_not_applicable) {
                            $label .= ' - '.$record->registrationLabel();
                        }

                        return $label ?: 'Sin definir';
                    })
                    ->searchable(query: function (Builder $query, string $search): void {
                        $query->where(function (Builder $droneQuery) use ($search): void {
                            $droneQuery
                                ->where('manufacturer_name', 'like', "%{$search}%")
                                ->orWhere('model', 'like', "%{$search}%")
                                ->orWhere('registration_number', 'like', "%{$search}%");
                        });
                    }),

                TextColumn::make('aesa_registration_status')
                    ->label('Estado AESA')
                    ->state(fn (Dron $record): string => $record->aesaRegistrationLabel())
                    ->badge()
                    ->color(fn (Dron $record): string => $record->aesaRegistrationColor()),

                TextColumn::make('updated_at')
                    ->label('Solicitado')
                    ->since()
                    ->description(fn (Dron $record): ?string => $record->updated_at?->format('d/m/Y H:i')),
            ])
            ->emptyStateHeading('No hay solicitudes pendientes de registro AESA')
            ->emptyStateDescription('Cuando un cliente marque "Gestiona gestor" en un dron, aparecera aqui.')
            ->recordActions([
                Action::make('markRegistered')
                    ->label('Marcar como registrado')
                    ->color('success')
                    ->button()
                    ->size('sm')
                    ->requiresConfirmation()
                    ->action(function (Dron $record): void {
                        $record->update([
                            'aesa_registration_status' => Dron::AESA_STATUS_YES,
                        ]);
                    }),
            ]);
    }
}
