<?php

namespace App\Filament\Resources\Operaciones\Pages;

use App\Filament\Resources\Operaciones\OperacionResource;
use App\Filament\Resources\Operaciones\Schemas\OperacionForm;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListOperaciones extends ListRecords
{
    protected static string $resource = OperacionResource::class;

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        return [
            'prioridad' => Tab::make('Cola prioritaria')
                ->modifyQueryUsing(fn (Builder $query): Builder => OperacionResource::applyPriorityTabQuery($query)),

            'pendientes' => Tab::make('Pendientes')
                ->modifyQueryUsing(fn (Builder $query): Builder => OperacionResource::applyPendingTabQuery($query)),

            'hoy' => Tab::make('Hoy')
                ->modifyQueryUsing(fn (Builder $query): Builder => OperacionResource::applyTodayTabQuery($query)),

            'incidencias' => Tab::make('Confirmadas con incidencias')
                ->modifyQueryUsing(fn (Builder $query): Builder => OperacionResource::applyConfirmedIssuesTabQuery($query)),

            'proximas' => Tab::make('Proximas')
                ->modifyQueryUsing(fn (Builder $query): Builder => OperacionResource::applyUpcomingTabQuery($query)),

            'rechazadas' => Tab::make('Rechazadas')
                ->modifyQueryUsing(fn (Builder $query): Builder => OperacionResource::applyRejectedTabQuery($query)),

            'pasadas' => Tab::make('Pasadas')
                ->modifyQueryUsing(fn (Builder $query): Builder => OperacionResource::applyPastTabQuery($query)),
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
        return [
            CreateAction::make()
                ->label('Anadir operacion')
                ->mutateDataUsing(fn (array $data): array => OperacionForm::mutateData($data)),
        ];
    }
}
