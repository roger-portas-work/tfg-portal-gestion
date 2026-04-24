<?php

use App\Models\Dron;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Mis drones')] class extends Component {
    use WithFileUploads;

    public ?int $editingDronId = null;

    public bool $showForm = false;

    public string $uas_class = '';

    public string $manufacturer_name = '';

    public string $model = '';

    public string $drone_serial_number = '';

    public string $controller_serial_number = '';

    public string $registration_number = '';

    public bool $registration_not_applicable = false;

    public string $mtom_weight = '';

    public string $remote_id_number = '';

    public bool $remote_id_not_applicable = false;

    public string $class_marking = '';

    public string $band_frequency = '';

    public string $color = '';

    public string $payload = '';

    public bool $payload_not_applicable = false;

    public string $vhf_equipment = '';

    public bool $vhf_not_applicable = false;

    public string $emergency_equipment = '';

    public bool $emergency_not_applicable = false;

    public string $insurance_policy_number = '';

    public string $insurance_valid_until = '';

    public string $insurance_company_name = '';

    public string $aesa_registration_status = Dron::AESA_STATUS_NO;

    public $insurance_coverage_policy_upload = null;

    public function mount(): void
    {
        $this->showForm = $this->drones->isEmpty();
    }

    #[Computed]
    public function cliente()
    {
        return Auth::user()->cliente;
    }

    #[Computed]
    public function drones()
    {
        return $this->cliente
            ? $this->cliente->drones()->latest()->get()
            : collect();
    }

    public function startCreate(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function canDeleteDrones(): bool
    {
        return $this->drones->count() > 1;
    }

    public function canDeleteDron(int $dronId): bool
    {
        if (! $this->canDeleteDrones()) {
            return false;
        }

        return ! $this->cliente?->drones()->findOrFail($dronId)->operaciones()->exists();
    }

    public function edit(int $dronId): void
    {
        $dron = $this->cliente?->drones()->findOrFail($dronId);

        $this->editingDronId = $dron->id;
        $this->uas_class = $dron->uas_class;
        $this->manufacturer_name = $dron->manufacturer_name;
        $this->model = $dron->model;
        $this->drone_serial_number = $dron->drone_serial_number ?? '';
        $this->controller_serial_number = $dron->controller_serial_number;
        $this->registration_number = $dron->registration_number ?? '';
        $this->registration_not_applicable = (bool) $dron->registration_not_applicable;
        $this->mtom_weight = (string) $dron->mtom_weight;
        $this->remote_id_number = $dron->remote_id_number ?? '';
        $this->remote_id_not_applicable = (bool) $dron->remote_id_not_applicable;
        $this->class_marking = $dron->class_marking;
        $this->band_frequency = $dron->band_frequency;
        $this->color = $dron->color;
        $this->payload = $dron->payload ?? '';
        $this->payload_not_applicable = (bool) $dron->payload_not_applicable;
        $this->vhf_equipment = $dron->vhf_equipment ?? '';
        $this->vhf_not_applicable = (bool) $dron->vhf_not_applicable;
        $this->emergency_equipment = $dron->emergency_equipment ?? '';
        $this->emergency_not_applicable = (bool) $dron->emergency_not_applicable;
        $this->insurance_policy_number = $dron->insurance_policy_number;
        $this->insurance_valid_until = match (true) {
            $dron->insurance_valid_until instanceof \DateTimeInterface => $dron->insurance_valid_until->format('Y-m-d'),
            filled($dron->insurance_valid_until) => (string) $dron->insurance_valid_until,
            default => '',
        };
        $this->insurance_company_name = $dron->insurance_company_name;
        $this->aesa_registration_status = $dron->aesa_registration_status ?: Dron::AESA_STATUS_NO;
        $this->insurance_coverage_policy_upload = null;
        $this->showForm = true;
    }

    public function cancel(): void
    {
        $this->resetForm();
        $this->showForm = $this->drones->isEmpty();
    }

    public function toggleNotApplicable(string $field): void
    {
        match ($field) {
            'registration' => $this->registration_not_applicable = ! $this->registration_not_applicable,
            'remote_id' => $this->remote_id_not_applicable = ! $this->remote_id_not_applicable,
            'payload' => $this->payload_not_applicable = ! $this->payload_not_applicable,
            'vhf' => $this->vhf_not_applicable = ! $this->vhf_not_applicable,
            'emergency' => $this->emergency_not_applicable = ! $this->emergency_not_applicable,
            default => null,
        };

        if ($field === 'registration' && $this->registration_not_applicable) {
            $this->registration_number = '';
        }

        if ($field === 'remote_id' && $this->remote_id_not_applicable) {
            $this->remote_id_number = '';
        }

        if ($field === 'payload' && $this->payload_not_applicable) {
            $this->payload = '';
        }

        if ($field === 'vhf' && $this->vhf_not_applicable) {
            $this->vhf_equipment = '';
        }

        if ($field === 'emergency' && $this->emergency_not_applicable) {
            $this->emergency_equipment = '';
        }
    }

    public function updatedRegistrationNumber($value): void
    {
        if (filled($value)) {
            $this->registration_not_applicable = false;
        }
    }

    public function updatedRemoteIdNumber($value): void
    {
        if (filled($value)) {
            $this->remote_id_not_applicable = false;
        }
    }

    public function updatedPayload($value): void
    {
        if (filled($value)) {
            $this->payload_not_applicable = false;
        }
    }

    public function updatedVhfEquipment($value): void
    {
        if (filled($value)) {
            $this->vhf_not_applicable = false;
        }
    }

    public function updatedEmergencyEquipment($value): void
    {
        if (filled($value)) {
            $this->emergency_not_applicable = false;
        }
    }

    public function save(): void
    {
        $cliente = $this->cliente;

        abort_unless($cliente, 403);

        $currentDron = $this->editingDronId
            ? $cliente->drones()->findOrFail($this->editingDronId)
            : null;

        $validated = $this->validate([
            'uas_class' => ['required', Rule::in(array_keys(Dron::uasClassOptions()))],
            'manufacturer_name' => ['required', 'string', 'max:255'],
            'model' => ['required', 'string', 'max:255'],
            'drone_serial_number' => ['required', 'string', 'max:255'],
            'controller_serial_number' => ['required', 'string', 'max:255'],
            'registration_number' => [$this->registration_not_applicable ? 'nullable' : 'required', 'string', 'max:255'],
            'registration_not_applicable' => ['boolean'],
            'mtom_weight' => ['required', 'numeric', 'min:0'],
            'remote_id_number' => [$this->remote_id_not_applicable ? 'nullable' : 'required', 'string', 'max:255'],
            'remote_id_not_applicable' => ['boolean'],
            'class_marking' => ['required', Rule::in(array_keys(Dron::classMarkingOptions()))],
            'band_frequency' => ['required', 'string', 'max:255'],
            'color' => ['required', 'string', 'max:255'],
            'payload' => [$this->payload_not_applicable ? 'nullable' : 'required', 'string', 'max:1000'],
            'payload_not_applicable' => ['boolean'],
            'vhf_equipment' => [$this->vhf_not_applicable ? 'nullable' : 'required', 'string', 'max:255'],
            'vhf_not_applicable' => ['boolean'],
            'emergency_equipment' => [$this->emergency_not_applicable ? 'nullable' : 'required', 'string', 'max:255'],
            'emergency_not_applicable' => ['boolean'],
            'insurance_policy_number' => ['required', 'string', 'max:255'],
            'insurance_valid_until' => ['required', 'date'],
            'insurance_company_name' => ['required', 'string', 'max:255'],
            'insurance_coverage_policy_upload' => [
                $currentDron && filled($currentDron->insurance_coverage_policy_path) ? 'nullable' : 'required',
                'file',
                'mimes:pdf',
                'max:10240',
            ],
            'aesa_registration_status' => ['required', Rule::in(array_keys(Dron::aesaRegistrationOptions()))],
        ]);

        $payload = [
            ...$validated,
            'registration_number' => $this->registration_not_applicable ? null : $validated['registration_number'],
            'remote_id_number' => $this->remote_id_not_applicable ? null : $validated['remote_id_number'],
            'payload' => $this->payload_not_applicable ? null : $validated['payload'],
            'vhf_equipment' => $this->vhf_not_applicable ? null : $validated['vhf_equipment'],
            'emergency_equipment' => $this->emergency_not_applicable ? null : $validated['emergency_equipment'],
        ];

        unset($payload['insurance_coverage_policy_upload']);

        if ($this->editingDronId) {
            $dron = $currentDron;
            $dron->update($payload);
        } else {
            $dron = $cliente->drones()->create($payload);
        }

        if ($this->insurance_coverage_policy_upload) {
            $stored = $this->storeCoveragePolicy($dron, $this->insurance_coverage_policy_upload);

            $dron->update([
                'insurance_coverage_policy_path' => $stored,
                'insurance_coverage_policy_original_name' => $this->insurance_coverage_policy_upload->getClientOriginalName(),
            ]);
        }

        $this->resetForm();
        $this->showForm = false;
        $this->dispatch('dron-saved');
    }

    public function delete(int $dronId): void
    {
        if (! $this->canDeleteDron($dronId)) {
            return;
        }

        $dron = $this->cliente?->drones()->findOrFail($dronId);

        if (filled($dron?->insurance_coverage_policy_path)) {
            Storage::disk('public')->delete($dron->insurance_coverage_policy_path);
        }

        $dron?->delete();
        $this->resetForm();
        $this->showForm = $this->drones->isEmpty();
    }

    public function downloadCoveragePolicy(int $dronId)
    {
        $dron = $this->cliente?->drones()->findOrFail($dronId);

        abort_unless($dron && filled($dron->insurance_coverage_policy_path), 404);

        return Storage::disk('public')->download(
            $dron->insurance_coverage_policy_path,
            $dron->insurance_coverage_policy_original_name ?: basename($dron->insurance_coverage_policy_path)
        );
    }

    protected function storeCoveragePolicy(Dron $dron, $uploadedFile): string
    {
        if (filled($dron->insurance_coverage_policy_path)) {
            Storage::disk('public')->delete($dron->insurance_coverage_policy_path);
        }

        $folder = sprintf(
            'drones/cliente-%d/dron-%d-%s',
            $dron->cliente_id,
            $dron->id,
            Str::slug(trim(($dron->manufacturer_name ?? '').' '.($dron->model ?? '')) ?: 'dron')
        );

        $fileName = now()->format('YmdHis').'-poliza-cobertura.'.$uploadedFile->getClientOriginalExtension();

        return $uploadedFile->storeAs($folder, $fileName, 'public');
    }

    protected function resetForm(): void
    {
        $this->reset([
            'editingDronId',
            'uas_class',
            'manufacturer_name',
            'model',
            'drone_serial_number',
            'controller_serial_number',
            'registration_number',
            'registration_not_applicable',
            'mtom_weight',
            'remote_id_number',
            'remote_id_not_applicable',
            'class_marking',
            'band_frequency',
            'color',
            'payload',
            'payload_not_applicable',
            'vhf_equipment',
            'vhf_not_applicable',
            'emergency_equipment',
            'emergency_not_applicable',
            'insurance_policy_number',
            'insurance_valid_until',
            'insurance_company_name',
            'insurance_coverage_policy_upload',
            'aesa_registration_status',
        ]);

        $this->aesa_registration_status = Dron::AESA_STATUS_NO;
    }
}; ?>

<section class="portal-page">
    <x-pages::settings.layout heading="" subheading="">
        <div class="portal-hero portal-hero--sky">
            <div class="portal-hero__row">
                <div>
                    <p class="portal-hero__eyebrow text-sky-700 dark:text-sky-300">Portal cliente</p>
                    <h1 class="portal-hero__title">Mis drones</h1>
                </div>

                @if ($this->drones->isNotEmpty() && ! $showForm)
                    <flux:button variant="primary" wire:click="startCreate">
                        Anadir otro dron
                    </flux:button>
                @endif
            </div>
        </div>

        @if ($showForm)
            <div class="portal-form-shell">
                <div class="portal-form-header">
                    <div>
                        <h2 class="portal-form-title">
                            {{ $editingDronId ? 'Editar dron' : 'Registrar dron' }}
                        </h2>
                        <p class="portal-form-text">
                            Introduce la informacion tecnica, la cobertura del seguro y el estado del registro en AESA.
                        </p>
                    </div>

                    @if ($this->drones->isNotEmpty())
                        <flux:button variant="ghost" wire:click="cancel">Volver al listado</flux:button>
                    @endif
                </div>

                <form wire:submit="save" class="portal-form-sections">
                    <div class="portal-form-section">
                        <h3 class="portal-form-section__title">Datos del dron</h3>
                        <p class="portal-form-section__text">
                            Completa la identificacion tecnica, los equipos disponibles y la informacion basica del dron.
                        </p>

                        <div class="mt-6 grid gap-6 md:grid-cols-2">
                            <div>
                                <label class="mb-2 block text-sm font-medium text-neutral-900 dark:text-white">Clase de UAS</label>
                                <select wire:model="uas_class" data-flux-control class="block w-full rounded-lg border border-zinc-200 border-b-zinc-300/80 bg-white p-3 text-sm text-zinc-700 shadow-xs dark:border-white/10 dark:bg-white/10 dark:text-zinc-300">
                                    <option value="">Selecciona una clase</option>
                                    @foreach (Dron::uasClassOptions() as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('uas_class') <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                            </div>

                            <flux:input wire:model="manufacturer_name" label="Nombre del fabricante" type="text" required />
                        </div>

                        <div class="mt-6 grid gap-6 md:grid-cols-2">
                            <flux:input wire:model="model" label="Modelo" type="text" required />
                            <flux:input wire:model="drone_serial_number" label="Numero de serie del dron" type="text" required />
                        </div>

                        <div class="mt-6 grid gap-6 md:grid-cols-2">
                            <flux:input wire:model="controller_serial_number" label="Numero de serie de la controladora" type="text" required />
                            <flux:input wire:model="mtom_weight" label="Peso MTOM (g)" type="number" step="0.01" min="0" required />
                        </div>

                        <div class="mt-6 grid gap-6 md:grid-cols-2">
                            <div class="portal-inline-field">
                                <div class="portal-inline-field__input">
                                    <flux:input wire:model.live.debounce.150ms="registration_number" label="Matricula" type="text" />
                                </div>
                                <button type="button" wire:click="toggleNotApplicable('registration')" class="portal-na-button {{ $registration_not_applicable ? 'portal-na-button--active' : '' }}">
                                    No aplica
                                </button>
                            </div>

                            <div class="portal-inline-field">
                                <div class="portal-inline-field__input">
                                    <flux:input wire:model.live.debounce.150ms="remote_id_number" label="Numero de ID remoto" type="text" />
                                </div>
                                <button type="button" wire:click="toggleNotApplicable('remote_id')" class="portal-na-button {{ $remote_id_not_applicable ? 'portal-na-button--active' : '' }}">
                                    No aplica
                                </button>
                            </div>
                        </div>

                        <div class="mt-6 grid gap-6 md:grid-cols-2">
                            <div>
                                <label class="mb-2 block text-sm font-medium text-neutral-900 dark:text-white">Marcado de clase</label>
                                <select wire:model="class_marking" data-flux-control class="block w-full rounded-lg border border-zinc-200 border-b-zinc-300/80 bg-white p-3 text-sm text-zinc-700 shadow-xs dark:border-white/10 dark:bg-white/10 dark:text-zinc-300">
                                    <option value="">Selecciona un marcado</option>
                                    @foreach (Dron::classMarkingOptions() as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('class_marking') <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                            </div>

                            <flux:input wire:model="band_frequency" label="Banda y frecuencia" type="text" placeholder="2,4 y 5,8 GHz" required />
                        </div>

                        <div class="mt-6 grid gap-6 md:grid-cols-2">
                            <flux:input wire:model="color" label="Color" type="text" required />
                        </div>

                        <div class="mt-6 grid gap-6">
                            <div class="portal-inline-field">
                                <div class="portal-inline-field__input">
                                    <flux:textarea wire:model.live.debounce.150ms="payload" label="Carga de pago (Camera, microfono, objetos, dispositivos)" rows="4" />
                                </div>
                                <button type="button" wire:click="toggleNotApplicable('payload')" class="portal-na-button {{ $payload_not_applicable ? 'portal-na-button--active' : '' }}">
                                    No aplica
                                </button>
                            </div>
                        </div>

                        <div class="mt-6 grid gap-6 md:grid-cols-2">
                            <div class="portal-inline-field">
                                <div class="portal-inline-field__input">
                                    <flux:input wire:model.live.debounce.150ms="vhf_equipment" label="Equipo de comunicaciones VHF" type="text" />
                                </div>
                                <button type="button" wire:click="toggleNotApplicable('vhf')" class="portal-na-button {{ $vhf_not_applicable ? 'portal-na-button--active' : '' }}">
                                    No aplica
                                </button>
                            </div>

                            <div class="portal-inline-field">
                                <div class="portal-inline-field__input">
                                    <flux:input wire:model.live.debounce.150ms="emergency_equipment" label="Equipo de emergencia" type="text" />
                                </div>
                                <button type="button" wire:click="toggleNotApplicable('emergency')" class="portal-na-button {{ $emergency_not_applicable ? 'portal-na-button--active' : '' }}">
                                    No aplica
                                </button>
                            </div>
                        </div>
                    </div>

                    {{--
                    <div class="portal-form-section">
                        <h3 class="portal-form-section__title">Seguro</h3>
                        <p class="portal-form-section__text">
                            Añade la vigencia del seguro y la entidad aseguradora del dron.
                        </p>

                        <div class="mt-6 grid gap-6 md:grid-cols-2">
                            <flux:input wire:model="insurance_policy_number" label="Numero de poliza seguro" type="text" required />
                            <flux:input wire:model="insurance_valid_until" label="Fecha de validez" type="date" required />
                        </div>

                        <div class="mt-6 grid gap-6 md:grid-cols-2">
                            <flux:input wire:model="insurance_company_name" label="Nombre de la entidad aseguradora" type="text" required />
                        </div>
                    </div>
                    --}}
                    <div class="portal-form-section">
                        <h3 class="portal-form-section__title">Seguro</h3>
                        <p class="portal-form-section__text">
                            Anade la vigencia del seguro, la entidad aseguradora y la poliza de cobertura en PDF.
                        </p>

                        <div class="mt-6 grid gap-6 md:grid-cols-2">
                            <flux:input wire:model="insurance_policy_number" label="Numero de poliza seguro" type="text" required />
                            <flux:input wire:model="insurance_valid_until" label="Fecha de validez" type="date" required />
                        </div>

                        <div class="mt-6 grid gap-6 md:grid-cols-2">
                            <flux:input wire:model="insurance_company_name" label="Nombre de la entidad aseguradora" type="text" required />
                        </div>

                        <div class="portal-upload-card">
                            <label class="block text-sm font-medium text-neutral-900 dark:text-white">Anadir poliza de cobertura</label>
                            @php
                                $selectedCoveragePdf = is_object($insurance_coverage_policy_upload) && method_exists($insurance_coverage_policy_upload, 'getClientOriginalName')
                                    ? $insurance_coverage_policy_upload->getClientOriginalName()
                                    : null;
                                $currentCoverageName = $editingDronId
                                    ? ($this->cliente?->drones()->find($editingDronId)?->insurance_coverage_policy_original_name ?? null)
                                    : null;
                            @endphp
                            <input id="dron-coverage-policy-pdf" type="file" wire:model="insurance_coverage_policy_upload" accept=".pdf,application/pdf" class="hidden">
                            <div class="portal-upload-actions">
                                <label for="dron-coverage-policy-pdf" class="inline-flex h-10 cursor-pointer items-center justify-center rounded-lg bg-cyan-500 px-4 text-sm font-medium text-white shadow-sm transition hover:bg-cyan-600">
                                    Seleccionar PDF
                                </label>
                                @if ($selectedCoveragePdf)
                                    <span class="text-sm text-neutral-600 dark:text-neutral-300">{{ $selectedCoveragePdf }}</span>
                                @elseif ($currentCoverageName)
                                    <span class="text-sm text-neutral-600 dark:text-neutral-300">{{ $currentCoverageName }}</span>
                                @endif
                            </div>
                            @error('insurance_coverage_policy_upload') <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror

                            @if ($editingDronId && $this->cliente?->drones()->find($editingDronId)?->insurance_coverage_policy_path)
                                <div class="mt-4">
                                    <flux:button type="button" variant="ghost" wire:click="downloadCoveragePolicy({{ $editingDronId }})">
                                        Descargar poliza actual
                                    </flux:button>
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="portal-form-section">
                        <h3 class="portal-form-section__title">Dron registrado en AESA</h3>
                        <p class="portal-form-section__text">
                            Indica si este dron ya esta registrado en AESA o si el gestor tiene que encargarse del tramite.
                        </p>

                        <div class="portal-choice-grid">
                            @foreach (Dron::aesaRegistrationOptions() as $value => $label)
                                <label class="portal-choice-card">
                                    <div class="portal-choice-card__row">
                                        <input type="radio" wire:model.live="aesa_registration_status" value="{{ $value }}">
                                        <span>{{ $label }}</span>
                                    </div>
                                </label>
                            @endforeach
                        </div>
                        @error('aesa_registration_status') <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    </div>

                    <div class="portal-form-actions">
                        <flux:button variant="primary" type="submit">
                            {{ $editingDronId ? 'Guardar cambios' : 'Guardar dron' }}
                        </flux:button>

                        <x-action-message class="me-3" on="dron-saved">
                            Guardado.
                        </x-action-message>
                    </div>
                </form>
            </div>
        @else
            <div class="portal-record-list">
                @foreach ($this->drones as $dron)
                    <div class="portal-record-card">
                        <div class="portal-record-card__header">
                            <div>
                                <h2 class="portal-record-card__title">
                                    {{ $dron->manufacturer_name }} {{ $dron->model }}
                                </h2>

                                <div class="portal-record-card__meta">
                                    <p>Clase: {{ Dron::uasClassOptions()[$dron->uas_class] ?? $dron->uas_class }} - Matricula: {{ $dron->registrationLabel() }}</p>
                                    <p>Serie dron: {{ $dron->drone_serial_number ?: 'Sin definir' }} - ID remoto: {{ $dron->remoteIdLabel() }}</p>
                                    <p>MTOM: {{ $dron->mtom_weight }} g - AESA: {{ $dron->aesaRegistrationLabel() }}</p>
                                    <p>Seguro: {{ $dron->insurance_company_name }} - Poliza: {{ $dron->insurance_policy_number }}</p>
                                </div>
                            </div>

                            <div class="portal-record-card__actions">
                                <flux:button variant="ghost" wire:click="edit({{ $dron->id }})">
                                    Editar
                                </flux:button>
                                @if ($this->canDeleteDron($dron->id))
                                    <flux:button variant="danger" wire:click="delete({{ $dron->id }})">
                                        Borrar
                                    </flux:button>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </x-pages::settings.layout>
</section>
