<?php

namespace App\Filament\Resources\Operaciones\Widgets;

use App\Filament\Resources\Operaciones\OperacionResource;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class UpcomingOperacionesTableWidget extends TableWidget
{
    protected int | string | array $columnSpan = 'full';

    protected static ?string $heading = 'Proximas operaciones';

    public function table(Table $table): Table
    {
        return OperacionResource::buildOperationsTable(
            $table->query(OperacionResource::getEloquentQuery()),
            onlyUpcoming: true,
            heading: static::$heading,
        );
    }
}
