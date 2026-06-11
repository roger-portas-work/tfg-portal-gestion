<?php

namespace App\Filament\Resources\Operaciones\Pages;

use App\Filament\Resources\Clientes\ClienteResource;
use App\Filament\Resources\Operaciones\OperacionResource;
use App\Filament\Resources\Operaciones\Schemas\OperacionForm;
use App\Models\Operacion;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Carbon;
use Livewire\Attributes\On;

class ViewOperacion extends ViewRecord
{
    protected static string $resource = OperacionResource::class;

    #[On('operacion-tramites-updated')]
    public function refreshOperationWorkflowState(): void
    {
        $this->record = OperacionResource::getEloquentQuery()
            ->whereKey($this->getRecord()->getKey())
            ->firstOrFail();
    }

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
            Action::make('editarOperacion')
                ->label('Editar operacion')
                ->icon('heroicon-m-pencil-square')
                ->color('gray')
                ->fillForm(fn (): array => $this->record->attributesToArray())
                ->form(OperacionForm::components())
                ->action(function (array $data): void {
                    $this->record->update(OperacionForm::mutateData($data));
                    $this->record->refresh();
                })
                ->successNotificationTitle('Operacion actualizada'),

            Action::make('confirmarOperacion')
                ->label(fn (): string => $this->record->isConfirmed() ? 'Actualizar confirmacion' : 'Confirmar operacion')
                ->color('success')
                ->visible(fn (): bool => $this->record->isPending() || $this->record->isConfirmed())
                ->fillForm(fn (): array => [
                    'operation_cost' => $this->record->operation_cost,
                    'operational_conditions' => $this->record->operational_conditions,
                ])
                ->form(OperacionResource::confirmationForm())
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
                ->modalHeading('Rechazar operacion')
                ->modalDescription('La operacion pasara a rechazada y se eliminaran el coste y las condiciones operativas.')
                ->modalSubmitActionLabel('Rechazar operacion')
                ->modalCancelActionLabel('Cancelar')
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
