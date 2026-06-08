<?php

use App\Models\Operacion;

function operacionWithWorkflowCounts(string $status, array $counts = []): Operacion
{
    $operacion = new Operacion([
        'status' => $status,
    ]);

    foreach ($counts as $key => $value) {
        $operacion->setAttribute($key, $value);
    }

    return $operacion;
}

test('operacion workflow priority highlights pending decisions first', function () {
    $operacion = operacionWithWorkflowCounts(Operacion::STATUS_PENDING);

    expect($operacion->workflowPriorityLabel())->toBe('Pendiente decision')
        ->and($operacion->workflowPriorityColor())->toBe('warning')
        ->and($operacion->workflowFocus())->toBe('pendiente-confirmar');
});

test('operacion workflow priority highlights confirmed operations without tramites', function () {
    $operacion = operacionWithWorkflowCounts(Operacion::STATUS_CONFIRMED, [
        'tramites_count' => 0,
    ]);

    expect($operacion->workflowPriorityLabel())->toBe('Sin tramites')
        ->and($operacion->workflowPriorityColor())->toBe('danger')
        ->and($operacion->workflowFocus())->toBe('sin-tramites');
});

test('operacion workflow priority highlights tramite deadlines before generic pending work', function () {
    $operacion = operacionWithWorkflowCounts(Operacion::STATUS_CONFIRMED, [
        'tramites_count' => 3,
        'approved_tramites_count' => 1,
        'pending_to_process_tramites_count' => 2,
        'overdue_tramites_count' => 0,
        'due_soon_tramites_count' => 1,
    ]);

    expect($operacion->workflowPriorityLabel())->toBe('1 vencen en 7 dias')
        ->and($operacion->workflowPriorityColor())->toBe('warning')
        ->and($operacion->workflowFocus())->toBe('tramites-7-dias');
});

test('operacion workflow priority turns green when all tramites are approved', function () {
    $operacion = operacionWithWorkflowCounts(Operacion::STATUS_CONFIRMED, [
        'tramites_count' => 2,
        'approved_tramites_count' => 2,
        'pending_to_process_tramites_count' => 0,
        'overdue_tramites_count' => 0,
        'due_soon_tramites_count' => 0,
    ]);

    expect($operacion->workflowPriorityLabel())->toBe('Documentacion completa')
        ->and($operacion->workflowPriorityColor())->toBe('success')
        ->and($operacion->workflowFocus())->toBe('documentacion-completa');
});
