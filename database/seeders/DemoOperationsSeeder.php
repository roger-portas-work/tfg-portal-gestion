<?php

namespace Database\Seeders;

use App\Models\Cliente;
use App\Models\Dron;
use App\Models\Operacion;
use App\Models\OperacionTramite;
use App\Models\OperadoraProfile;
use App\Models\OperadoraRequirement;
use App\Models\Piloto;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DemoOperationsSeeder extends Seeder
{
    public function run(): void
    {
        $clienteUser = User::updateOrCreate(
            ['email' => 'operadora.demo@idronlex.com'],
            [
                'name' => 'Carlos Romero Salas',
                'password' => '12345678',
                'role' => User::ROLE_CLIENTE,
                'email_verified_at' => now(),
            ],
        );

        $cliente = Cliente::updateOrCreate(
            ['dni' => '87654321X'],
            [
                'user_id' => $clienteUser->id,
                'name' => 'Carlos',
                'last_name' => 'Romero',
                'second_last_name' => 'Salas',
                'email' => $clienteUser->email,
                'personal_email' => 'carlos.romero@iberodron.es',
                'phone' => '611223344',
                'profile_completed' => true,
                'address' => 'Calle Serrano 118',
                'country' => 'Espana',
                'city' => 'Madrid',
                'province' => 'Madrid',
                'postal_code' => '28006',
                'operator_registration_number' => 'ESP-OPER-000245',
                'birth_date' => '1987-03-22',
                'pilot_identification_number' => 'ESP-RP-000000000099',
                'pilot_certificate' => $this->storePdf('clientes/iberodron/certificado-piloto.pdf', 'Certificado piloto Carlos Romero'),
                'operator_certification' => $this->storePdf('clientes/iberodron/certificado-operador.pdf', 'Certificado operador Iberodron'),
            ],
        );

        $this->seedOperadora($cliente);

        foreach ($this->records(10) as $record) {
            $piloto = $this->seedPiloto($cliente, $record['piloto']);
            $dron = $this->seedDron($cliente, $record['dron']);
            $operacion = $this->seedOperacion($cliente, $piloto, $dron, $record['operacion']);

            $this->clearTramitesForPendingOperacion($operacion);
        }

        $yearlyClienteUser = User::updateOrCreate(
            ['email' => 'cliente.demo@idronlex.com'],
            [
                'name' => 'Laura Garcia Vidal',
                'password' => '12345678',
                'role' => User::ROLE_CLIENTE,
                'email_verified_at' => now(),
            ],
        );

        $yearlyCliente = Cliente::updateOrCreate(
            ['dni' => '12345678Z'],
            [
                'user_id' => $yearlyClienteUser->id,
                'name' => 'Laura',
                'last_name' => 'Garcia',
                'second_last_name' => 'Vidal',
                'email' => $yearlyClienteUser->email,
                'personal_email' => 'laura.garcia@aeromedia.es',
                'phone' => '600112233',
                'profile_completed' => true,
                'address' => 'Calle Marina 21',
                'country' => 'Espana',
                'city' => 'Barcelona',
                'province' => 'Barcelona',
                'postal_code' => '08005',
                'operator_registration_number' => 'ESP-OPER-000112',
                'birth_date' => '1990-05-12',
                'pilot_identification_number' => 'ESP-RP-000000000001',
                'pilot_certificate' => $this->storePdf('clientes/cliente-demo/certificado-piloto.pdf', 'Certificado piloto cliente demo'),
                'operator_certification' => $this->storePdf('clientes/cliente-demo/certificado-operador.pdf', 'Certificado operador cliente demo'),
            ],
        );

        $this->seedOperadora($yearlyCliente);

        foreach ($this->records(365) as $record) {
            $piloto = $this->seedPiloto($yearlyCliente, $record['piloto']);
            $dron = $this->seedDron($yearlyCliente, $record['dron']);
            $operacion = $this->seedOperacion($yearlyCliente, $piloto, $dron, $record['operacion']);

            $this->clearTramitesForPendingOperacion($operacion);
        }

        $priorityClienteUser = User::updateOrCreate(
            ['email' => 'ivan.suarez@idronlex.com'],
            [
                'name' => 'Ivan Suarez Sanchez',
                'password' => '12345678',
                'role' => User::ROLE_CLIENTE,
                'email_verified_at' => now(),
            ],
        );

        $priorityCliente = Cliente::updateOrCreate(
            ['dni' => '46813579Q'],
            [
                'user_id' => $priorityClienteUser->id,
                'name' => 'Ivan',
                'last_name' => 'Suarez',
                'second_last_name' => 'Sanchez',
                'email' => $priorityClienteUser->email,
                'personal_email' => 'ivan.suarez@skyworks.es',
                'phone' => '699887766',
                'profile_completed' => true,
                'address' => 'Calle Gran Via 42',
                'country' => 'Espana',
                'city' => 'Madrid',
                'province' => 'Madrid',
                'postal_code' => '28013',
                'operator_registration_number' => 'ESP-OPER-000389',
                'birth_date' => '1989-12-04',
                'pilot_identification_number' => 'ESP-RP-000000000120',
                'pilot_certificate' => $this->storePdf('clientes/ivan-suarez/certificado-piloto.pdf', 'Certificado piloto Ivan Suarez'),
                'operator_certification' => $this->storePdf('clientes/ivan-suarez/certificado-operador.pdf', 'Certificado operador Ivan Suarez'),
            ],
        );

        $this->seedOperadora($priorityCliente);

        foreach ($this->recordsFromOffsets([0, 1, 3, 5, 7]) as $index => $record) {
            $piloto = $this->seedPiloto($priorityCliente, $record['piloto']);
            $dron = $this->seedDron($priorityCliente, $record['dron']);
            $operacion = $this->seedOperacion($priorityCliente, $piloto, $dron, $record['operacion']);

            $this->seedPriorityTramites($operacion, $record['operacion']['permisos'], $index);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function seedPiloto(Cliente $cliente, array $data): Piloto
    {
        $folder = 'pilotos/cliente-'.$cliente->id.'/'.Str::slug($data['first_name'].'-'.$data['last_name']);
        $theory = $data['theoretical_certificate_level'];

        return Piloto::updateOrCreate(
            [
                'cliente_id' => $cliente->id,
                'dni_nie' => $data['dni_nie'],
            ],
            [
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'second_last_name' => $data['second_last_name'],
                'birth_date' => $data['birth_date'],
                'pilot_identification_number' => $data['pilot_identification_number'],
                'maximum_pilot_certification' => $data['maximum_pilot_certification'],
                'address' => $data['address'],
                'country' => $data['country'],
                'city' => $data['city'],
                'province' => $data['province'],
                'postal_code' => $data['postal_code'],
                'phone' => $data['phone'],
                'has_radiofonista_certificate' => $data['has_radiofonista_certificate'],
                'radiofonista_certificate_path' => $data['has_radiofonista_certificate']
                    ? $this->storePdf($folder.'/certificado-radiofonista.pdf', 'Certificado radiofonista '.$data['first_name'])
                    : null,
                'theoretical_certificate_level' => $theory,
                'dni_front_path' => $this->storePdf($folder.'/dni-frontal.pdf', 'DNI frontal '.$data['first_name']),
                'dni_back_path' => $this->storePdf($folder.'/dni-trasero.pdf', 'DNI trasero '.$data['first_name']),
                'theoretical_certificate_path' => $this->storePdf($folder.'/certificado-teorico.pdf', 'Certificado teorico '.$data['first_name']),
                'practical_certificate_path' => $theory === Piloto::THEORY_STS
                    ? $this->storePdf($folder.'/certificado-practico.pdf', 'Certificado practico '.$data['first_name'])
                    : null,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function seedDron(Cliente $cliente, array $data): Dron
    {
        $insurancePath = 'drones/cliente-'.$cliente->id.'/seguros/'.$data['insurance_file'];

        return Dron::updateOrCreate(
            [
                'cliente_id' => $cliente->id,
                'drone_serial_number' => $data['drone_serial_number'],
            ],
            [
                'uas_class' => Dron::UAS_CLASS_ROTOR,
                'manufacturer_name' => $data['manufacturer_name'],
                'model' => $data['model'],
                'controller_serial_number' => $data['controller_serial_number'],
                'registration_number' => $data['registration_not_applicable'] ? null : $data['registration_number'],
                'registration_not_applicable' => $data['registration_not_applicable'],
                'mtom_weight' => $data['mtom_weight'],
                'remote_id_number' => $data['remote_id_not_applicable'] ? null : $data['remote_id_number'],
                'remote_id_not_applicable' => $data['remote_id_not_applicable'],
                'class_marking' => $data['class_marking'],
                'band_frequency' => $data['band_frequency'],
                'color' => $data['color'],
                'payload' => $data['payload_not_applicable'] ? null : $data['payload'],
                'payload_not_applicable' => $data['payload_not_applicable'],
                'vhf_equipment' => $data['vhf_not_applicable'] ? null : $data['vhf_equipment'],
                'vhf_not_applicable' => $data['vhf_not_applicable'],
                'emergency_equipment' => $data['emergency_not_applicable'] ? null : $data['emergency_equipment'],
                'emergency_not_applicable' => $data['emergency_not_applicable'],
                'insurance_policy_number' => $data['insurance_policy_number'],
                'insurance_valid_until' => $data['insurance_valid_until'],
                'insurance_company_name' => $data['insurance_company_name'],
                'insurance_coverage_policy_path' => $this->storePdf($insurancePath, 'Poliza '.$data['insurance_policy_number']),
                'insurance_coverage_policy_original_name' => $data['insurance_file'],
                'aesa_registration_status' => $data['aesa_registration_status'],
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function seedOperacion(Cliente $cliente, Piloto $piloto, Dron $dron, array $data): Operacion
    {
        return Operacion::updateOrCreate(
            [
                'cliente_id' => $cliente->id,
                'reference' => $data['reference'],
            ],
            [
                'piloto_id' => $piloto->id,
                'dron_id' => $dron->id,
                'status' => $data['status'] ?? Operacion::STATUS_PENDING,
                'operation_date' => $data['operation_date'],
                'estimated_filming_schedule' => $data['estimated_filming_schedule'],
                'address' => $data['address'],
                'country' => $data['country'],
                'city' => $data['city'],
                'province' => $data['province'],
                'postal_code' => $data['postal_code'],
                'google_maps_link' => $data['google_maps_link'],
                'altitude' => $data['altitude'],
                'operation_radius' => $data['operation_radius'],
                'operation_cost' => $data['operation_cost'],
                'operational_conditions' => $data['operational_conditions'],
                'extra_information' => $data['extra_information'],
                'video_objective' => $data['video_objective'],
                'end_client' => $data['end_client'],
                'production_company_name' => $data['production_company_name'],
                'production_contact_phone' => $data['production_contact_phone'],
                'environment_type' => $data['environment_type'],
                'people_present' => $data['people_present'],
                'prior_permits_notes' => $data['permisos'],
                'location' => $data['location'],
                'description' => $data['description'],
            ],
        );
    }

    protected function seedTramite(Operacion $operacion, string $permisos): void
    {
        $path = 'operaciones/'.Str::slug($operacion->reference).'/permiso-operacion.pdf';

        OperacionTramite::updateOrCreate(
            [
                'operacion_id' => $operacion->id,
                'title' => 'Briefing operacion',
            ],
            [
                'attachments' => [$this->storePdf($path, 'Permisos '.$operacion->reference)],
                'attachment_file_names' => ['permiso-operacion.pdf'],
                'deadline_date' => Carbon::parse($operacion->operation_date)->subDays(10),
                'processed_at' => null,
                'status' => OperacionTramite::STATUS_PENDING,
                'request_code' => 'REQ-'.$operacion->id,
                'extra_information' => $permisos,
            ],
        );
    }

    protected function clearTramitesForPendingOperacion(Operacion $operacion): void
    {
        if ($operacion->isPending()) {
            $operacion->tramites()->delete();
        }
    }

    protected function seedPriorityTramites(Operacion $operacion, string $permisos, int $index): void
    {
        $operationDate = Carbon::parse($operacion->operation_date)->startOfDay();
        $tramites = match ($index) {
            0 => [
                $this->tramiteData('Briefing operacion', Carbon::today(), null, OperacionTramite::STATUS_PENDING, 'BRF-'.$operacion->id, 'Briefing final pendiente para la operacion de hoy.'),
                $this->tramiteData('Coordinacion Ministerio del Interior', Carbon::today()->subDay(), null, OperacionTramite::STATUS_PENDING, 'INT-'.$operacion->id, $permisos),
                $this->tramiteData('FPL', $operationDate->copy()->subDay(), Carbon::today(), OperacionTramite::STATUS_PROCESSED, 'FPL-'.$operacion->id, 'Plan de vuelo tramitado, pendiente de aprobacion final.'),
            ],
            1 => [
                $this->tramiteData('Briefing operacion', Carbon::today(), null, OperacionTramite::STATUS_PENDING, 'BRF-'.$operacion->id, 'Briefing pendiente de validar con el piloto asignado.'),
                $this->tramiteData('Coordinacion aeropuerto', Carbon::today()->addDay(), null, OperacionTramite::STATUS_PENDING, 'AIR-'.$operacion->id, $permisos),
                $this->tramiteData('Permiso municipal', $operationDate->copy()->subDay(), Carbon::today()->subDay(), OperacionTramite::STATUS_APPROVED, 'MUN-'.$operacion->id, 'Permiso municipal aprobado.'),
            ],
            2 => [
                $this->tramiteData('Briefing operacion', Carbon::today()->addDay(), null, OperacionTramite::STATUS_PENDING, 'BRF-'.$operacion->id, 'Briefing operativo pendiente.'),
                $this->tramiteData('Permiso ZEPA', Carbon::today()->addDays(2), null, OperacionTramite::STATUS_PENDING, 'ZEP-'.$operacion->id, $permisos),
                $this->tramiteData('FPL', $operationDate->copy(), null, OperacionTramite::STATUS_PENDING, 'FPL-'.$operacion->id, 'Plan de vuelo pendiente de presentar.'),
            ],
            3 => [
                $this->tramiteData('Briefing operacion', Carbon::today()->addDays(2), Carbon::today(), OperacionTramite::STATUS_PROCESSED, 'BRF-'.$operacion->id, 'Briefing enviado, pendiente de aprobacion documental.'),
                $this->tramiteData('Coordinacion helipuerto', Carbon::today()->addDays(4), null, OperacionTramite::STATUS_PENDING, 'HEL-'.$operacion->id, $permisos),
                $this->tramiteData('Comunicacion Ministerio del Interior', $operationDate->copy(), null, OperacionTramite::STATUS_PENDING, 'INT-'.$operacion->id, 'Comunicacion previa pendiente de registrar.'),
            ],
            default => [
                $this->tramiteData('Briefing operacion', Carbon::today()->addDays(3), Carbon::today()->subDay(), OperacionTramite::STATUS_APPROVED, 'BRF-'.$operacion->id, 'Briefing aprobado.'),
                $this->tramiteData('Parque natural', Carbon::today()->addDays(6), null, OperacionTramite::STATUS_PENDING, 'PN-'.$operacion->id, $permisos),
                $this->tramiteData('FPL', $operationDate->copy(), null, OperacionTramite::STATUS_PENDING, 'FPL-'.$operacion->id, 'Plan de vuelo pendiente para operacion a siete dias.'),
            ],
        };

        foreach ($tramites as $tramite) {
            $path = 'operaciones/'.Str::slug($operacion->reference).'/'.Str::slug($tramite['title']).'.pdf';

            OperacionTramite::updateOrCreate(
                [
                    'operacion_id' => $operacion->id,
                    'title' => $tramite['title'],
                ],
                [
                    'attachments' => [$this->storePdf($path, $tramite['title'].' '.$operacion->reference)],
                    'attachment_file_names' => [Str::slug($tramite['title']).'.pdf'],
                    'deadline_date' => $tramite['deadline_date'],
                    'processed_at' => $tramite['processed_at'],
                    'status' => $tramite['status'],
                    'request_code' => $tramite['request_code'],
                    'extra_information' => $tramite['extra_information'],
                ],
            );
        }
    }

    /**
     * @return array{title: string, deadline_date: Carbon, processed_at: Carbon|null, status: string, request_code: string, extra_information: string}
     */
    protected function tramiteData(
        string $title,
        Carbon $deadlineDate,
        ?Carbon $processedAt,
        string $status,
        string $requestCode,
        string $extraInformation
    ): array {
        return [
            'title' => $title,
            'deadline_date' => $deadlineDate,
            'processed_at' => $processedAt,
            'status' => $status,
            'request_code' => $requestCode,
            'extra_information' => $extraInformation,
        ];
    }

    protected function seedOperadora(Cliente $cliente): void
    {
        OperadoraProfile::updateOrCreate(
            ['cliente_id' => $cliente->id],
            [
                'first_name' => $cliente->name,
                'last_name' => $cliente->last_name,
                'second_last_name' => $cliente->second_last_name,
                'registration_number' => $cliente->operator_registration_number,
                'expiration_date' => '2027-12-31',
            ],
        );

        $path = 'operadora/cliente-'.$cliente->id.'/certificado-operador.pdf';
        $storedPath = $this->storePdf($path, 'Certificado operador '.$cliente->fullName());

        OperadoraRequirement::updateOrCreate(
            [
                'cliente_id' => $cliente->id,
                'is_system_default' => true,
            ],
            [
                'name' => 'CERTIFICADO OPERADOR',
                'input_type' => OperadoraRequirement::TYPE_PDF,
                'is_required' => true,
                'instructions' => 'Certificado operador registrado en AESA.',
                'status' => OperadoraRequirement::STATUS_APPROVED,
                'file_path' => $storedPath,
                'original_file_name' => 'certificado-operador.pdf',
                'mime_type' => 'application/pdf',
                'file_size' => strlen(Storage::disk('public')->get($storedPath)),
                'submitted_at' => now()->subDays(15),
                'reviewed_at' => now()->subDays(14),
            ],
        );
    }

    protected function storePdf(string $path, string $title): string
    {
        Storage::disk('public')->put($path, $this->pdfContent($title));

        return $path;
    }

    protected function pdfContent(string $title): string
    {
        $title = str_replace(['\\', '(', ')'], ['/', '\(', '\)'], $title);
        $line = 'Documento de prueba generado por DemoOperationsSeeder.';

        $objects = [
            "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n",
            "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n",
            "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n",
            "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n",
        ];

        $stream = "BT\n/F1 18 Tf\n72 760 Td\n({$title}) Tj\n/F1 11 Tf\n0 -28 Td\n({$line}) Tj\nET\n";
        $objects[] = "5 0 obj\n<< /Length ".strlen($stream)." >>\nstream\n{$stream}endstream\nendobj\n";

        $pdf = "%PDF-1.4\n";
        $offsets = [0];

        foreach ($objects as $object) {
            $offsets[] = strlen($pdf);
            $pdf .= $object;
        }

        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 ".count($offsets)."\n";
        $pdf .= "0000000000 65535 f \n";

        foreach (array_slice($offsets, 1) as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        $pdf .= "trailer\n<< /Size ".count($offsets)." /Root 1 0 R >>\n";
        $pdf .= "startxref\n{$xrefOffset}\n%%EOF\n";

        return $pdf;
    }

    /**
     * @return array<int, array<string, array<string, mixed>>>
     */
    protected function records(int $firstOperationOffsetDays = 10): array
    {
        return [
            [
                'piloto' => [
                    'first_name' => 'Sergio',
                    'last_name' => 'Navarro',
                    'second_last_name' => 'Ruiz',
                    'dni_nie' => '45879632M',
                    'birth_date' => '1988-11-14',
                    'pilot_identification_number' => 'ESP-RP-000000000004',
                    'maximum_pilot_certification' => 'STS',
                    'address' => 'Avenida del Puerto 45',
                    'country' => 'Espana',
                    'city' => 'Valencia',
                    'province' => 'Valencia',
                    'postal_code' => '46023',
                    'phone' => '623451789',
                    'has_radiofonista_certificate' => true,
                    'theoretical_certificate_level' => Piloto::THEORY_STS,
                ],
                'dron' => [
                    'manufacturer_name' => 'DJI',
                    'model' => 'Air 3S',
                    'drone_serial_number' => 'DJI-A3S-004',
                    'controller_serial_number' => 'RC2-A3S-004',
                    'registration_number' => 'ESP-DRN-004',
                    'registration_not_applicable' => false,
                    'mtom_weight' => 724,
                    'remote_id_number' => 'RID-A3S-004',
                    'remote_id_not_applicable' => false,
                    'class_marking' => 'C2',
                    'band_frequency' => '2.4 GHz y 5.8 GHz',
                    'color' => 'Gris oscuro',
                    'payload' => 'Camara dual gran angular y teleobjetivo',
                    'payload_not_applicable' => false,
                    'vhf_equipment' => 'Motorola XT420',
                    'vhf_not_applicable' => false,
                    'emergency_equipment' => 'Paracaidas integrado',
                    'emergency_not_applicable' => false,
                    'insurance_policy_number' => 'POL-2026-0004',
                    'insurance_valid_until' => '2027-03-15',
                    'insurance_company_name' => 'Mapfre',
                    'insurance_file' => 'seguro-air3s.pdf',
                    'aesa_registration_status' => Dron::AESA_STATUS_YES,
                ],
                'operacion' => [
                    'reference' => 'Seguimiento obras Puerto Valencia',
                    'operation_date' => Carbon::today()->addDays($firstOperationOffsetDays)->toDateString(),
                    'estimated_filming_schedule' => '07:30 a 11:30',
                    'location' => 'Puerto de Valencia',
                    'address' => 'Muelle de Poniente, Puerto de Valencia',
                    'country' => 'Espana',
                    'city' => 'Valencia',
                    'province' => 'Valencia',
                    'postal_code' => '46024',
                    'google_maps_link' => 'https://maps.google.com/?q=Puerto+de+Valencia',
                    'altitude' => 90,
                    'operation_radius' => 250,
                    'operation_cost' => 950,
                    'operational_conditions' => 'Operacion diurna en entorno portuario con coordinacion previa de seguridad.',
                    'extra_information' => 'Seguimiento de avance de obra con vuelos sobre zona restringida.',
                    'video_objective' => 'Seguimiento audiovisual de avance de obras portuarias',
                    'end_client' => 'Autoridad Portuaria de Valencia',
                    'production_company_name' => 'Levante Media',
                    'production_contact_phone' => '623451999',
                    'environment_type' => 'exterior',
                    'people_present' => true,
                    'permisos' => 'Coordinacion previa con seguridad portuaria y autorizacion de acceso a zona restringida.',
                    'description' => 'Operacion de seguimiento de obras en el Puerto de Valencia.',
                ],
            ],
            [
                'piloto' => [
                    'first_name' => 'Marta',
                    'last_name' => 'Jimenez',
                    'second_last_name' => 'Ortega',
                    'dni_nie' => '58412369P',
                    'birth_date' => '1995-02-18',
                    'pilot_identification_number' => 'ESP-RP-000000000005',
                    'maximum_pilot_certification' => 'A1/A3',
                    'address' => 'Calle San Fernando 18',
                    'country' => 'Espana',
                    'city' => 'Sevilla',
                    'province' => 'Sevilla',
                    'postal_code' => '41004',
                    'phone' => '634112233',
                    'has_radiofonista_certificate' => false,
                    'theoretical_certificate_level' => Piloto::THEORY_A1_A3,
                ],
                'dron' => [
                    'manufacturer_name' => 'DJI',
                    'model' => 'Mini 4 Pro',
                    'drone_serial_number' => 'DJI-M4P-005',
                    'controller_serial_number' => 'RC2-M4P-005',
                    'registration_number' => null,
                    'registration_not_applicable' => true,
                    'mtom_weight' => 249,
                    'remote_id_number' => null,
                    'remote_id_not_applicable' => true,
                    'class_marking' => 'C0',
                    'band_frequency' => '2.4 GHz y 5.8 GHz',
                    'color' => 'Blanco',
                    'payload' => null,
                    'payload_not_applicable' => true,
                    'vhf_equipment' => null,
                    'vhf_not_applicable' => true,
                    'emergency_equipment' => null,
                    'emergency_not_applicable' => true,
                    'insurance_policy_number' => 'POL-2026-0005',
                    'insurance_valid_until' => '2026-11-30',
                    'insurance_company_name' => 'Allianz',
                    'insurance_file' => 'seguro-mini4pro-sevilla.pdf',
                    'aesa_registration_status' => Dron::AESA_STATUS_MANAGER,
                ],
                'operacion' => [
                    'reference' => 'Reportaje turistico Plaza Espana',
                    'operation_date' => Carbon::today()->addDays($firstOperationOffsetDays + 5)->toDateString(),
                    'estimated_filming_schedule' => '08:00 a 10:00',
                    'location' => 'Plaza de Espana, Sevilla',
                    'address' => 'Plaza de Espana',
                    'country' => 'Espana',
                    'city' => 'Sevilla',
                    'province' => 'Sevilla',
                    'postal_code' => '41013',
                    'google_maps_link' => 'https://maps.google.com/?q=Plaza+de+Espana+Sevilla',
                    'altitude' => 45,
                    'operation_radius' => 100,
                    'operation_cost' => 650,
                    'operational_conditions' => 'Vuelo temprano en zona urbana con delimitacion temporal de area.',
                    'extra_information' => 'Grabacion promocional con baja altura y dron sub-250g.',
                    'video_objective' => 'Grabacion promocional para campana turistica municipal',
                    'end_client' => 'Turismo de Sevilla',
                    'production_company_name' => 'Sur Visual Media',
                    'production_contact_phone' => '634445566',
                    'environment_type' => 'exterior',
                    'people_present' => true,
                    'permisos' => 'Coordinacion con Ayuntamiento y delimitacion temporal de zona de grabacion.',
                    'description' => 'Reportaje turistico en Plaza de Espana.',
                ],
            ],
            [
                'piloto' => [
                    'first_name' => 'Alberto',
                    'last_name' => 'Torres',
                    'second_last_name' => 'Martin',
                    'dni_nie' => '74125896T',
                    'birth_date' => '1986-06-09',
                    'pilot_identification_number' => 'ESP-RP-000000000006',
                    'maximum_pilot_certification' => 'STS',
                    'address' => 'Calle Ramon y Cajal 12',
                    'country' => 'Espana',
                    'city' => 'Zaragoza',
                    'province' => 'Zaragoza',
                    'postal_code' => '50004',
                    'phone' => '645778899',
                    'has_radiofonista_certificate' => true,
                    'theoretical_certificate_level' => Piloto::THEORY_STS,
                ],
                'dron' => [
                    'manufacturer_name' => 'DJI',
                    'model' => 'Matrice 350 RTK',
                    'drone_serial_number' => 'DJI-M350-006',
                    'controller_serial_number' => 'RC-M350-006',
                    'registration_number' => 'ESP-DRN-006',
                    'registration_not_applicable' => false,
                    'mtom_weight' => 6300,
                    'remote_id_number' => 'RID-M350-006',
                    'remote_id_not_applicable' => false,
                    'class_marking' => 'C3',
                    'band_frequency' => '2.4 GHz y 5.8 GHz',
                    'color' => 'Negro',
                    'payload' => 'Camara Zenmuse H20T',
                    'payload_not_applicable' => false,
                    'vhf_equipment' => 'Motorola DP4401',
                    'vhf_not_applicable' => false,
                    'emergency_equipment' => 'Sistema de recuperacion por paracaidas',
                    'emergency_not_applicable' => false,
                    'insurance_policy_number' => 'POL-2026-0006',
                    'insurance_valid_until' => '2027-07-15',
                    'insurance_company_name' => 'AXA',
                    'insurance_file' => 'seguro-matrice350.pdf',
                    'aesa_registration_status' => Dron::AESA_STATUS_YES,
                ],
                'operacion' => [
                    'reference' => 'Inspeccion lineas electricas Ebro',
                    'operation_date' => Carbon::today()->addDays($firstOperationOffsetDays + 10)->toDateString(),
                    'estimated_filming_schedule' => '06:00 a 12:00',
                    'location' => 'Ribera del Ebro, Zaragoza',
                    'address' => 'Camino de la Alfranca km 3',
                    'country' => 'Espana',
                    'city' => 'Zaragoza',
                    'province' => 'Zaragoza',
                    'postal_code' => '50059',
                    'google_maps_link' => 'https://maps.google.com/?q=Zaragoza+Ribera+del+Ebro',
                    'altitude' => 120,
                    'operation_radius' => 500,
                    'operation_cost' => 1800,
                    'operational_conditions' => 'Inspeccion tecnica con camara termica y coordinacion con operador electrico.',
                    'extra_information' => 'Operacion en entorno rural con recorrido lineal sobre infraestructura electrica.',
                    'video_objective' => 'Inspeccion termica y visual de lineas de alta tension',
                    'end_client' => 'Red Electrica Regional',
                    'production_company_name' => 'AeroInspect Iberia',
                    'production_contact_phone' => '645111222',
                    'environment_type' => 'exterior',
                    'people_present' => false,
                    'permisos' => 'Autorizacion del operador electrico y plan de seguridad aprobado.',
                    'description' => 'Inspeccion de lineas electricas en la ribera del Ebro.',
                ],
            ],
            [
                'piloto' => [
                    'first_name' => 'Cristina',
                    'last_name' => 'Moreno',
                    'second_last_name' => 'Gil',
                    'dni_nie' => '96325874L',
                    'birth_date' => '1991-04-27',
                    'pilot_identification_number' => 'ESP-RP-000000000007',
                    'maximum_pilot_certification' => 'A2',
                    'address' => 'Paseo Maritimo 89',
                    'country' => 'Espana',
                    'city' => 'Malaga',
                    'province' => 'Malaga',
                    'postal_code' => '29016',
                    'phone' => '656889900',
                    'has_radiofonista_certificate' => false,
                    'theoretical_certificate_level' => Piloto::THEORY_A2,
                ],
                'dron' => [
                    'manufacturer_name' => 'Autel',
                    'model' => 'EVO Lite Plus',
                    'drone_serial_number' => 'AUTEL-EVO-007',
                    'controller_serial_number' => 'AUTEL-RC-007',
                    'registration_number' => 'ESP-DRN-007',
                    'registration_not_applicable' => false,
                    'mtom_weight' => 835,
                    'remote_id_number' => 'RID-EVO-007',
                    'remote_id_not_applicable' => false,
                    'class_marking' => 'C1',
                    'band_frequency' => '2.4 GHz y 5.8 GHz',
                    'color' => 'Naranja',
                    'payload' => 'Camara 6K integrada',
                    'payload_not_applicable' => false,
                    'vhf_equipment' => null,
                    'vhf_not_applicable' => true,
                    'emergency_equipment' => 'Luz estroboscopica homologada',
                    'emergency_not_applicable' => false,
                    'insurance_policy_number' => 'POL-2026-0007',
                    'insurance_valid_until' => '2027-02-10',
                    'insurance_company_name' => 'Helvetia',
                    'insurance_file' => 'seguro-autel-evo.pdf',
                    'aesa_registration_status' => Dron::AESA_STATUS_YES,
                ],
                'operacion' => [
                    'reference' => 'Promocion hotel Costa del Sol',
                    'operation_date' => Carbon::today()->addDays($firstOperationOffsetDays + 15)->toDateString(),
                    'estimated_filming_schedule' => '17:00 a 20:00',
                    'location' => 'Paseo Maritimo de Malaga',
                    'address' => 'Paseo Maritimo Pablo Ruiz Picasso 15',
                    'country' => 'Espana',
                    'city' => 'Malaga',
                    'province' => 'Malaga',
                    'postal_code' => '29016',
                    'google_maps_link' => 'https://maps.google.com/?q=Paseo+Maritimo+Malaga',
                    'altitude' => 70,
                    'operation_radius' => 180,
                    'operation_cost' => 900,
                    'operational_conditions' => 'Grabacion exterior en zona hotelera con punto de despegue senalizado.',
                    'extra_information' => 'Produccion promocional con presencia de equipo de marketing del hotel.',
                    'video_objective' => 'Produccion audiovisual para promocion hotelera internacional',
                    'end_client' => 'Costa Resort Hotels',
                    'production_company_name' => 'BlueSky Productions',
                    'production_contact_phone' => '656123456',
                    'environment_type' => 'exterior',
                    'people_present' => true,
                    'permisos' => 'Coordinacion con hotel y senalizacion de zona de despegue.',
                    'description' => 'Promocion hotelera en Costa del Sol.',
                ],
            ],
            [
                'piloto' => [
                    'first_name' => 'Daniel',
                    'last_name' => 'Herrera',
                    'second_last_name' => 'Campos',
                    'dni_nie' => '35795146R',
                    'birth_date' => '1984-09-03',
                    'pilot_identification_number' => 'ESP-RP-000000000008',
                    'maximum_pilot_certification' => 'STS',
                    'address' => 'Calle de Alcala 320',
                    'country' => 'Espana',
                    'city' => 'Madrid',
                    'province' => 'Madrid',
                    'postal_code' => '28027',
                    'phone' => '667445588',
                    'has_radiofonista_certificate' => true,
                    'theoretical_certificate_level' => Piloto::THEORY_STS,
                ],
                'dron' => [
                    'manufacturer_name' => 'DJI',
                    'model' => 'Inspire 3',
                    'drone_serial_number' => 'DJI-INS3-008',
                    'controller_serial_number' => 'RC-INS3-008',
                    'registration_number' => 'ESP-DRN-008',
                    'registration_not_applicable' => false,
                    'mtom_weight' => 3995,
                    'remote_id_number' => 'RID-INS3-008',
                    'remote_id_not_applicable' => false,
                    'class_marking' => 'C2',
                    'band_frequency' => '2.4 GHz y 5.8 GHz',
                    'color' => 'Negro',
                    'payload' => 'Camara Zenmuse X9-8K Air',
                    'payload_not_applicable' => false,
                    'vhf_equipment' => 'Motorola DP4801',
                    'vhf_not_applicable' => false,
                    'emergency_equipment' => 'Sistema de recuperacion de emergencia',
                    'emergency_not_applicable' => false,
                    'insurance_policy_number' => 'POL-2026-0008',
                    'insurance_valid_until' => '2027-08-20',
                    'insurance_company_name' => 'Zurich',
                    'insurance_file' => 'seguro-inspire3.pdf',
                    'aesa_registration_status' => Dron::AESA_STATUS_YES,
                ],
                'operacion' => [
                    'reference' => 'Rodaje serie historica Madrid',
                    'operation_date' => Carbon::today()->addDays($firstOperationOffsetDays + 20)->toDateString(),
                    'estimated_filming_schedule' => '05:30 a 13:00',
                    'location' => 'Casa de Campo, Madrid',
                    'address' => 'Casa de Campo, zona Lago',
                    'country' => 'Espana',
                    'city' => 'Madrid',
                    'province' => 'Madrid',
                    'postal_code' => '28011',
                    'google_maps_link' => 'https://maps.google.com/?q=Casa+de+Campo+Madrid',
                    'altitude' => 100,
                    'operation_radius' => 350,
                    'operation_cost' => 2400,
                    'operational_conditions' => 'Rodaje audiovisual con plan de seguridad especifico y coordinacion municipal.',
                    'extra_information' => 'Captura cinematografica con dron pesado y equipo de produccion en campo.',
                    'video_objective' => 'Captura de secuencias cinematograficas para serie de television',
                    'end_client' => 'Iberia Studios',
                    'production_company_name' => 'Horizon Films Espana',
                    'production_contact_phone' => '667999111',
                    'environment_type' => 'exterior',
                    'people_present' => true,
                    'permisos' => 'Permiso municipal de rodaje, coordinacion con policia local y plan de seguridad especifico para produccion audiovisual.',
                    'description' => 'Rodaje de serie historica en Madrid.',
                ],
            ],
        ];
    }

    /**
     * @param  array<int, int>  $offsets
     * @return array<int, array<string, array<string, mixed>>>
     */
    protected function recordsFromOffsets(array $offsets): array
    {
        $records = $this->records(0);

        foreach ($records as $index => $record) {
            $records[$index]['operacion']['operation_date'] = Carbon::today()
                ->addDays($offsets[$index] ?? 0)
                ->toDateString();
            $records[$index]['operacion']['status'] = Operacion::STATUS_CONFIRMED;
        }

        return $records;
    }
}
