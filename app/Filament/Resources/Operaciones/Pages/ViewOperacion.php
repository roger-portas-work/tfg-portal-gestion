<?php

namespace App\Filament\Resources\Operaciones\Pages;

use App\Filament\Resources\Clientes\ClienteResource;
use App\Filament\Resources\Operaciones\OperacionResource;
use Filament\Actions\Action;
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
