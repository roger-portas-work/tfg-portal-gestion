<?php

namespace App\Filament\Resources\Operaciones\Pages;

use App\Filament\Resources\Operaciones\OperacionResource;
use Filament\Resources\Pages\ListRecords;

class ListOperaciones extends ListRecords
{
    protected static string $resource = OperacionResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
