<?php

namespace App\Filament\Resources\Clientes\RelationManagers;

use App\Models\OperadoraRequirement;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class OperadoraRequirementsRelationManager extends RelationManager
{
    protected static string $relationship = 'operadoraRequirements';

    protected static ?string $title = 'Expediente Operadora';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Cada requisito se gestiona por separado para que el gestor
                // pueda crearlo, editarlo o borrarlo sin pelear con un repeater largo.
                TextInput::make('name')
                    ->label('Nombre del requisito')
                    ->required()
                    ->maxLength(255),

                Select::make('input_type')
                    ->label('Tipo')
                    ->options(OperadoraRequirement::inputTypeOptions())
                    ->required()
                    ->default(OperadoraRequirement::TYPE_PDF)
                    ->native(false),

                Toggle::make('is_required')
                    ->label('Obligatorio')
                    ->default(true),

                Textarea::make('instructions')
                    ->label('Instrucciones')
                    ->rows(4)
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Requisito')
                    ->searchable(),

                TextColumn::make('input_type')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => OperadoraRequirement::inputTypeOptions()[$state] ?? $state),

                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => OperadoraRequirement::statusOptions()[$state] ?? $state),

                IconColumn::make('is_required')
                    ->label('Obligatorio')
                    ->boolean(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Add to Operadora requirements'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
