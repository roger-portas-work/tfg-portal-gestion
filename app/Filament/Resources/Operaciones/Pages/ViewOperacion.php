<?php

namespace App\Filament\Resources\Operaciones\Pages;

use App\Filament\Resources\Clientes\ClienteResource;
use App\Filament\Resources\Operaciones\OperacionResource;
use App\Models\Operacion;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Carbon;

class ViewOperacion extends ViewRecord
{
    protected static string $resource = OperacionResource::class;

    public function getTitle(): string | Htmlable
    {
        return $this->record->reference ?: 'Gestion de operacion';
    }

    public function getSubheading(): string | Htmlable | null
    {
        $cliente = $this->record->cliente?->fullName() ?: 'Sin cliente';
        $fecha = match (true) {
            $this->record->operation_date instanceof \DateTimeInterface => $this->record->operation_date->format('d/m/Y'),
            filled($this->record->operation_date) => Carbon::parse((string) $this->record->operation_date)->format('d/m/Y'),
            default => 'Sin fecha',
        };

        return "Cliente: {$cliente} - Fecha operacion: {$fecha}";
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('confirmarOperacion')
                ->label(fn (): string => $this->record->isConfirmed() ? 'Actualizar confirmacion' : 'Confirmar operacion')
                ->color('success')
                ->visible(fn (): bool => $this->record->isPending() || $this->record->isConfirmed())
                ->fillForm(fn (): array => [
                    'operation_cost' => $this->record->operation_cost,
                    'operational_conditions' => $this->record->operational_conditions,
                ])
                ->form([
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
                ])
                ->action(function (array $data): void {
                    $this->record->update([
                        'status' => Operacion::STATUS_CONFIRMED,
                        'operation_cost' => $data['operation_cost'],
                        'operational_conditions' => $data['operational_conditions'],
                    ]);

                    $this->record->refresh();
                    $this->redirect(OperacionResource::getUrl('view', ['record' => $this->record]), navigate: true);
                })
                ->successNotificationTitle('Operacion confirmada'),

            Action::make('rechazarOperacion')
                ->label('Rechazar operacion')
                ->color('danger')
                ->visible(fn (): bool => $this->record->isPending())
                ->requiresConfirmation()
                ->action(function (): void {
                    $this->record->update([
                        'status' => Operacion::STATUS_REJECTED,
                        'operation_cost' => null,
                        'operational_conditions' => null,
                    ]);

                    $this->record->refresh();
                    $this->redirect(OperacionResource::getUrl('view', ['record' => $this->record]), navigate: true);
                })
                ->successNotificationTitle('Operacion rechazada'),

            Action::make('abrirCliente')
                ->label('Abrir cliente')
                ->url(fn (): string => ClienteResource::getUrl('edit', ['record' => $this->record->cliente_id])),

            Action::make('volverOperaciones')
                ->label('Volver a operaciones')
                ->color('gray')
                ->url(fn (): string => OperacionResource::getUrl('index')),
        ];
    }
}
