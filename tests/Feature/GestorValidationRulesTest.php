<?php

use App\Models\Cliente;
use App\Models\Dron;
use App\Models\OperadoraRequirement;
use App\Models\Piloto;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

function gestorValidationCliente(string $email = 'gestor-validation@example.com'): Cliente
{
    $user = User::factory()->create([
        'email' => $email,
        'role' => User::ROLE_CLIENTE,
    ]);

    return Cliente::create([
        'user_id' => $user->id,
        'name' => 'Cliente',
        'last_name' => 'Gestor',
        'email' => $email,
        'personal_email' => $email,
        'phone' => '600000000',
        'dni' => '00000000T',
        'address' => 'Calle Test 1',
        'country' => 'Espana',
        'city' => 'Barcelona',
        'province' => 'Barcelona',
        'postal_code' => '08001',
        'birth_date' => '1990-01-01',
    ]);
}

test('operadora correction review requires notes for the cliente', function () {
    $cliente = gestorValidationCliente();

    try {
        OperadoraRequirement::create([
            'cliente_id' => $cliente->id,
            'name' => 'Certificado operador',
            'input_type' => OperadoraRequirement::TYPE_PDF,
            'is_required' => true,
            'status' => OperadoraRequirement::STATUS_NEEDS_CHANGES,
        ]);

        $this->fail('Correction requests should not be saved without review notes.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('review_notes');
    }

    $requirement = OperadoraRequirement::create([
        'cliente_id' => $cliente->id,
        'name' => 'Certificado operador',
        'input_type' => OperadoraRequirement::TYPE_PDF,
        'is_required' => true,
        'status' => OperadoraRequirement::STATUS_NEEDS_CHANGES,
        'review_notes' => 'Sube un PDF legible y vigente.',
    ]);

    expect($requirement->review_notes)->toBe('Sube un PDF legible y vigente.');
});

test('operadora requirement colors reflect gestor attention priority', function () {
    expect((new OperadoraRequirement(['status' => OperadoraRequirement::STATUS_IN_REVIEW]))->gestorStatusColor())->toBe('danger')
        ->and((new OperadoraRequirement(['status' => OperadoraRequirement::STATUS_PENDING]))->gestorStatusColor())->toBe('warning')
        ->and((new OperadoraRequirement(['status' => OperadoraRequirement::STATUS_NEEDS_CHANGES]))->gestorStatusColor())->toBe('gray')
        ->and((new OperadoraRequirement(['status' => OperadoraRequirement::STATUS_APPROVED]))->gestorStatusColor())->toBe('success');
});

test('dron operational completeness follows required portal fields', function () {
    Carbon::setTestNow('2026-06-09 12:00:00');

    try {
        $dron = new Dron([
            'registration_not_applicable' => false,
            'remote_id_not_applicable' => false,
            'payload_not_applicable' => false,
            'vhf_not_applicable' => false,
            'emergency_not_applicable' => false,
            'insurance_valid_until' => '2026-06-08',
        ]);

        $missing = $dron->missingOperationalFields();

        expect($missing)->toContain('numero de serie')
            ->and($missing)->toContain('matricula')
            ->and($missing)->toContain('ID remoto')
            ->and($missing)->toContain('carga de pago')
            ->and($missing)->toContain('equipo VHF')
            ->and($missing)->toContain('equipo de emergencia')
            ->and($missing)->toContain('numero de poliza')
            ->and($missing)->toContain('aseguradora')
            ->and($missing)->toContain('seguro caducado')
            ->and($missing)->toContain('PDF de la poliza')
            ->and($missing)->toContain('estado AESA')
            ->and($dron->operationalStatusColor())->toBe('danger');

        $dron->forceFill([
            'drone_serial_number' => 'DRN-001',
            'registration_number' => 'UAS-001',
            'remote_id_number' => 'RID-001',
            'payload' => 'Camara',
            'vhf_equipment' => 'Equipo VHF',
            'emergency_equipment' => 'Paracaidas',
            'insurance_policy_number' => 'POL-001',
            'insurance_valid_until' => '2026-07-01',
            'insurance_company_name' => 'Aseguradora',
            'insurance_coverage_policy_path' => 'drones/poliza.pdf',
            'aesa_registration_status' => Dron::AESA_STATUS_YES,
        ]);

        expect($dron->isOperationallyComplete())->toBeTrue()
            ->and($dron->operationalStatusLabel())->toBe('Completo')
            ->and($dron->operationalStatusColor())->toBe('success');
    } finally {
        Carbon::setTestNow();
    }
});

test('piloto operational completeness requires mandatory documents', function () {
    $piloto = new Piloto([
        'dni_nie' => '12345678Z',
        'pilot_identification_number' => 'ESP-RP-001',
        'theoretical_certificate_level' => Piloto::THEORY_STS,
        'has_radiofonista_certificate' => true,
    ]);

    $missing = $piloto->missingOperationalFields();

    expect($missing)->toContain('DNI frontal')
        ->and($missing)->toContain('DNI trasero')
        ->and($missing)->toContain('certificado teorico')
        ->and($missing)->toContain('certificado practico')
        ->and($missing)->toContain('certificado radiofonista')
        ->and($piloto->operationalStatusColor())->toBe('warning');

    $piloto->forceFill([
        'dni_front_path' => 'pilotos/dni-frontal.pdf',
        'dni_back_path' => 'pilotos/dni-trasero.pdf',
        'theoretical_certificate_path' => 'pilotos/teorico.pdf',
        'practical_certificate_path' => 'pilotos/practico.pdf',
        'radiofonista_certificate_path' => 'pilotos/radiofonista.pdf',
    ]);

    expect($piloto->isOperationallyComplete())->toBeTrue()
        ->and($piloto->operationalStatusLabel())->toBe('Completo');

    $a2Piloto = new Piloto([
        'dni_nie' => '12345678A',
        'pilot_identification_number' => 'ESP-RP-002',
        'theoretical_certificate_level' => Piloto::THEORY_A2,
        'has_radiofonista_certificate' => false,
        'dni_front_path' => 'pilotos/dni-frontal.pdf',
        'dni_back_path' => 'pilotos/dni-trasero.pdf',
        'theoretical_certificate_path' => 'pilotos/teorico.pdf',
    ]);

    expect($a2Piloto->missingOperationalFields())->toBe([])
        ->and($a2Piloto->isOperationallyComplete())->toBeTrue();
});
