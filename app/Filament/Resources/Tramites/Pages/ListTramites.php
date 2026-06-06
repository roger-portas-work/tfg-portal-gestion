<?php

namespace App\Filament\Resources\Tramites\Pages;

use App\Filament\Resources\Tramites\TramiteResource;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;

class ListTramites extends ListRecords
{
    protected static string $resource = TramiteResource::class;

    public function getTitle(): string | Htmlable
    {
        return 'Tramites';
    }

    public function getSubheading(): string | Htmlable | null
    {
        return 'Cola global para revisar vencimientos, pendientes, tramitados y denegados sin perder el contexto de la operacion.';
    }

    public function hydrate(): void
    {
        $this->flushCachedTableRecords();
    }

    protected function getTablePollingInterval(): ?string
    {
        return '10s';
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
