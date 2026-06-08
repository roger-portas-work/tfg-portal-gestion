<?php

use App\Models\Cliente;
use App\Models\Dron;
use App\Models\Operacion;
use App\Models\Piloto;
use App\Models\User;
use Illuminate\Validation\ValidationException;

function safetyCliente(string $email): Cliente
{
    $user = User::factory()->create([
        'email' => $email,
        'role' => User::ROLE_CLIENTE,
    ]);

    return Cliente::create([
        'user_id' => $user->id,
        'name' => 'Cliente',
        'last_name' => 'Prueba',
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

function safetyPiloto(Cliente $cliente): Piloto
{
    return Piloto::create([
        'cliente_id' => $cliente->id,
        'first_name' => 'Piloto',
        'last_name' => 'Prueba',
        'dni_nie' => '11111111H',
        'birth_date' => '1990-01-01',
        'pilot_identification_number' => 'ESP-RP-001',
        'maximum_pilot_certification' => 'STS',
        'address' => 'Calle Piloto 1',
        'country' => 'Espana',
        'city' => 'Barcelona',
        'province' => 'Barcelona',
        'postal_code' => '08001',
        'phone' => '600000001',
        'theoretical_certificate_level' => Piloto::THEORY_STS,
    ]);
}

function safetyDron(Cliente $cliente): Dron
{
    return Dron::create([
        'cliente_id' => $cliente->id,
        'uas_class' => Dron::UAS_CLASS_ROTOR,
        'manufacturer_name' => 'DJI',
        'model' => 'Mavic',
        'controller_serial_number' => 'CTRL-001',
        'registration_number' => 'UAS-001',
        'mtom_weight' => 900,
        'remote_id_number' => 'RID-001',
        'class_marking' => 'C1',
        'band_frequency' => '2.4 GHz',
        'color' => 'Negro',
        'insurance_policy_number' => 'POL-001',
        'insurance_valid_until' => '2027-01-01',
        'insurance_company_name' => 'Aseguradora',
        'aesa_registration_status' => Dron::AESA_STATUS_YES,
    ]);
}

test('cliente with operational data cannot be deleted from the model', function () {
    $cliente = safetyCliente('cliente-operativo@example.com');
    $piloto = safetyPiloto($cliente);
    $dron = safetyDron($cliente);

    Operacion::create([
        'cliente_id' => $cliente->id,
        'piloto_id' => $piloto->id,
        'dron_id' => $dron->id,
        'reference' => 'Operacion protegida',
        'operation_date' => '2026-06-20',
        'location' => 'Barcelona',
    ]);

    expect($cliente->canBeDeletedSafely())->toBeFalse();

    try {
        $cliente->delete();

        $this->fail('The cliente should not be deleted while it has operational data.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('cliente');
    }

    expect($cliente->fresh())->not->toBeNull()
        ->and($cliente->user()->exists())->toBeTrue();
});

test('operacion requires piloto and dron to belong to its cliente', function () {
    $cliente = safetyCliente('cliente-operacion@example.com');
    $otherCliente = safetyCliente('otro-cliente-operacion@example.com');
    $foreignPiloto = safetyPiloto($otherCliente);
    $foreignDron = safetyDron($otherCliente);

    try {
        Operacion::create([
            'cliente_id' => $cliente->id,
            'piloto_id' => $foreignPiloto->id,
            'dron_id' => $foreignDron->id,
            'reference' => 'Operacion cruzada',
            'operation_date' => '2026-06-20',
            'location' => 'Barcelona',
        ]);

        $this->fail('The operation should not accept assignments from another cliente.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKeys(['piloto_id', 'dron_id']);
    }
});
