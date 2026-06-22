<?php

use App\Filament\Resources\Clientes\RelationManagers\OperacionTramitesRelationManager as ClienteOperacionTramitesRelationManager;
use App\Filament\Resources\Clientes\RelationManagers\OperacionesRelationManager as ClienteOperacionesRelationManager;
use App\Models\Cliente;
use App\Models\Dron;
use App\Models\Operacion;
use App\Models\OperacionTramite;
use App\Models\Piloto;
use Illuminate\Support\Carbon;

function clienteCurrentOperationsRecord(string $email): Cliente
{
    return Cliente::create([
        'name' => 'Cliente',
        'last_name' => 'Vigente',
        'email' => $email,
        'personal_email' => $email,
        'phone' => '600000000',
        'dni' => str_pad((string) (abs(crc32($email)) % 100000000), 8, '0', STR_PAD_LEFT).'T',
        'address' => 'Calle Vigente 1',
        'country' => 'Espana',
        'city' => 'Barcelona',
        'province' => 'Barcelona',
        'postal_code' => '08001',
        'birth_date' => '1990-01-01',
    ]);
}

function clienteCurrentOperationsPiloto(Cliente $cliente, string $suffix): Piloto
{
    return Piloto::create([
        'cliente_id' => $cliente->id,
        'first_name' => 'Piloto',
        'last_name' => 'Vigente',
        'dni_nie' => "PILOTO-VIGENTE-{$suffix}",
        'birth_date' => '1990-01-01',
        'pilot_identification_number' => "ESP-RP-VIG-{$suffix}",
        'address' => 'Calle Piloto 1',
        'country' => 'Espana',
        'city' => 'Barcelona',
        'province' => 'Barcelona',
        'postal_code' => '08001',
        'phone' => '600000001',
        'theoretical_certificate_level' => Piloto::THEORY_A2,
    ]);
}

function clienteCurrentOperationsDron(Cliente $cliente, string $suffix): Dron
{
    return Dron::create([
        'cliente_id' => $cliente->id,
        'uas_class' => Dron::UAS_CLASS_ROTOR,
        'manufacturer_name' => 'DJI',
        'model' => "Mavic {$suffix}",
        'drone_serial_number' => "DRN-VIG-{$suffix}",
        'controller_serial_number' => "CTRL-VIG-{$suffix}",
        'registration_number' => "UAS-VIG-{$suffix}",
        'mtom_weight' => 900,
        'remote_id_number' => "RID-VIG-{$suffix}",
        'class_marking' => 'C1',
        'band_frequency' => '2.4 GHz',
        'color' => 'Negro',
        'payload' => 'Camara',
        'vhf_equipment' => 'Equipo VHF',
        'emergency_equipment' => 'Paracaidas',
        'insurance_policy_number' => "POL-VIG-{$suffix}",
        'insurance_valid_until' => '2027-01-01',
        'insurance_company_name' => 'Aseguradora',
        'aesa_registration_status' => Dron::AESA_STATUS_YES,
    ]);
}

function clienteCurrentOperationsOperacion(
    Cliente $cliente,
    string $date,
    string $suffix,
    string $status = Operacion::STATUS_CONFIRMED
): Operacion
{
    $piloto = clienteCurrentOperationsPiloto($cliente, $suffix);
    $dron = clienteCurrentOperationsDron($cliente, $suffix);

    return Operacion::create([
        'cliente_id' => $cliente->id,
        'piloto_id' => $piloto->id,
        'dron_id' => $dron->id,
        'reference' => "Operacion {$suffix}",
        'status' => $status,
        'operation_date' => $date,
        'location' => 'Barcelona',
    ]);
}

afterEach(function (): void {
    Carbon::setTestNow();
});

test('cliente tramites table only includes tramites from active operations', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-07', config('app.timezone')));

    $cliente = clienteCurrentOperationsRecord('cliente-tramites-vigentes@example.com');
    $inactiveOperation = clienteCurrentOperationsOperacion($cliente, '2026-06-04', 'INACTIVE');
    $activeBoundaryOperation = clienteCurrentOperationsOperacion($cliente, '2026-06-05', 'BOUNDARY');
    $todayOperation = clienteCurrentOperationsOperacion($cliente, '2026-06-07', 'TODAY');
    $futureOperation = clienteCurrentOperationsOperacion($cliente, '2026-06-08', 'FUTURE');
    $rejectedOperation = clienteCurrentOperationsOperacion($cliente, '2026-06-08', 'REJECTED', Operacion::STATUS_REJECTED);

    OperacionTramite::create([
        'operacion_id' => $inactiveOperation->id,
        'title' => 'Tramite operacion inactiva',
        'deadline_date' => '2026-06-04',
        'status' => OperacionTramite::STATUS_PENDING,
    ]);

    OperacionTramite::create([
        'operacion_id' => $activeBoundaryOperation->id,
        'title' => 'Tramite operacion limite activa',
        'deadline_date' => '2026-06-05',
        'status' => OperacionTramite::STATUS_PENDING,
    ]);

    OperacionTramite::create([
        'operacion_id' => $todayOperation->id,
        'title' => 'Tramite operacion hoy',
        'deadline_date' => '2026-06-07',
        'status' => OperacionTramite::STATUS_PENDING,
    ]);

    OperacionTramite::create([
        'operacion_id' => $futureOperation->id,
        'title' => 'Tramite operacion futura',
        'processed_at' => '2026-06-06',
        'status' => OperacionTramite::STATUS_PROCESSED,
    ]);

    OperacionTramite::create([
        'operacion_id' => $rejectedOperation->id,
        'title' => 'Tramite operacion rechazada',
        'deadline_date' => '2026-06-08',
        'status' => OperacionTramite::STATUS_PENDING,
    ]);

    $titles = ClienteOperacionTramitesRelationManager::applyCurrentWorkflowQuery(
        $cliente->operacionTramites()->getQuery()
    )->pluck('operacion_tramites.title')->all();

    expect($titles)->toContain('Tramite operacion limite activa')
        ->and($titles)->toContain('Tramite operacion hoy')
        ->and($titles)->toContain('Tramite operacion futura')
        ->and($titles)->not->toContain('Tramite operacion inactiva')
        ->and($titles)->not->toContain('Tramite operacion rechazada');
});

test('cliente operations table uses active operations scope', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-07', config('app.timezone')));

    $cliente = clienteCurrentOperationsRecord('cliente-operaciones-vigentes@example.com');
    clienteCurrentOperationsOperacion($cliente, '2026-06-04', 'INACTIVE');
    clienteCurrentOperationsOperacion($cliente, '2026-06-05', 'BOUNDARY');
    clienteCurrentOperationsOperacion($cliente, '2026-06-07', 'TODAY');
    clienteCurrentOperationsOperacion($cliente, '2026-06-08', 'FUTURE');
    clienteCurrentOperationsOperacion($cliente, '2026-06-08', 'REJECTED', Operacion::STATUS_REJECTED);

    $references = ClienteOperacionesRelationManager::applyCurrentOperationsQuery(
        $cliente->operaciones()->getQuery()
    )->pluck('reference')->all();

    expect($references)->toBe([
        'Operacion BOUNDARY',
        'Operacion TODAY',
        'Operacion FUTURE',
    ]);
});
