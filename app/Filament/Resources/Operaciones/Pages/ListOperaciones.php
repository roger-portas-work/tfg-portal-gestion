<?php

namespace App\Filament\Resources\Operaciones\Pages;

use App\Filament\Resources\Operaciones\OperacionResource;
use App\Filament\Resources\Operaciones\Widgets\UpcomingOperacionesTableWidget;
use Filament\Resources\Pages\ListRecords;

class ListOperaciones extends ListRecords
{
    protected static string $resource = OperacionResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            UpcomingOperacionesTableWidget::class,
        ];
    }
}
