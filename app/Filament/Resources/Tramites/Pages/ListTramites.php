<?php

namespace App\Filament\Resources\Tramites\Pages;

use App\Filament\Resources\Tramites\TramiteResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;

class ListTramites extends ListRecords
{
    protected static string $resource = TramiteResource::class;

    public function getTitle(): string | Htmlable
    {
        return 'Tramites';
    }

    public function getSubheading(): string | Htmlable | null
    {
        return 'Cola global separada por trabajo pendiente, seguimiento de tramites procesados e historial.';
    }

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        return [
            'vencidos' => Tab::make('Tramites vencidos')
                ->modifyQueryUsing(fn (Builder $query): Builder => TramiteResource::applyOverdueTabQuery($query)),

            'pendientes' => Tab::make('Tramites pendientes')
                ->modifyQueryUsing(fn (Builder $query): Builder => TramiteResource::applyPendingTabQuery($query)),

            'procesados' => Tab::make('Tramites procesados')
                ->modifyQueryUsing(fn (Builder $query): Builder => TramiteResource::applyProcessedTabQuery($query)),

            'todos' => Tab::make('Todos los tramites')
                ->modifyQueryUsing(fn (Builder $query): Builder => TramiteResource::applyHistoryTabQuery($query)),
        ];
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
