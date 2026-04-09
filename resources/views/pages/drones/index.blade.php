<?php

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Mis drones')] class extends Component {
    public ?int $editingDronId = null;
    public bool $showForm = false;

    public string $uas_class = '';
    public string $manufacturer_name = '';
    public string $model = '';
    public string $controller_serial_number = '';
    public string $registration_number = '';
    public string $mtom_weight = '';
    public string $remote_id_number = '';
    public string $class_marking = '';
    public string $band_frequency = '';
    public string $color = '';
    public string $payload = '';
    public string $vhf_equipment = '';
    public string $emergency_equipment = '';
    public string $insurance_policy_number = '';
    public string $insurance_valid_until = '';
    public string $insurance_company_name = '';

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
        // El portal cliente siempre debe conservar al menos un dron base
        // una vez que el usuario ya ha salido del alta inicial.
        return $this->drones->count() > 1;
    }

    public function edit(int $dronId): void
    {
        $dron = $this->cliente?->drones()->findOrFail($dronId);

        $this->editingDronId = $dron->id;
        $this->uas_class = $dron->uas_class;
        $this->manufacturer_name = $dron->manufacturer_name;
        $this->model = $dron->model;
        $this->controller_serial_number = $dron->controller_serial_number;
        $this->registration_number = $dron->registration_number;
        $this->mtom_weight = (string) $dron->mtom_weight;
        $this->remote_id_number = $dron->remote_id_number;
        $this->class_marking = $dron->class_marking;
        $this->band_frequency = $dron->band_frequency;
        $this->color = $dron->color;
        $this->payload = $dron->payload ?? '';
        $this->vhf_equipment = $dron->vhf_equipment ?? '';
        $this->emergency_equipment = $dron->emergency_equipment ?? '';
        $this->insurance_policy_number = $dron->insurance_policy_number;
        $this->insurance_valid_until = match (true) {
            $dron->insurance_valid_until instanceof \DateTimeInterface => $dron->insurance_valid_until->format('Y-m-d'),
            filled($dron->insurance_valid_until) => (string) $dron->insurance_valid_until,
            default => '',
        };
        $this->insurance_company_name = $dron->insurance_company_name;
        $this->showForm = true;
    }

    public function cancel(): void
    {
        $this->resetForm();
        $this->showForm = $this->drones->isEmpty();
    }

    public function save(): void
    {
        $cliente = $this->cliente;

        abort_unless($cliente, 403);

        $validated = $this->validate([
            'uas_class' => ['required', 'string', 'max:255'],
            'manufacturer_name' => ['required', 'string', 'max:255'],
            'model' => ['required', 'string', 'max:255'],
            'controller_serial_number' => ['required', 'string', 'max:255'],
            'registration_number' => ['required', 'string', 'max:255'],
            'mtom_weight' => ['required', 'numeric', 'min:0'],
            'remote_id_number' => ['required', 'string', 'max:255'],
            'class_marking' => ['required', 'string', 'max:255'],
            'band_frequency' => ['required', 'string', 'max:255'],
            'color' => ['required', 'string', 'max:255'],
            'payload' => ['nullable', 'string', 'max:255'],
            'vhf_equipment' => ['nullable', 'string', 'max:255'],
            'emergency_equipment' => ['nullable', 'string', 'max:255'],
            'insurance_policy_number' => ['required', 'string', 'max:255'],
            'insurance_valid_until' => ['required', 'date'],
            'insurance_company_name' => ['required', 'string', 'max:255'],
        ]);

        if ($this->editingDronId) {
            $cliente->drones()->findOrFail($this->editingDronId)->update($validated);
        } else {
            $cliente->drones()->create($validated);
        }

        $this->resetForm();
        $this->showForm = false;
        $this->dispatch('dron-saved');
    }

    public function delete(int $dronId): void
    {
        if (! $this->canDeleteDrones()) {
            return;
        }

        $this->cliente?->drones()->findOrFail($dronId)?->delete();
        $this->resetForm();
        $this->showForm = $this->drones->isEmpty();
    }

    protected function resetForm(): void
    {
        $this->reset([
            'editingDronId',
            'uas_class',
            'manufacturer_name',
            'model',
            'controller_serial_number',
            'registration_number',
            'mtom_weight',
            'remote_id_number',
            'class_marking',
            'band_frequency',
            'color',
            'payload',
            'vhf_equipment',
            'emergency_equipment',
            'insurance_policy_number',
            'insurance_valid_until',
            'insurance_company_name',
        ]);
    }
}; ?>

<section class="w-full">
    <x-pages::settings.layout heading="" subheading="">
        <div class="rounded-3xl border border-sky-200 bg-gradient-to-br from-sky-50 via-white to-cyan-50 p-6 shadow-sm dark:border-sky-800/60 dark:from-sky-950/30 dark:via-neutral-900 dark:to-cyan-950/30">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-sm uppercase tracking-[0.25em] text-sky-700 dark:text-sky-300">Portal cliente</p>
                    <h1 class="mt-3 text-3xl font-semibold text-neutral-900 dark:text-white">Mis drones</h1>
                </div>

                @if ($this->drones->isNotEmpty() && ! $showForm)
                    <flux:button variant="primary" wire:click="startCreate">
                        Anadir otro dron
                    </flux:button>
                @endif
            </div>
        </div>

        @if ($showForm)
            <div class="mt-6 rounded-3xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <h2 class="text-xl font-semibold text-neutral-900 dark:text-white">
                            {{ $editingDronId ? 'Editar dron' : 'Registrar dron' }}
                        </h2>
                        <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-300">
                            Introduce la informacion principal del dron y de su seguro.
                        </p>
                    </div>

                    @if ($this->drones->isNotEmpty())
                        <flux:button variant="ghost" wire:click="cancel">Volver al listado</flux:button>
                    @endif
                </div>

                <form wire:submit="save" class="mt-6 space-y-6">
                    <div class="grid gap-6 md:grid-cols-2">
                        <flux:input wire:model="uas_class" label="Clase de UAS" type="text" required />
                        <flux:input wire:model="manufacturer_name" label="Nombre del fabricante" type="text" required />
                    </div>

                    <div class="grid gap-6 md:grid-cols-2">
                        <flux:input wire:model="model" label="Modelo" type="text" required />
                        <flux:input wire:model="controller_serial_number" label="Número de serie de la controladora" type="text" required />
                    </div>

                    <div class="grid gap-6 md:grid-cols-2">
                        <flux:input wire:model="registration_number" label="Matrícula" type="text" required />
                        <flux:input wire:model="mtom_weight" label="Peso MTOM" type="number" step="0.01" required />
                    </div>

                    <div class="grid gap-6 md:grid-cols-2">
                        <flux:input wire:model="remote_id_number" label="Número de ID remoto" type="text" required />
                        <flux:input wire:model="class_marking" label="Marcado de clase" type="text" required />
                    </div>

                    <div class="grid gap-6 md:grid-cols-2">
                        <flux:input wire:model="band_frequency" label="Banda y frecuencia" type="text" required />
                        <flux:input wire:model="color" label="Color" type="text" required />
                    </div>

                    <div class="grid gap-6 md:grid-cols-2">
                        <flux:input wire:model="payload" label="Carga de pago" type="text" />
                        <flux:input wire:model="vhf_equipment" label="Equip VHF" type="text" />
                    </div>

                    <div class="grid gap-6 md:grid-cols-2">
                        <flux:input wire:model="emergency_equipment" label="Equipo de emergencia" type="text" />
                        <flux:input wire:model="insurance_policy_number" label="Número de póliza del seguro" type="text" required />
                    </div>

                    <div class="grid gap-6 md:grid-cols-2">
                        <flux:input wire:model="insurance_valid_until" label="Fecha de validez" type="date" required />
                        <flux:input wire:model="insurance_company_name" label="Nombre de la entidad aseguradora" type="text" required />
                    </div>

                    <div class="flex items-center gap-4">
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
            <div class="mt-6 grid gap-4">
                @foreach ($this->drones as $dron)
                    <div class="rounded-3xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <h2 class="text-xl font-semibold text-neutral-900 dark:text-white">
                                    {{ $dron->manufacturer_name }} {{ $dron->model }}
                                </h2>
                                <p class="mt-2 text-sm text-neutral-600 dark:text-neutral-300">
                                    Matricula: {{ $dron->registration_number }} · Clase: {{ $dron->uas_class }}
                                </p>
                                <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-300">
                                    ID remoto: {{ $dron->remote_id_number }} · MTOM: {{ $dron->mtom_weight }} kg
                                </p>
                                <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-300">
                                    Seguro: {{ $dron->insurance_company_name }} · Poliza: {{ $dron->insurance_policy_number }}
                                </p>
                            </div>

                            <div class="flex gap-2">
                                <flux:button variant="ghost" wire:click="edit({{ $dron->id }})">
                                    Editar
                                </flux:button>
                                @if ($this->canDeleteDrones())
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
