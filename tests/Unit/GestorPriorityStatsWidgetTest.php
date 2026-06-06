<?php

use App\Filament\Widgets\GestorPriorityStatsWidget;

function gestorConfirmedOperationsText(
    int $withoutTramites,
    int $withPendingTramites,
    string $fallback = 'Operaciones desde hoy hasta 7 dias vista'
): string {
    $method = new ReflectionMethod(GestorPriorityStatsWidget::class, 'confirmedOperationsDescription');
    $method->setAccessible(true);

    return $method->invoke(
        new GestorPriorityStatsWidget(),
        $withoutTramites,
        $withPendingTramites,
        $fallback
    );
}

function gestorConfirmedOperationsTone(int $withoutTramites, int $withPendingTramites): string
{
    $method = new ReflectionMethod(GestorPriorityStatsWidget::class, 'confirmedOperationsTone');
    $method->setAccessible(true);

    return $method->invoke(new GestorPriorityStatsWidget(), $withoutTramites, $withPendingTramites);
}

test('confirmed operations card warns when tramites are not fully approved', function () {
    expect(gestorConfirmedOperationsText(0, 1))->toBe('1 con tramites pendientes de aprobar')
        ->and(gestorConfirmedOperationsTone(0, 1))->toBe('warning');
});

test('confirmed operations card is green only when there are no tramite issues', function () {
    expect(gestorConfirmedOperationsText(0, 0))->toBe('Operaciones desde hoy hasta 7 dias vista')
        ->and(gestorConfirmedOperationsTone(0, 0))->toBe('success');
});

test('confirmed operations card prioritizes missing tramites', function () {
    expect(gestorConfirmedOperationsText(1, 2))->toBe('1 sin tramites - 2 con tramites pendientes de aprobar')
        ->and(gestorConfirmedOperationsTone(1, 2))->toBe('danger');
});
