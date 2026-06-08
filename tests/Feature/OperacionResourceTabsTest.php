<?php

use App\Filament\Resources\Operaciones\OperacionResource;
use App\Models\Operacion;
use Illuminate\Support\Carbon;

afterEach(function (): void {
    Carbon::setTestNow();
});

test('operacion resource priority tab orders active work by operation date', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-07', config('app.timezone')));

    $query = OperacionResource::applyPriorityTabQuery(Operacion::query())->getQuery();
    $whereTypes = collect($query->wheres)->pluck('type')->all();

    expect($whereTypes)->toContain('Date')
        ->and($whereTypes)->toContain('Nested')
        ->and($query->orders)->toBe([
            ['column' => 'operation_date', 'direction' => 'asc'],
            ['column' => 'id', 'direction' => 'asc'],
        ]);
});

test('operacion resource pending tab filters null or pending status', function () {
    $query = OperacionResource::applyPendingTabQuery(Operacion::query())->getQuery();
    $whereTypes = collect($query->wheres)->pluck('type')->all();

    expect($whereTypes)->toContain('Date')
        ->and($whereTypes)->toContain('Nested')
        ->and($query->orders)->toBe([
            ['column' => 'operation_date', 'direction' => 'asc'],
            ['column' => 'id', 'direction' => 'asc'],
        ]);
});

test('operacion resource past tab shows operations whose date has passed', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-07', config('app.timezone')));

    $query = OperacionResource::applyPastTabQuery(Operacion::query())->getQuery();

    expect(collect($query->wheres)->pluck('type')->all())->toContain('Date')
        ->and($query->wheres[0]['operator'])->toBe('<')
        ->and($query->orders)->toBe([
            ['column' => 'operation_date', 'direction' => 'desc'],
            ['column' => 'id', 'direction' => 'desc'],
        ]);
});
