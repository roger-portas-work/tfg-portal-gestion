<?php

use App\Filament\Resources\Tramites\TramiteResource;
use App\Models\OperacionTramite;
use Illuminate\Support\Carbon;

afterEach(function (): void {
    Carbon::setTestNow();
});

test('tramite resource pending tab filters pending tramites with deadline ordered by deadline', function () {
    $query = TramiteResource::applyPendingTabQuery(OperacionTramite::query())->getQuery();

    expect($query->wheres)->toContain([
        'type' => 'Basic',
        'column' => 'status',
        'operator' => '=',
        'value' => OperacionTramite::STATUS_PENDING,
        'boolean' => 'and',
    ])->toContain([
        'type' => 'Null',
        'column' => 'processed_at',
        'boolean' => 'and',
    ])->toContain([
        'type' => 'NotNull',
        'column' => 'deadline_date',
        'boolean' => 'and',
    ]);

    expect($query->orders)->toBe([
        ['column' => 'deadline_date', 'direction' => 'asc'],
        ['column' => 'id', 'direction' => 'asc'],
    ]);
});

test('tramite resource overdue tab filters unprocessed non approved tramites before today', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-07', config('app.timezone')));

    $query = TramiteResource::applyOverdueTabQuery(OperacionTramite::query())->getQuery();

    expect($query->wheres)->toContain([
        'type' => 'Null',
        'column' => 'processed_at',
        'boolean' => 'and',
    ])->toContain([
        'type' => 'Basic',
        'column' => 'status',
        'operator' => '!=',
        'value' => OperacionTramite::STATUS_APPROVED,
        'boolean' => 'and',
    ]);

    expect(collect($query->wheres)->pluck('type')->all())->toContain('Date')
        ->and($query->orders)->toBe([
            ['column' => 'deadline_date', 'direction' => 'asc'],
            ['column' => 'id', 'direction' => 'asc'],
        ]);
});

test('tramite resource processed tab shows oldest processed tramites first', function () {
    $query = TramiteResource::applyProcessedTabQuery(OperacionTramite::query())->getQuery();

    expect($query->wheres)->toContain([
        'type' => 'Basic',
        'column' => 'status',
        'operator' => '=',
        'value' => OperacionTramite::STATUS_PROCESSED,
        'boolean' => 'and',
    ])->toContain([
        'type' => 'NotNull',
        'column' => 'processed_at',
        'boolean' => 'and',
    ]);

    expect($query->orders)->toBe([
        ['column' => 'processed_at', 'direction' => 'asc'],
        ['column' => 'id', 'direction' => 'asc'],
    ]);
});

test('tramite resource history tab shows newest tramites first', function () {
    $query = TramiteResource::applyHistoryTabQuery(OperacionTramite::query())->getQuery();

    expect($query->orders)->toBe([
        ['column' => 'created_at', 'direction' => 'desc'],
        ['column' => 'id', 'direction' => 'desc'],
    ]);
});
