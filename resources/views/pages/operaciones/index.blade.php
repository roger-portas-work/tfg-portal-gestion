<?php

use App\Models\Operacion;
use App\Models\OperacionTramite;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Mis operaciones')] class extends Component {
    public ?int $editingOperacionId = null;

    public bool $showForm = false;

    public string $statusFilter = 'all';

    public string $searchFilter = '';

    public string $dateFromFilter = '';

    public string $dateToFilter = '';

    public string $dronFilter = 'all';

    public string $pilotoFilter = 'all';

    public string $piloto_id = '';

    public string $dron_id = '';

    public string $reference = '';

    public string $operation_date = '';

    public string $estimated_filming_schedule = '';

    public string $address = '';

    public string $country = '';

    public string $city = '';

    public string $province = '';

    public string $postal_code = '';

    public string $google_maps_link = '';

    public string $altitude = '';

    public string $operation_radius = '';

    public string $extra_information = '';

    public string $video_objective = '';

    public string $end_client = '';

    public string $production_company_name = '';

    public string $production_contact_phone = '';

    public string $environment_type = '';

    public string $people_present = '';

    public string $prior_permits_notes = '';

    public function mount(): void
    {
        abort_unless($this->cliente?->isUnblocked(), 403);

        $requestedDron = request('dron');
        $requestedPiloto = request('piloto');

        if (filled($requestedDron) && $this->availableDrones->contains('id', (int) $requestedDron)) {
            $this->dronFilter = (string) $requestedDron;
        }

        if (filled($requestedPiloto) && $this->availablePilotos->contains('id', (int) $requestedPiloto)) {
            $this->pilotoFilter = (string) $requestedPiloto;
        }

        $this->showForm = $this->canCreateOperations()
            && (request()->boolean('crear') || $this->operaciones->isEmpty());
    }

    #[Computed]
    public function cliente()
    {
        return Auth::user()->cliente;
    }

    #[Computed]
    public function operaciones()
    {
        return $this->cliente
            ? $this->cliente->operaciones()
                ->with(['piloto', 'dron'])
                ->withCount([
                    'tramites',
                    'tramites as pending_tramites_count' => fn ($query) => $query->where('status', OperacionTramite::STATUS_PENDING),
                    'tramites as processed_tramites_count' => fn ($query) => $query->where('status', OperacionTramite::STATUS_PROCESSED),
                    'tramites as denied_tramites_count' => fn ($query) => $query->where('status', OperacionTramite::STATUS_DENIED),
                    'tramites as approved_tramites_count' => fn ($query) => $query->where('status', OperacionTramite::STATUS_APPROVED),
                ])
                ->orderBy('operation_date')
                ->orderByDesc('created_at')
                ->get()
            : collect();
    }

    #[Computed]
    public function availablePilotos()
    {
        return $this->cliente
            ? $this->cliente->pilotos()->latest()->get()
            : collect();
    }

    #[Computed]
    public function availableDrones()
    {
        return $this->cliente
            ? $this->cliente->drones()->latest()->get()
            : collect();
    }

    #[Computed]
    public function filteredOperaciones()
    {
        $dateFrom = filled($this->dateFromFilter) ? $this->dateFromFilter : null;
        $dateTo = filled($this->dateToFilter) ? $this->dateToFilter : null;

        if ($dateFrom && $dateTo && $dateFrom > $dateTo) {
            [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
        }

        return $this->operaciones
            ->filter(function (Operacion $operacion) use ($dateFrom, $dateTo): bool {
                if (! $this->operationMatchesSearch($operacion)) {
                    return false;
                }

                $matchesStatus = $this->statusFilter === 'all'
                    || $operacion->status === $this->statusFilter
                    || ($this->statusFilter === Operacion::STATUS_PENDING && $operacion->isPending());

                if (! $matchesStatus) {
                    return false;
                }

                if ($this->dronFilter !== 'all' && (int) $operacion->dron_id !== (int) $this->dronFilter) {
                    return false;
                }

                if ($this->pilotoFilter !== 'all' && (int) $operacion->piloto_id !== (int) $this->pilotoFilter) {
                    return false;
                }

                $operationDate = $this->normalizedOperationDate($operacion->operation_date);

                if (! $operationDate) {
                    return ! $dateFrom && ! $dateTo;
                }

                if ($dateFrom && $operationDate < $dateFrom) {
                    return false;
                }

                if ($dateTo && $operationDate > $dateTo) {
                    return false;
                }

                return true;
            })
            ->values();
    }

    protected function normalizedOperationDate(mixed $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (! filled($value)) {
            return null;
        }

        try {
            return Carbon::parse((string) $value)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    public function clearDateFilters(): void
    {
        $this->dateFromFilter = '';
        $this->dateToFilter = '';
    }

    public function clearFilters(): void
    {
        $this->searchFilter = '';
        $this->statusFilter = 'all';
        $this->pilotoFilter = 'all';
        $this->dronFilter = 'all';
        $this->clearDateFilters();
    }

    public function canCreateOperations(): bool
    {
        return $this->availablePilotos->isNotEmpty() && $this->availableDrones->isNotEmpty();
    }

    public function startCreate(): void
    {
        if (! $this->canCreateOperations()) {
            return;
        }

        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $operacionId): void
    {
        $operacion = $this->cliente?->operaciones()->findOrFail($operacionId);

        abort_unless($operacion?->isPending(), 403);

        $this->editingOperacionId = $operacion->id;
        $this->piloto_id = (string) $operacion->piloto_id;
        $this->dron_id = (string) $operacion->dron_id;
        $this->reference = $operacion->reference;
        $this->operation_date = match (true) {
            $operacion->operation_date instanceof \DateTimeInterface => $operacion->operation_date->format('Y-m-d'),
            filled($operacion->operation_date) => (string) $operacion->operation_date,
            default => '',
        };
        $this->estimated_filming_schedule = $operacion->estimated_filming_schedule ?? '';
        $this->address = $operacion->address ?? $operacion->location ?? '';
        $this->country = $operacion->country ?? '';
        $this->city = $operacion->city ?? '';
        $this->province = $operacion->province ?? '';
        $this->postal_code = $operacion->postal_code ?? '';
        $this->google_maps_link = $operacion->google_maps_link ?? '';
        $this->altitude = filled($operacion->altitude) ? (string) $operacion->altitude : '';
        $this->operation_radius = filled($operacion->operation_radius) ? (string) $operacion->operation_radius : '';
        $this->extra_information = $operacion->extra_information ?? $operacion->description ?? '';
        $this->video_objective = $operacion->video_objective ?? '';
        $this->end_client = $operacion->end_client ?? '';
        $this->production_company_name = $operacion->production_company_name ?? '';
        $this->production_contact_phone = $operacion->production_contact_phone ?? '';
        $this->environment_type = $operacion->environment_type ?? '';
        $this->people_present = match (true) {
            $operacion->people_present === true => '1',
            $operacion->people_present === false => '0',
            default => '',
        };
        $this->prior_permits_notes = $operacion->prior_permits_notes ?? '';
        $this->showForm = true;
    }

    public function cancel(): void
    {
        $this->resetForm();
        $this->showForm = $this->operaciones->isEmpty() && $this->canCreateOperations();
    }

    public function save(): void
    {
        $cliente = $this->cliente;

        abort_unless($cliente, 403);

        $validated = $this->validate([
            'piloto_id' => ['required', 'integer'],
            'dron_id' => ['required', 'integer'],
            'reference' => ['required', 'string', 'max:255'],
            'operation_date' => ['required', 'date'],
            'estimated_filming_schedule' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:255'],
            'country' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:255'],
            'province' => ['required', 'string', 'max:255'],
            'postal_code' => ['required', 'string', 'max:20'],
            'google_maps_link' => ['nullable', 'url', 'max:255'],
            'altitude' => ['required', 'numeric', 'min:0'],
            'operation_radius' => ['required', 'numeric', 'min:0'],
            'video_objective' => ['nullable', 'string', 'max:255'],
            'end_client' => ['nullable', 'string', 'max:255'],
            'production_company_name' => ['nullable', 'string', 'max:255'],
            'production_contact_phone' => ['nullable', 'string', 'max:255'],
            'environment_type' => ['nullable', 'in:interior,exterior'],
            'people_present' => ['nullable', 'in:1,0'],
            'prior_permits_notes' => ['nullable', 'string'],
        ]);

        abort_unless(
            $cliente->pilotos()->whereKey((int) $validated['piloto_id'])->exists(),
            403
        );

        abort_unless(
            $cliente->drones()->whereKey((int) $validated['dron_id'])->exists(),
            403
        );

        $payload = [
            'piloto_id' => (int) $validated['piloto_id'],
            'dron_id' => (int) $validated['dron_id'],
            'reference' => $validated['reference'],
            'operation_date' => $validated['operation_date'],
            'estimated_filming_schedule' => $validated['estimated_filming_schedule'],
            'address' => $validated['address'],
            'country' => $validated['country'],
            'city' => $validated['city'],
            'province' => $validated['province'],
            'postal_code' => $validated['postal_code'],
            'google_maps_link' => $validated['google_maps_link'] ?: null,
            'altitude' => $validated['altitude'],
            'operation_radius' => $validated['operation_radius'],
            'extra_information' => $this->extra_information ?: null,
            'video_objective' => $validated['video_objective'] ?: null,
            'end_client' => $validated['end_client'] ?: null,
            'production_company_name' => $validated['production_company_name'] ?: null,
            'production_contact_phone' => $validated['production_contact_phone'] ?: null,
            'environment_type' => $validated['environment_type'] ?: null,
            'people_present' => $validated['people_present'] !== '' ? $validated['people_present'] === '1' : null,
            'prior_permits_notes' => $validated['prior_permits_notes'] ?: null,
            'location' => $validated['address'],
            'description' => $validated['prior_permits_notes'] ?: ($this->extra_information ?: null),
        ];

        if ($this->editingOperacionId) {
            $operacion = $cliente->operaciones()->findOrFail($this->editingOperacionId);

            abort_unless($operacion->isPending(), 403);

            $operacion->update($payload);
        } else {
            $cliente->operaciones()->create([
                ...$payload,
                'status' => Operacion::STATUS_PENDING,
            ]);
        }

        $this->resetForm();
        $this->showForm = false;
        $this->dispatch('operacion-saved');
    }

    public function delete(int $operacionId): void
    {
        $operacion = $this->cliente?->operaciones()->findOrFail($operacionId);

        abort_unless($operacion?->isPending(), 403);

        $operacion?->delete();
        $this->resetForm();
        $this->showForm = $this->operaciones->isEmpty() && $this->canCreateOperations();
    }

    protected function resetForm(): void
    {
        $this->reset([
            'editingOperacionId',
            'piloto_id',
            'dron_id',
            'reference',
            'operation_date',
            'estimated_filming_schedule',
            'address',
            'country',
            'city',
            'province',
            'postal_code',
            'google_maps_link',
            'altitude',
            'operation_radius',
            'extra_information',
            'video_objective',
            'end_client',
            'production_company_name',
            'production_contact_phone',
            'environment_type',
            'people_present',
            'prior_permits_notes',
        ]);
    }

    protected function formatMetric(null|int|float|string $value, string $unit): string
    {
        if (! filled($value)) {
            return 'Sin definir';
        }

        $formatted = rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');

        return $formatted.' '.$unit;
    }

    protected function formatDateValue(mixed $value): string
    {
        return match (true) {
            $value instanceof \DateTimeInterface => $value->format('d/m/Y'),
            filled($value) => Carbon::parse((string) $value)->format('d/m/Y'),
            default => 'Sin definir',
        };
    }

    protected function formatCurrencyValue(mixed $value): string
    {
        if (! filled($value)) {
            return 'Sin definir';
        }

        return number_format((float) $value, 2, ',', '.').' EUR';
    }

    protected function operationAddressLabel(Operacion $operacion): string
    {
        return trim(collect([
            $operacion->address ?: $operacion->location,
            $operacion->city,
            $operacion->province,
            $operacion->postal_code,
            $operacion->country,
        ])->filter()->implode(', ')) ?: 'Sin definir';
    }

    protected function operationMatchesSearch(Operacion $operacion): bool
    {
        $term = mb_strtolower(trim($this->searchFilter));

        if ($term === '') {
            return true;
        }

        $haystack = mb_strtolower((string) $operacion->reference);

        return str_contains($haystack, $term);
    }

    protected function operationDronLabel(Operacion $operacion): string
    {
        $dronName = $operacion->dron?->displayNameWithSerial() ?? 'Sin dron';

        return $dronName;
    }

    protected function operationTramiteStatusCounts(Operacion $operacion): array
    {
        return [
            [
                'label' => 'Pendientes',
                'count' => (int) ($operacion->pending_tramites_count ?? 0),
                'class' => 'portal-badge portal-badge--sky',
            ],
            [
                'label' => 'Tramitados',
                'count' => (int) ($operacion->processed_tramites_count ?? 0),
                'class' => 'portal-badge portal-badge--amber',
            ],
            [
                'label' => 'Denegados',
                'count' => (int) ($operacion->denied_tramites_count ?? 0),
                'class' => 'portal-badge portal-badge--danger',
            ],
            [
                'label' => 'Aprobados',
                'count' => (int) ($operacion->approved_tramites_count ?? 0),
                'class' => 'portal-badge portal-badge--emerald',
            ],
        ];
    }

    protected function operationDocumentationFullyApproved(Operacion $operacion): bool
    {
        $tramitesCount = (int) ($operacion->tramites_count ?? 0);
        $approvedCount = (int) ($operacion->approved_tramites_count ?? 0);

        return $tramitesCount > 0 && $approvedCount === $tramitesCount;
    }

    protected function operationStatusBadgeClass(Operacion $operacion): string
    {
        return match ($operacion->status) {
            Operacion::STATUS_CONFIRMED => 'portal-badge portal-badge--emerald',
            Operacion::STATUS_REJECTED => 'portal-badge portal-badge--danger',
            default => 'portal-badge portal-badge--amber',
        };
    }

    protected function operationCardClass(Operacion $operacion): string
    {
        return match ($operacion->status) {
            Operacion::STATUS_CONFIRMED => 'portal-record-card portal-record-card--confirmed',
            Operacion::STATUS_REJECTED => 'portal-record-card portal-record-card--rejected',
            default => 'portal-record-card portal-record-card--pending',
        };
    }

    protected function operationStatusLabel(Operacion $operacion): string
    {
        return $operacion->isConfirmed()
            ? 'Operacion confirmada por cliente'
            : $operacion->statusLabel();
    }

    protected function operationPrimaryTitle(Operacion $operacion): string
    {
        return $operacion->isConfirmed()
            ? 'Tramites aprobados para operacion '.$operacion->reference
            : $operacion->reference;
    }

    /**
     * @return array<int, array{label: string, meta: string, completed: bool}>
     */
    protected function operationTimelineSteps(Operacion $operacion): array
    {
        $tramitesCount = (int) ($operacion->tramites_count ?? 0);
        $approvedCount = (int) ($operacion->approved_tramites_count ?? 0);

        return [
            [
                'label' => 'Solicitud creada',
                'meta' => optional($operacion->created_at)->format('d/m/Y') ?: 'Pendiente',
                'completed' => true,
            ],
            [
                'label' => 'Confirmacion gestor',
                'meta' => $operacion->isRejected()
                    ? 'Rechazada por gestor'
                    : ($operacion->isConfirmed() ? 'Operacion confirmada' : 'Pendiente'),
                'completed' => $operacion->isConfirmed(),
            ],
            [
                'label' => 'Tramitando tramites',
                'meta' => $tramitesCount > 0
                    ? $tramitesCount.' '.($tramitesCount === 1 ? 'tramite creado' : 'tramites creados')
                    : 'Pendiente',
                'completed' => $tramitesCount > 0,
            ],
            [
                'label' => 'Tramites aprobados',
                'meta' => $this->operationDocumentationFullyApproved($operacion)
                    ? 'Todos aprobados'
                    : ($tramitesCount > 0 ? $approvedCount.' / '.$tramitesCount.' aprobados' : 'Pendiente'),
                'completed' => $this->operationDocumentationFullyApproved($operacion),
            ],
        ];
    }
}; ?>

<section class="portal-page portal-page--wide">
    <x-pages::settings.layout heading="" subheading="">
        <div class="portal-hero portal-hero--client">
            <div class="portal-hero__row">
                <div class="portal-operations-hero__copy">
                    <p class="portal-hero__eyebrow text-sky-700 dark:text-sky-300">Portal cliente</p>
                    <h1 class="portal-hero__title">Mis operaciones</h1>
                    <p class="portal-hero__text">
                        Consulta y sigue el estado de tus operaciones, tramites y documentacion.
                    </p>

                    @if ($this->operaciones->isNotEmpty() && ! $showForm && $this->canCreateOperations())
                        <div class="portal-hero__actions">
                            <flux:button variant="primary" wire:click="startCreate">
                                Crear operacion
                            </flux:button>
                        </div>
                    @endif
                </div>

                <div class="portal-operations-hero__aside">
                    <div class="portal-hero__brand">
                        <img
                            src="{{ asset('images/logo-idronlex.png') }}"
                            alt="Idron Lex & Consulting"
                            class="portal-hero__logo"
                        >
                    </div>
                </div>
            </div>
        </div>

        @if (! $this->canCreateOperations())
            <div class="portal-empty-state">
                <h2 class="text-lg font-semibold text-neutral-900 dark:text-white">Antes de crear una operacion</h2>
                <p class="mt-2 text-sm text-neutral-700 dark:text-neutral-300">
                    Necesitas tener al menos un piloto y un dron registrados para poder crear operaciones.
                </p>

                <div class="mt-5 flex flex-wrap gap-3">
                    @if ($this->availablePilotos->isEmpty())
                        <flux:button as="a" variant="primary" :href="route('pilotos.index')" wire:navigate>
                            Ir a Pilotos
                        </flux:button>
                    @endif

                    @if ($this->availableDrones->isEmpty())
                        <flux:button as="a" variant="filled" :href="route('drones.index')" wire:navigate>
                            Ir a Drones
                        </flux:button>
                    @endif
                </div>
            </div>
        @elseif ($showForm)
            <div class="portal-form-shell">
                <div class="portal-form-header">
                    <div>
                        <h2 class="portal-form-title">
                            {{ $editingOperacionId ? 'Editar operacion' : 'Crear operacion' }}
                        </h2>
                        <p class="portal-form-text">
                            Selecciona el dron y el piloto, y despues completa los datos de la operacion.
                        </p>
                    </div>

                    @if ($this->operaciones->isNotEmpty())
                        <flux:button variant="ghost" wire:click="cancel">Volver al listado</flux:button>
                    @endif
                </div>

                <form wire:submit="save" class="portal-form-sections">
                    <div class="grid gap-6 md:grid-cols-2">
                        <div>
                            <label class="mb-2 block text-sm font-medium text-neutral-900 dark:text-white">Dron</label>
                            <select wire:model="dron_id" data-flux-control class="block w-full rounded-lg border border-zinc-200 border-b-zinc-300/80 bg-white p-3 text-sm text-zinc-700 shadow-xs dark:border-white/10 dark:bg-white/10 dark:text-zinc-300">
                                <option value="">Selecciona un dron</option>
                                @foreach ($this->availableDrones as $dron)
                                    <option value="{{ $dron->id }}">{{ $dron->displayNameWithSerial() }}{{ filled($dron->registration_number) ? ' - Matricula: '.$dron->registrationLabel() : '' }}</option>
                                @endforeach
                            </select>
                            @error('dron_id') <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-medium text-neutral-900 dark:text-white">Piloto</label>
                            <select wire:model="piloto_id" data-flux-control class="block w-full rounded-lg border border-zinc-200 border-b-zinc-300/80 bg-white p-3 text-sm text-zinc-700 shadow-xs dark:border-white/10 dark:bg-white/10 dark:text-zinc-300">
                                <option value="">Selecciona un piloto</option>
                                @foreach ($this->availablePilotos as $piloto)
                                    <option value="{{ $piloto->id }}">{{ $piloto->displayNameWithIdentification() }}</option>
                                @endforeach
                            </select>
                            @error('piloto_id') <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="portal-form-section">
                        <h3 class="portal-form-section__title">Datos de la operacion</h3>
                        <p class="portal-form-section__text">
                            Define el nombre, la fecha y la referencia geografica principal de la operacion.
                        </p>

                        <div class="mt-6 grid gap-6 md:grid-cols-2">
                            <flux:input wire:model="reference" label="Nombre de la operacion" type="text" required />
                            <flux:input wire:model="operation_date" label="Fecha de la operacion" type="date" required />
                        </div>

                        <div class="mt-6 grid gap-6 md:grid-cols-2">
                            <flux:input wire:model="estimated_filming_schedule" label="Horario de rodaje estimado" type="text" placeholder="Ej. 08:00 a 14:00" required />
                            <flux:input wire:model="google_maps_link" label="Link Google Maps" type="url" />
                        </div>

                        <div class="mt-6 grid gap-6">
                            <flux:input wire:model="address" label="Direccion completa" type="text" placeholder="Ej. Calle Mayor 15, nave 3" required />
                        </div>

                        <div class="mt-6 grid gap-6 md:grid-cols-2 xl:grid-cols-4">
                            <flux:input wire:model="country" label="Pais" type="text" placeholder="Ej. Espana" required />
                            <flux:input wire:model="city" label="Ciudad" type="text" placeholder="Ej. Barcelona" required />
                            <flux:input wire:model="province" label="Provincia" type="text" placeholder="Ej. Barcelona" required />
                            <flux:input wire:model="postal_code" label="Codigo postal" type="text" placeholder="Ej. 08001" required />
                        </div>

                        <div class="mt-6 grid gap-6 md:grid-cols-2">
                            <flux:input wire:model="altitude" label="Altitud" type="number" step="0.01" min="0" required />
                            <flux:input wire:model="operation_radius" label="Radio operacion" type="number" step="0.01" min="0" required />
                        </div>
                    </div>

                    <div class="portal-form-section">
                        <h3 class="portal-form-section__title">Informacion complementaria</h3>
                        <p class="portal-form-section__text">
                            Este bloque es opcional. Rellenalo solo si ya tienes esta informacion disponible.
                        </p>

                        <div class="mt-6 grid gap-6 md:grid-cols-2">
                            <flux:input wire:model="video_objective" label="Objetivo del video que se va a grabar" type="text" />
                            <flux:input wire:model="end_client" label="Cliente final" type="text" />
                        </div>

                        <div class="mt-6 grid gap-6 md:grid-cols-2">
                            <flux:input wire:model="production_company_name" label="Nombre de la productora" type="text" />
                            <flux:input wire:model="production_contact_phone" label="Numero de telefono de la productora o contacto en set" type="text" />
                        </div>

                        <div class="mt-6 grid gap-6 md:grid-cols-2">
                            <div>
                                <label class="mb-2 block text-sm font-medium text-neutral-900 dark:text-white">Interior o exterior</label>
                                <select wire:model="environment_type" data-flux-control class="block w-full rounded-lg border border-zinc-200 border-b-zinc-300/80 bg-white p-3 text-sm text-zinc-700 shadow-xs dark:border-white/10 dark:bg-white/10 dark:text-zinc-300">
                                    <option value="">Selecciona una opcion</option>
                                    <option value="interior">Interior</option>
                                    <option value="exterior">Exterior</option>
                                </select>
                                @error('environment_type') <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label class="mb-2 block text-sm font-medium text-neutral-900 dark:text-white">Hay gente</label>
                                <select wire:model="people_present" data-flux-control class="block w-full rounded-lg border border-zinc-200 border-b-zinc-300/80 bg-white p-3 text-sm text-zinc-700 shadow-xs dark:border-white/10 dark:bg-white/10 dark:text-zinc-300">
                                    <option value="">Selecciona una opcion</option>
                                    <option value="1">Si</option>
                                    <option value="0">No</option>
                                </select>
                                @error('people_present') <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        <div class="mt-6 grid gap-6">
                            <flux:textarea wire:model="prior_permits_notes" label="Permisos previos necesarios" rows="4" />
                        </div>
                    </div>

                    <div class="portal-form-actions">
                        <flux:button variant="primary" type="submit">
                            {{ $editingOperacionId ? 'Guardar cambios' : 'Guardar operacion' }}
                        </flux:button>

                        <x-action-message class="me-3" on="operacion-saved">
                            Guardado.
                        </x-action-message>
                    </div>
                </form>
            </div>
        @else
            @php
                $totalOperations = $this->operaciones->count();
                $pendingOperations = $this->operaciones->filter(fn (Operacion $operacion): bool => $operacion->isPending())->count();
                $confirmedOperations = $this->operaciones->filter(fn (Operacion $operacion): bool => $operacion->isConfirmed())->count();
                $rejectedOperations = $this->operaciones->filter(fn (Operacion $operacion): bool => $operacion->isRejected())->count();
                $hasActiveFilters = filled($searchFilter)
                    || $statusFilter !== 'all'
                    || $pilotoFilter !== 'all'
                    || $dronFilter !== 'all'
                    || filled($dateFromFilter)
                    || filled($dateToFilter);
                $operationFilters = [
                    'all' => 'Todas',
                    Operacion::STATUS_PENDING => 'Pendientes',
                    Operacion::STATUS_REJECTED => 'Rechazadas',
                    Operacion::STATUS_CONFIRMED => 'Confirmadas',
                ];
            @endphp

            <div class="portal-operation-summary-grid">
                <div class="portal-operation-summary-card portal-operation-summary-card--total">
                    <span class="portal-operation-summary-card__icon">
                        <flux:icon icon="clipboard-document-list" variant="mini" class="size-5" />
                    </span>
                    <span>
                        <small>Total operaciones</small>
                        <strong>{{ $totalOperations }}</strong>
                        <em>Todas registradas</em>
                    </span>
                </div>

                <div class="portal-operation-summary-card portal-operation-summary-card--pending">
                    <span class="portal-operation-summary-card__icon">
                        <flux:icon icon="clock" variant="mini" class="size-5" />
                    </span>
                    <span>
                        <small>Pendientes</small>
                        <strong>{{ $pendingOperations }}</strong>
                        <em>Por confirmar</em>
                    </span>
                </div>

                <div class="portal-operation-summary-card portal-operation-summary-card--confirmed">
                    <span class="portal-operation-summary-card__icon">
                        <flux:icon icon="check-circle" variant="mini" class="size-5" />
                    </span>
                    <span>
                        <small>Confirmadas</small>
                        <strong>{{ $confirmedOperations }}</strong>
                        <em>En curso o aprobadas</em>
                    </span>
                </div>

                <div class="portal-operation-summary-card portal-operation-summary-card--rejected">
                    <span class="portal-operation-summary-card__icon">
                        <flux:icon icon="x-circle" variant="mini" class="size-5" />
                    </span>
                    <span>
                        <small>Rechazadas</small>
                        <strong>{{ $rejectedOperations }}</strong>
                        <em>No aprobadas</em>
                    </span>
                </div>
            </div>

            <div class="portal-operations-filter">
                <div class="portal-operations-filter__search">
                    <label class="portal-operations-filter__field">
                        <span>Buscar operacion</span>
                        <input
                            type="search"
                            wire:model.live.debounce.300ms="searchFilter"
                            placeholder="Buscar por nombre de operacion"
                            class="portal-operations-filter__input"
                        >
                    </label>
                </div>

                <div class="portal-operations-filter__status">
                    <span class="portal-operations-filter__label">Estado</span>
                    <div class="portal-operations-filter__status-grid">
                        @foreach ($operationFilters as $value => $label)
                            <button
                                type="button"
                                wire:click="$set('statusFilter', '{{ $value }}')"
                                class="portal-operation-filter-pill {{ $statusFilter === $value ? 'portal-operation-filter-pill--active' : '' }} {{ $value === 'all' ? 'portal-operation-filter-pill--all' : ($value === Operacion::STATUS_PENDING ? 'portal-operation-filter-pill--pending' : ($value === Operacion::STATUS_REJECTED ? 'portal-operation-filter-pill--rejected' : 'portal-operation-filter-pill--confirmed')) }}"
                            >
                                <span></span>
                                {{ $label }}
                            </button>
                        @endforeach
                    </div>
                </div>

                <label class="portal-operations-filter__field portal-operations-filter__field--pilot">
                    <span>Piloto</span>
                    <select wire:model.live="pilotoFilter" class="portal-operations-filter__input">
                        <option value="all">Todos los pilotos</option>
                        @foreach ($this->availablePilotos as $filterPiloto)
                            <option value="{{ $filterPiloto->id }}">
                                {{ $filterPiloto->displayNameWithIdentification() }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="portal-operations-filter__field portal-operations-filter__field--dron">
                    <span>Dron</span>
                    <select wire:model.live="dronFilter" class="portal-operations-filter__input">
                        <option value="all">Todos los drones</option>
                        @foreach ($this->availableDrones as $filterDron)
                            <option value="{{ $filterDron->id }}">
                                {{ $filterDron->displayNameWithSerial() }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="portal-operations-filter__field portal-operations-filter__field--date-from">
                    <span>Fecha desde</span>
                    <input type="date" wire:model.live="dateFromFilter" class="portal-operations-filter__input">
                </label>

                <label class="portal-operations-filter__field portal-operations-filter__field--date-to">
                    <span>Fecha hasta</span>
                    <input type="date" wire:model.live="dateToFilter" class="portal-operations-filter__input">
                </label>

                <div class="portal-operations-filter__actions">
                    @if ($hasActiveFilters)
                        <button type="button" wire:click="clearFilters" class="portal-operations-filter__reset">
                            Limpiar filtros
                        </button>
                    @endif
                </div>
            </div>

            @if ($this->filteredOperaciones->isEmpty())
                <div class="portal-empty-state">
                    No hay operaciones para el filtro seleccionado.
                </div>
            @else
                <div class="portal-operation-card-list">
                    @foreach ($this->filteredOperaciones as $operacion)
                        @php
                            $tramitesCount = (int) ($operacion->tramites_count ?? 0);
                            $pendingTramites = (int) ($operacion->pending_tramites_count ?? 0);
                            $processedTramites = (int) ($operacion->processed_tramites_count ?? 0);
                            $deniedTramites = (int) ($operacion->denied_tramites_count ?? 0);
                            $approvedTramites = (int) ($operacion->approved_tramites_count ?? 0);
                            $documentationFullyApproved = $this->operationDocumentationFullyApproved($operacion);
                            $operationTone = $operacion->isRejected()
                                ? 'rejected'
                                : ($operacion->isPending() ? 'pending' : ($documentationFullyApproved ? 'approved' : 'confirmed'));
                            $statusLabel = $operacion->isConfirmed()
                                ? 'Operacion confirmada'
                                : $operacion->statusLabel();
                            $documentationTone = $operacion->isRejected()
                                ? 'rejected'
                                : ($operacion->isPending() ? 'pending' : ($documentationFullyApproved ? 'approved' : ($deniedTramites > 0 ? 'danger' : 'partial')));
                            $documentationTitle = match (true) {
                                $operacion->isRejected() => 'Operacion rechazada',
                                $operacion->isPending() => 'Pendiente de confirmacion por el gestor',
                                $documentationFullyApproved => 'Documentacion aprobada',
                                $deniedTramites > 0 => 'Falta documentacion por aprobar',
                                $tramitesCount === 0 => 'Operacion confirmada sin tramites',
                                default => 'Documentacion en curso',
                            };
                            $documentationText = match (true) {
                                $operacion->isRejected() => 'Esta operacion no admite nuevos tramites mientras este rechazada.',
                                $operacion->isPending() => 'El gestor debe confirmar esta operacion antes de crear tramites.',
                                $documentationFullyApproved => 'Todos los tramites estan aprobados. Operacion autorizada.',
                                $deniedTramites > 0 => $deniedTramites.' '.($deniedTramites === 1 ? 'tramite denegado' : 'tramites denegados').'. Revisa la documentacion.',
                                $tramitesCount === 0 => 'Aceptada, pendiente de gestion documental.',
                                default => $approvedTramites.' de '.$tramitesCount.' tramites aprobados.',
                            };
                            $primaryActionHref = $operacion->isConfirmed()
                                ? route('operaciones.tramites-aprobados', $operacion)
                                : null;
                            $primaryActionLabel = $documentationFullyApproved ? 'Ver tramites aprobados' : 'Ver tramites';
                        @endphp

                        <details id="operacion-{{ $operacion->id }}" class="portal-operation-list-card portal-operation-list-card--{{ $operationTone }} portal-anchor-target">
                            <summary class="portal-operation-list-card__header">
                                <div class="portal-operation-list-card__title-wrap">
                                    <span class="portal-operation-status-pill portal-operation-status-pill--{{ $operationTone }}">
                                        @if ($operacion->isConfirmed())
                                            <flux:icon icon="check-circle" variant="mini" class="size-4" />
                                        @elseif ($operacion->isRejected())
                                            <flux:icon icon="x-circle" variant="mini" class="size-4" />
                                        @else
                                            <flux:icon icon="clock" variant="mini" class="size-4" />
                                        @endif
                                        {{ $statusLabel }}
                                    </span>
                                    <h2>{{ $operacion->reference }}</h2>
                                </div>

                                <div class="portal-operation-list-card__summary-actions">
                                    <span class="portal-operation-doc-pill portal-operation-doc-pill--{{ $documentationTone }}">
                                        {{ $documentationTitle }}
                                    </span>
                                    <span class="portal-operation-toggle-indicator" aria-hidden="true"></span>
                                </div>
                            </summary>

                            <div class="portal-operation-list-card__body">
                                <div class="portal-operation-list-card__main">
                                    <div class="portal-operation-doc-strip portal-operation-doc-strip--{{ $documentationTone }}">
                                        <span class="portal-operation-doc-strip__icon">
                                            <flux:icon icon="document-text" variant="mini" class="size-5" />
                                        </span>
                                        <span class="portal-operation-doc-strip__text">{{ $documentationText }}</span>
                                        <span class="portal-operation-count-pill portal-operation-count-pill--pending">Pendientes <b>{{ $pendingTramites }}</b></span>
                                        <span class="portal-operation-count-pill portal-operation-count-pill--processed">Procesados <b>{{ $processedTramites }}</b></span>
                                        <span class="portal-operation-count-pill portal-operation-count-pill--denied">Denegados <b>{{ $deniedTramites }}</b></span>
                                        <span class="portal-operation-count-pill portal-operation-count-pill--approved">Aprobados <b>{{ $approvedTramites }}</b></span>
                                    </div>

                                    <div class="portal-operation-meta-row">
                                        <div class="portal-operation-meta-item">
                                            <flux:icon icon="calendar-days" variant="mini" class="size-4" />
                                            <span>
                                                <small>Fecha</small>
                                                <strong>{{ $this->formatDateValue($operacion->operation_date) }}</strong>
                                            </span>
                                        </div>

                                        <div class="portal-operation-meta-item">
                                            <flux:icon icon="clock" variant="mini" class="size-4" />
                                            <span>
                                                <small>Hora / rodaje estimado</small>
                                                <strong>{{ $operacion->estimated_filming_schedule ?: 'Sin definir' }}</strong>
                                            </span>
                                        </div>

                                        <div class="portal-operation-meta-item portal-operation-meta-item--wide">
                                            <flux:icon icon="map-pin" variant="mini" class="size-4" />
                                            <span>
                                                <small>Direccion</small>
                                                <strong>{{ $this->operationAddressLabel($operacion) }}</strong>
                                            </span>
                                        </div>
                                    </div>

                                    <div class="portal-operation-admin-note">
                                        <div class="portal-operation-admin-note__item portal-operation-admin-note__item--cost">
                                            <span class="portal-operation-admin-note__icon">EUR</span>
                                            <span>
                                                <small>Coste operacion</small>
                                                <strong>{{ $this->formatCurrencyValue($operacion->operation_cost) }}</strong>
                                            </span>
                                        </div>

                                        <div class="portal-operation-admin-note__item portal-operation-admin-note__item--comment">
                                            <span>
                                                <small>Comentario gestor</small>
                                                <strong>{{ filled($operacion->operational_conditions) ? $operacion->operational_conditions : 'Sin comentario del gestor' }}</strong>
                                            </span>
                                        </div>
                                    </div>

                                    <div class="portal-operation-card-timeline">
                                        @foreach ($this->operationTimelineSteps($operacion) as $step)
                                            <div class="portal-operation-card-timeline__step {{ $step['completed'] ? 'portal-operation-card-timeline__step--completed' : '' }}">
                                                <span>
                                                    @if ($step['completed'])
                                                        <flux:icon icon="check" variant="micro" class="size-3.5" />
                                                    @else
                                                        {{ $loop->iteration }}
                                                    @endif
                                                </span>
                                                <div>
                                                    <strong>{{ $step['label'] }}</strong>
                                                    <small>{{ $step['meta'] }}</small>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>

                                <aside class="portal-operation-list-card__side">
                                    <div class="portal-operation-side-grid">
                                        <div class="portal-operation-side-card">
                                            <flux:icon icon="user" variant="mini" class="size-4" />
                                            <span>
                                                <small>Piloto</small>
                                                <strong>{{ $operacion->piloto?->displayNameWithIdentification() ?? 'Sin piloto' }}</strong>
                                            </span>
                                        </div>

                                        <div class="portal-operation-side-card">
                                            <flux:icon icon="paper-airplane" variant="mini" class="size-4" />
                                            <span>
                                                <small>Dron</small>
                                                <strong>{{ $this->operationDronLabel($operacion) }}</strong>
                                            </span>
                                        </div>

                                        <div class="portal-operation-side-card">
                                            <flux:icon icon="arrows-up-down" variant="mini" class="size-4" />
                                            <span>
                                                <small>Altitud</small>
                                                <strong>{{ $this->formatMetric($operacion->altitude, 'm') }}</strong>
                                            </span>
                                        </div>

                                        <div class="portal-operation-side-card">
                                            <flux:icon icon="rss" variant="mini" class="size-4" />
                                            <span>
                                                <small>Radio</small>
                                                <strong>{{ $this->formatMetric($operacion->operation_radius, 'm') }}</strong>
                                            </span>
                                        </div>
                                    </div>

                                    <div class="portal-operation-card-actions">
                                        @if ($operacion->isConfirmed())
                                            <a href="{{ $primaryActionHref }}" wire:navigate class="portal-operation-card-action portal-operation-card-action--primary">
                                                {{ $primaryActionLabel }}
                                            </a>
                                        @elseif ($operacion->isPending())
                                            <button type="button" wire:click="edit({{ $operacion->id }})" class="portal-operation-card-action portal-operation-card-action--primary">
                                                Editar operacion
                                            </button>
                                            <button type="button" wire:click="delete({{ $operacion->id }})" class="portal-operation-card-action portal-operation-card-action--danger">
                                                Borrar operacion
                                            </button>
                                        @else
                                            <span class="portal-operation-card-action portal-operation-card-action--muted">
                                                Sin acciones pendientes
                                            </span>
                                        @endif
                                    </div>
                                </aside>
                            </div>
                        </details>
                    @endforeach
                </div>
            @endif
        @endif
    </x-pages::settings.layout>
</section>
