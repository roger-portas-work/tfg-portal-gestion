<?php

use App\Filament\Resources\Clientes\Tables\ClientesTable;
use App\Models\Cliente;
use App\Models\Dron;
use App\Models\Operacion;
use App\Models\OperadoraRequirement;
use App\Models\Piloto;
use Illuminate\Support\Carbon;

function clienteUsefulFilterRecord(string $email, bool $profileCompleted = false): Cliente
{
    return Cliente::create([
        'name' => 'Cliente',
        'last_name' => 'Filtro',
        'email' => $email,
        'personal_email' => $email,
        'phone' => '600000000',
        'dni' => str_pad((string) (abs(crc32($email)) % 100000000), 8, '0', STR_PAD_LEFT).'T',
        'address' => 'Calle Filtro 1',
        'country' => 'Espana',
        'city' => 'Barcelona',
        'province' => 'Barcelona',
        'postal_code' => '08001',
        'birth_date' => '1990-01-01',
        'profile_completed' => $profileCompleted,
    ]);
}

function clienteUsefulFilterPiloto(Cliente $cliente, string $suffix): Piloto
{
    return Piloto::create([
        'cliente_id' => $cliente->id,
        'first_name' => 'Piloto',
        'last_name' => 'Filtro',
        'dni_nie' => "PILOTO{$suffix}",
        'birth_date' => '1990-01-01',
        'pilot_identification_number' => "ESP-RP-{$suffix}",
        'address' => 'Calle Piloto 1',
        'country' => 'Espana',
        'city' => 'Barcelona',
        'province' => 'Barcelona',
        'postal_code' => '08001',
        'phone' => '600000001',
        'theoretical_certificate_level' => Piloto::THEORY_A2,
    ]);
}

function clienteUsefulFilterDron(Cliente $cliente, string $suffix): Dron
{
    return Dron::create([
        'cliente_id' => $cliente->id,
        'uas_class' => Dron::UAS_CLASS_ROTOR,
        'manufacturer_name' => 'DJI',
        'model' => "Mavic {$suffix}",
        'drone_serial_number' => "DRN-{$suffix}",
        'controller_serial_number' => "CTRL-{$suffix}",
        'registration_number' => "UAS-{$suffix}",
        'mtom_weight' => 900,
        'remote_id_number' => "RID-{$suffix}",
        'class_marking' => 'C1',
        'band_frequency' => '2.4 GHz',
        'color' => 'Negro',
        'payload' => 'Camara',
        'vhf_equipment' => 'Equipo VHF',
        'emergency_equipment' => 'Paracaidas',
        'insurance_policy_number' => "POL-{$suffix}",
        'insurance_valid_until' => '2027-01-01',
        'insurance_company_name' => 'Aseguradora',
        'insurance_coverage_policy_path' => "drones/{$suffix}/poliza.pdf",
        'aesa_registration_status' => Dron::AESA_STATUS_YES,
    ]);
}

function clienteUsefulFilterOperation(Cliente $cliente, string $date, ?string $status, string $suffix): Operacion
{
    $piloto = clienteUsefulFilterPiloto($cliente, $suffix);
    $dron = clienteUsefulFilterDron($cliente, $suffix);

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

test('cliente profile incomplete filter returns only incomplete profiles', function () {
    $incomplete = clienteUsefulFilterRecord('cliente-ficha-incompleta@example.com');
    $complete = clienteUsefulFilterRecord('cliente-ficha-completa@example.com', true);

    $ids = ClientesTable::applyProfileIncompleteFilter(Cliente::query())->pluck('id')->all();

    expect($ids)->toContain($incomplete->id)
        ->and($ids)->not->toContain($complete->id);
});

test('cliente operadora pending filter returns clientes with non closed requirements', function () {
    $pending = clienteUsefulFilterRecord('cliente-operadora-pending@example.com');
    $inReview = clienteUsefulFilterRecord('cliente-operadora-review@example.com');
    $needsChanges = clienteUsefulFilterRecord('cliente-operadora-changes@example.com');
    $approved = clienteUsefulFilterRecord('cliente-operadora-approved@example.com');

    OperadoraRequirement::create([
        'cliente_id' => $pending->id,
        'name' => 'Certificado operador',
        'input_type' => OperadoraRequirement::TYPE_PDF,
        'is_required' => true,
        'status' => OperadoraRequirement::STATUS_PENDING,
    ]);
    OperadoraRequirement::create([
        'cliente_id' => $inReview->id,
        'name' => 'Manual operaciones',
        'input_type' => OperadoraRequirement::TYPE_PDF,
        'is_required' => true,
        'status' => OperadoraRequirement::STATUS_IN_REVIEW,
    ]);
    OperadoraRequirement::create([
        'cliente_id' => $needsChanges->id,
        'name' => 'Seguro operador',
        'input_type' => OperadoraRequirement::TYPE_PDF,
        'is_required' => true,
        'status' => OperadoraRequirement::STATUS_NEEDS_CHANGES,
        'review_notes' => 'Falta la pagina de vigencia.',
    ]);
    OperadoraRequirement::create([
        'cliente_id' => $approved->id,
        'name' => 'Declaracion operador',
        'input_type' => OperadoraRequirement::TYPE_PDF,
        'is_required' => true,
        'status' => OperadoraRequirement::STATUS_APPROVED,
    ]);

    $ids = ClientesTable::applyOperadoraPendingFilter(Cliente::query())->pluck('id')->all();

    expect($ids)->toContain($pending->id)
        ->and($ids)->toContain($inReview->id)
        ->and($ids)->toContain($needsChanges->id)
        ->and($ids)->not->toContain($approved->id);
});

test('cliente active operations filter uses confirmed operations from today onwards', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-10', config('app.timezone')));

    $active = clienteUsefulFilterRecord('cliente-operacion-activa@example.com');
    $pending = clienteUsefulFilterRecord('cliente-operacion-pendiente@example.com');
    $rejected = clienteUsefulFilterRecord('cliente-operacion-rechazada@example.com');
    $old = clienteUsefulFilterRecord('cliente-operacion-antigua@example.com');

    clienteUsefulFilterOperation($active, '2026-06-11', Operacion::STATUS_CONFIRMED, 'ACTIVE');
    clienteUsefulFilterOperation($pending, '2026-06-11', Operacion::STATUS_PENDING, 'PENDING');
    clienteUsefulFilterOperation($rejected, '2026-06-11', Operacion::STATUS_REJECTED, 'REJECTED');
    clienteUsefulFilterOperation($old, '2026-06-07', Operacion::STATUS_CONFIRMED, 'OLD');

    $ids = ClientesTable::applyActiveOperationsFilter(Cliente::query())->pluck('id')->all();

    expect($ids)->toContain($active->id)
        ->and($ids)->not->toContain($pending->id)
        ->and($ids)->not->toContain($rejected->id)
        ->and($ids)->not->toContain($old->id);
});

test('cliente operation summary distinguishes past or confirmed operations from active operations', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-10', config('app.timezone')));

    $cliente = clienteUsefulFilterRecord('cliente-resumen-operaciones@example.com');

    clienteUsefulFilterOperation($cliente, '2026-06-09', Operacion::STATUS_PENDING, 'PAST-PENDING');
    clienteUsefulFilterOperation($cliente, '2026-06-09', Operacion::STATUS_REJECTED, 'PAST-REJECTED');
    clienteUsefulFilterOperation($cliente, '2026-06-10', Operacion::STATUS_PENDING, 'TODAY-PENDING');
    clienteUsefulFilterOperation($cliente, '2026-06-11', Operacion::STATUS_PENDING, 'FUTURE-PENDING');
    clienteUsefulFilterOperation($cliente, '2026-06-10', Operacion::STATUS_CONFIRMED, 'TODAY-CONFIRMED');
    clienteUsefulFilterOperation($cliente, '2026-06-11', Operacion::STATUS_CONFIRMED, 'FUTURE-CONFIRMED');

    $total = $cliente->operaciones()->countableForClienteSummary()->count();
    $active = $cliente->operaciones()->activeForClienteSummary()->count();

    expect($total)->toBe(3)
        ->and($active)->toBe(2);
});
