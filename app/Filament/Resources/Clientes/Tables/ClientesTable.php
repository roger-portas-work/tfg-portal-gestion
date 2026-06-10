<?php

namespace App\Filament\Resources\Clientes\Tables;

use App\Models\Cliente;
use App\Models\Operacion;
use App\Models\OperadoraRequirement;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class ClientesTable
{
    protected static function operationsSummary(Cliente $record): array
    {
        $active = (int) ($record->operaciones_active_count ?? 0);
        $total = (int) ($record->operaciones_total_count ?? 0);

        if ($active === 0) {
            return [
                'label' => '0 activas',
                'color' => 'gray',
                'description' => "Total {$total}",
            ];
        }

        return [
            'label' => "{$active} activas",
            'color' => 'info',
            'description' => "Total {$total}",
        ];
    }

    protected static function operadoraSummary(Cliente $record): array
    {
        $total = (int) ($record->operadora_requirements_count ?? 0);
        $requiredTotal = (int) ($record->operadora_required_count ?? 0);
        $requiredPending = (int) ($record->operadora_required_pending_count ?? 0);
        $requiredInReview = (int) ($record->operadora_required_in_review_count ?? 0);
        $requiredApproved = (int) ($record->operadora_required_approved_count ?? 0);
        $requiredNeedsChanges = (int) ($record->operadora_required_needs_changes_count ?? 0);
        $optionalPending = (int) ($record->operadora_optional_pending_count ?? 0);

        if ($total === 0) {
            return [
                'label' => 'Sin requisitos',
                'color' => 'gray',
                'description' => 'Todavia no se ha definido el expediente.',
            ];
        }

        if ($requiredInReview > 0) {
            return [
                'label' => "En revision {$requiredInReview}",
                'color' => 'warning',
                'description' => "Pendientes {$requiredPending} - Revision {$requiredInReview} - Corregir {$requiredNeedsChanges}",
            ];
        }

        $requiredResolved = ($requiredPending === 0) && ($requiredNeedsChanges === 0) && ($requiredInReview === 0);

        if (($requiredTotal === 0) || ($requiredResolved && ($requiredApproved === $requiredTotal))) {
            $optionalHint = $optionalPending > 0
                ? " - Opcionales pendientes {$optionalPending}"
                : '';

            return [
                'label' => 'Correcto',
                'color' => 'success',
                'description' => "Pendientes {$requiredPending} - Revision {$requiredInReview} - Corregir {$requiredNeedsChanges}{$optionalHint}",
            ];
        }

        if ($requiredNeedsChanges > $requiredPending) {
            return [
                'label' => "Corregir {$requiredNeedsChanges}",
                'color' => 'gray',
                'description' => "Pendientes {$requiredPending} - Revision {$requiredInReview} - Corregir {$requiredNeedsChanges}",
            ];
        }

        return [
            'label' => "Pendientes {$requiredPending}",
            'color' => 'danger',
            'description' => "Pendientes {$requiredPending} - Revision {$requiredInReview} - Corregir {$requiredNeedsChanges}",
        ];
    }

    public static function configure(Table $table): Table
    {
        $activeOperationsFrom = Carbon::today(config('app.timezone'))->subDays(2)->toDateString();

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount([
                'operaciones as operaciones_total_count',
                'operaciones as operaciones_active_count' => fn (Builder $query) => $query
                    ->whereDate('operation_date', '>=', $activeOperationsFrom)
                    ->where(function (Builder $query): void {
                        $query
                            ->whereNull('status')
                            ->orWhere('status', '!=', Operacion::STATUS_REJECTED);
                    }),
                'operadoraRequirements',
                'operadoraRequirements as operadora_required_count' => fn (Builder $query) => $query
                    ->where('is_required', true),
                'operadoraRequirements as operadora_required_pending_count' => fn (Builder $query) => $query
                    ->where('is_required', true)
                    ->where('status', OperadoraRequirement::STATUS_PENDING),
                'operadoraRequirements as operadora_required_in_review_count' => fn (Builder $query) => $query
                    ->where('is_required', true)
                    ->where('status', OperadoraRequirement::STATUS_IN_REVIEW),
                'operadoraRequirements as operadora_required_approved_count' => fn (Builder $query) => $query
                    ->where('is_required', true)
                    ->where('status', OperadoraRequirement::STATUS_APPROVED),
                'operadoraRequirements as operadora_required_needs_changes_count' => fn (Builder $query) => $query
                    ->where('is_required', true)
                    ->where('status', OperadoraRequirement::STATUS_NEEDS_CHANGES),
                'operadoraRequirements as operadora_optional_pending_count' => fn (Builder $query) => $query
                    ->where('is_required', false)
                    ->where('status', OperadoraRequirement::STATUS_PENDING),
            ]))
            ->columns([
                TextColumn::make('name')
                    ->label('Nombre')
                    ->description(fn (Cliente $record): ?string => $record->last_name)
                    ->searchable()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('profile_completed')
                    ->label('Ficha')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-clock')
                    ->trueColor('success')
                    ->falseColor('warning'),

                TextColumn::make('operadora_requirements_count')
                    ->label('Operadora')
                    ->badge()
                    ->state(fn (Cliente $record): string => static::operadoraSummary($record)['label'])
                    ->color(fn (Cliente $record): string => static::operadoraSummary($record)['color'])
                    ->description(fn (Cliente $record): ?string => static::operadoraSummary($record)['description']),

                TextColumn::make('operaciones_active_count')
                    ->label('Operaciones')
                    ->badge()
                    ->state(fn (Cliente $record): string => static::operationsSummary($record)['label'])
                    ->color(fn (Cliente $record): string => static::operationsSummary($record)['color'])
                    ->description(fn (Cliente $record): ?string => static::operationsSummary($record)['description']),

                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('phone')
                    ->label('Telefono')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                EditAction::make()
                    ->label('Editar')
                    ->hiddenLabel()
                    ->icon('heroicon-m-pencil-square')
                    ->extraAttributes(['class' => 'hidden']),
                DeleteAction::make()
                    ->visible(fn (Cliente $record): bool => $record->canBeDeletedSafely()),
            ])
            ->recordAction('edit');
    }
}
