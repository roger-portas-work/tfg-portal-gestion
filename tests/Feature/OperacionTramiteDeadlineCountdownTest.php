<?php

use App\Models\OperacionTramite;
use Illuminate\Support\Carbon;

afterEach(function (): void {
    Carbon::setTestNow();
});

test('operacion tramite deadline countdown shows labels and urgency colors', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-07', config('app.timezone')));

    $tramite = fn (?string $deadline): OperacionTramite => new OperacionTramite([
        'deadline_date' => $deadline,
    ]);

    expect($tramite(null)->deadlineCountdownLabel())->toBe('Sin fecha limite')
        ->and($tramite(null)->deadlineCountdownColor())->toBe('gray')
        ->and($tramite('2026-06-06')->deadlineCountdownLabel())->toBe('Vencido hace 1 dia')
        ->and($tramite('2026-06-06')->deadlineCountdownColor())->toBe('danger')
        ->and($tramite('2026-06-07')->deadlineCountdownLabel())->toBe('Vence hoy')
        ->and($tramite('2026-06-07')->deadlineCountdownColor())->toBe('danger')
        ->and($tramite('2026-06-08')->deadlineCountdownLabel())->toBe('Falta 1 dia')
        ->and($tramite('2026-06-14')->deadlineCountdownColor())->toBe('danger')
        ->and($tramite('2026-06-22')->deadlineCountdownColor())->toBe('warning')
        ->and($tramite('2026-07-06')->deadlineCountdownColor())->toBe('warning')
        ->and($tramite('2026-07-07')->deadlineCountdownColor())->toBe('success');
});
