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

        $this->showForm = $this->operaciones->isEmpty() && $this->canCreateOperations();
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
                ->latest()
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

    protected function operationDronLabel(Operacion $operacion): string
    {
        $dronName = trim(($operacion->dron?->manufacturer_name ?? '').' '.($operacion->dron?->model ?? '')) ?: 'Sin dron';

        if (! $operacion->dron || (! filled($operacion->dron->registration_number) && ! $operacion->dron->registration_not_applicable)) {
            return $dronName;
        }

        return $dronName.' - '.$operacion->dron->registrationLabel();
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
}; ?>

<section class="portal-page">
    <x-pages::settings.layout heading="" subheading="">
        <div class="portal-hero portal-hero--emerald">
            <div class="portal-hero__row">
                <div>
                    <p class="portal-hero__eyebrow text-emerald-700 dark:text-emerald-300">Portal cliente</p>
                    <h1 class="portal-hero__title">Mis operaciones</h1>
                    <p class="portal-hero__text">
                        Crea operaciones vinculando un piloto y un dron de tu expediente.
                    </p>
                </div>

                @if ($this->operaciones->isNotEmpty() && ! $showForm && $this->canCreateOperations())
                    <flux:button variant="primary" wire:click="startCreate">
                        Crear operacion
                    </flux:button>
                @endif
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
                                    <option value="{{ $dron->id }}">{{ trim($dron->manufacturer_name.' '.$dron->model) }}{{ filled($dron->registration_number) || $dron->registration_not_applicable ? ' - '.$dron->registrationLabel() : '' }}</option>
                                @endforeach
                            </select>
                            @error('dron_id') <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-medium text-neutral-900 dark:text-white">Piloto</label>
                            <select wire:model="piloto_id" data-flux-control class="block w-full rounded-lg border border-zinc-200 border-b-zinc-300/80 bg-white p-3 text-sm text-zinc-700 shadow-xs dark:border-white/10 dark:bg-white/10 dark:text-zinc-300">
                                <option value="">Selecciona un piloto</option>
                                @foreach ($this->availablePilotos as $piloto)
                                    <option value="{{ $piloto->id }}">{{ $piloto->fullName() }}</option>
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
            <div class="portal-record-list portal-record-list--relaxed">
                @foreach ($this->operaciones as $operacion)
                    <div class="{{ $this->operationCardClass($operacion) }}">
                        <div class="portal-record-card__header">
                            <div class="min-w-0 flex-1">
                                <h2 class="portal-record-card__title portal-operation-card__title">{{ $operacion->reference }}</h2>

                                <div class="portal-record-card__badges">
                                    <span class="{{ $this->operationStatusBadgeClass($operacion) }}">
                                        {{ $this->operationStatusLabel($operacion) }}
                                    </span>
                                </div>

                                @if ($operacion->isConfirmed())
                                    <div class="portal-operation-confirmed-summary">
                                        <span>Coste: {{ $this->formatCurrencyValue($operacion->operation_cost) }}</span>
                                        <span>Condiciones: {{ $operacion->operational_conditions ?: 'Sin definir' }}</span>
                                    </div>
                                @endif
                            </div>

                            <div class="portal-record-card__actions">
                                @if ($operacion->isPending())
                                    <flux:button variant="ghost" wire:click="edit({{ $operacion->id }})">
                                        Editar
                                    </flux:button>
                                    <flux:button variant="danger" wire:click="delete({{ $operacion->id }})">
                                        Borrar
                                    </flux:button>
                                @endif
                            </div>
                        </div>

                        @if ($operacion->isConfirmed())
                            <div class="portal-operation-status-panel {{ $this->operationDocumentationFullyApproved($operacion) ? 'portal-operation-status-panel--confirmed' : 'portal-operation-status-panel--missing-docs' }}">
                                <div>
                                    <p class="portal-operation-status-panel__title">
                                        @if ($this->operationDocumentationFullyApproved($operacion))
                                            Documentacion aprobada
                                        @else
                                            <span class="inline-flex items-center gap-2">
                                                <flux:icon icon="exclamation-triangle" variant="mini" class="size-4 text-red-600 dark:text-red-400" />
                                                <span>Falta documentacion para aprobar</span>
                                            </span>
                                        @endif
                                    </p>
                                </div>

                                <div class="portal-operation-docs {{ $this->operationDocumentationFullyApproved($operacion) ? 'portal-operation-docs--approved' : 'portal-operation-docs--missing' }}">
                                    <div class="min-w-0">
                                        <p class="portal-operation-docs__text">
                                            Consulta el estado de los tramites y descarga los PDFs aprobados por el gestor.
                                        </p>

                                        <div class="portal-operation-docs__badges">
                                            @foreach ($this->operationTramiteStatusCounts($operacion) as $statusCount)
                                                <span class="{{ $statusCount['class'] }}">
                                                    {{ $statusCount['label'] }}: {{ $statusCount['count'] }}
                                                </span>
                                            @endforeach
                                        </div>
                                    </div>

                                    <flux:button as="a" variant="primary" :href="route('operaciones.tramites-aprobados', $operacion)" wire:navigate>
                                        Ver tramites aprobados
                                    </flux:button>
                                </div>
                            </div>
                        @endif

                        <div class="portal-spec-grid">
                            <div class="portal-spec-card portal-spec-card--featured">
                                <p class="portal-spec-card__label">Fecha</p>
                                <p class="portal-spec-card__value">{{ $this->formatDateValue($operacion->operation_date) }}</p>
                            </div>

                            <div class="portal-spec-card portal-spec-card--featured">
                                <p class="portal-spec-card__label">Hora / rodaje estimado</p>
                                <p class="portal-spec-card__value">{{ $operacion->estimated_filming_schedule ?: 'Sin definir' }}</p>
                            </div>

                            <div class="portal-spec-card portal-spec-card--featured xl:col-span-1">
                                <p class="portal-spec-card__label">Direccion</p>
                                <p class="portal-spec-card__value">{{ $this->operationAddressLabel($operacion) }}</p>
                            </div>

                            <div class="portal-spec-card">
                                <p class="portal-spec-card__label">Piloto</p>
                                <p class="portal-spec-card__value">{{ $operacion->piloto?->fullName() ?? 'Sin piloto' }}</p>
                            </div>

                            <div class="portal-spec-card">
                                <p class="portal-spec-card__label">Dron</p>
                                <p class="portal-spec-card__value">{{ $this->operationDronLabel($operacion) }}</p>
                            </div>

                            <div class="portal-spec-card">
                                <p class="portal-spec-card__label">Altitud</p>
                                <p class="portal-spec-card__value">{{ $this->formatMetric($operacion->altitude, 'm') }}</p>
                            </div>

                            <div class="portal-spec-card">
                                <p class="portal-spec-card__label">Radio</p>
                                <p class="portal-spec-card__value">{{ $this->formatMetric($operacion->operation_radius, 'm') }}</p>
                            </div>
                        </div>

                        @if ($operacion->isPending())
                            <div class="portal-operation-status-panel portal-operation-status-panel--pending">
                                <p class="portal-operation-status-panel__title">Operacion pendiente</p>
                                <p class="portal-operation-status-panel__text">
                                    La operacion esta pendiente de revision por parte del gestor.
                                </p>
                            </div>
                        @elseif ($operacion->isRejected())
                            <div class="portal-operation-status-panel portal-operation-status-panel--rejected">
                                <p class="portal-operation-status-panel__title">Operacion rechazada</p>
                                <p class="portal-operation-status-panel__text">
                                    La operacion ha sido rechazada por el gestor. Esta operacion ya no requiere mas accion.
                                </p>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </x-pages::settings.layout>
</section>
