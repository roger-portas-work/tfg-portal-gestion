<?php

use App\Models\Piloto;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Mis pilotos')] class extends Component {
    use WithFileUploads;

    public ?int $editingPilotoId = null;

    public bool $showForm = false;

    public string $first_name = '';

    public string $last_name = '';

    public string $second_last_name = '';

    public string $dni_nie = '';

    public string $birth_date = '';

    public string $pilot_identification_number = '';

    public string $address = '';

    public string $country = '';

    public string $city = '';

    public string $province = '';

    public string $postal_code = '';

    public string $phone = '';

    public string $has_radiofonista_certificate = '0';

    public string $theoretical_certificate_level = '';

    public $radiofonista_certificate_upload = null;

    public $dni_front_upload = null;

    public $dni_back_upload = null;

    public $theoretical_certificate_upload = null;

    public $practical_certificate_upload = null;

    public function mount(): void
    {
        abort_unless($this->cliente?->isUnblocked(), 403);

        $this->showForm = $this->pilotos->isEmpty();
    }

    #[Computed]
    public function cliente()
    {
        return Auth::user()->cliente;
    }

    #[Computed]
    public function pilotos()
    {
        return $this->cliente
            ? $this->cliente->pilotos()->latest()->get()
            : collect();
    }

    public function startCreate(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $pilotoId): void
    {
        $piloto = $this->cliente?->pilotos()->findOrFail($pilotoId);

        $this->editingPilotoId = $piloto->id;
        $this->first_name = $piloto->first_name;
        $this->last_name = $piloto->last_name;
        $this->second_last_name = $piloto->second_last_name ?? '';
        $this->dni_nie = $piloto->dni_nie;
        $this->birth_date = match (true) {
            $piloto->birth_date instanceof \DateTimeInterface => $piloto->birth_date->format('Y-m-d'),
            filled($piloto->birth_date) => (string) $piloto->birth_date,
            default => '',
        };
        $this->pilot_identification_number = $piloto->pilot_identification_number;
        $this->address = $piloto->address;
        $this->country = $piloto->country;
        $this->city = $piloto->city;
        $this->province = $piloto->province;
        $this->postal_code = $piloto->postal_code;
        $this->phone = $piloto->phone;
        $this->has_radiofonista_certificate = $piloto->has_radiofonista_certificate ? '1' : '0';
        $this->theoretical_certificate_level = $piloto->theoretical_certificate_level;
        $this->radiofonista_certificate_upload = null;
        $this->dni_front_upload = null;
        $this->dni_back_upload = null;
        $this->theoretical_certificate_upload = null;
        $this->practical_certificate_upload = null;
        $this->showForm = true;
    }

    public function cancel(): void
    {
        $this->resetForm();
        $this->showForm = $this->pilotos->isEmpty();
    }

    public function save(): void
    {
        $cliente = $this->cliente;

        abort_unless($cliente, 403);

        $piloto = $this->currentPiloto();
        $hasRadiofonista = $this->hasRadiofonistaCertificate();
        $requiresPracticalCertificate = $this->requiresPracticalCertificate();
        $this->dni_nie = Str::upper(trim($this->dni_nie));

        $validated = $this->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'second_last_name' => ['nullable', 'string', 'max:255'],
            'dni_nie' => [
                'required',
                'string',
                'max:50',
                Rule::unique('pilotos', 'dni_nie')
                    ->where(fn ($query) => $query->where('cliente_id', $cliente->id))
                    ->ignore($piloto?->id),
            ],
            'birth_date' => ['required', 'date'],
            'pilot_identification_number' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:255'],
            'country' => ['required', 'string', 'max:120'],
            'city' => ['required', 'string', 'max:120'],
            'province' => ['required', 'string', 'max:120'],
            'postal_code' => ['required', 'string', 'max:20'],
            'phone' => ['required', 'string', 'max:30'],
            'has_radiofonista_certificate' => ['required', Rule::in(['0', '1'])],
            'theoretical_certificate_level' => ['required', Rule::in(array_keys(Piloto::theoreticalCertificateOptions()))],
            'radiofonista_certificate_upload' => $this->pdfRule($hasRadiofonista && blank($piloto?->radiofonista_certificate_path)),
            'dni_front_upload' => $this->pdfRule(blank($piloto?->dni_front_path)),
            'dni_back_upload' => $this->pdfRule(blank($piloto?->dni_back_path)),
            'theoretical_certificate_upload' => $this->pdfRule(blank($piloto?->theoretical_certificate_path)),
            'practical_certificate_upload' => $this->pdfRule($requiresPracticalCertificate && blank($piloto?->practical_certificate_path)),
        ], [
            'dni_nie.unique' => 'Ya existe un piloto con este DNI o NIE en tu expediente.',
        ]);

        $pilotoData = [
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'second_last_name' => $validated['second_last_name'] ?: null,
            'dni_nie' => $validated['dni_nie'],
            'birth_date' => $validated['birth_date'],
            'pilot_identification_number' => $validated['pilot_identification_number'],
            'address' => $validated['address'],
            'country' => $validated['country'],
            'city' => $validated['city'],
            'province' => $validated['province'],
            'postal_code' => $validated['postal_code'],
            'phone' => $validated['phone'],
            'has_radiofonista_certificate' => $hasRadiofonista,
            'theoretical_certificate_level' => $validated['theoretical_certificate_level'],
        ];

        if ($piloto) {
            $piloto->update($pilotoData);
        } else {
            $piloto = $cliente->pilotos()->create($pilotoData);
        }

        $documentUpdates = [];

        if ($hasRadiofonista) {
            if ($this->radiofonista_certificate_upload) {
                $documentUpdates['radiofonista_certificate_path'] = $this->storeDocument(
                    $piloto,
                    'radiofonista_certificate_path',
                    $this->radiofonista_certificate_upload,
                    'certificado-radiofonista'
                );
            }
        } else {
            $this->deleteStoredDocument($piloto, 'radiofonista_certificate_path');
            $documentUpdates['radiofonista_certificate_path'] = null;
        }

        if ($this->dni_front_upload) {
            $documentUpdates['dni_front_path'] = $this->storeDocument($piloto, 'dni_front_path', $this->dni_front_upload, 'dni-frontal');
        }

        if ($this->dni_back_upload) {
            $documentUpdates['dni_back_path'] = $this->storeDocument($piloto, 'dni_back_path', $this->dni_back_upload, 'dni-trasero');
        }

        if ($this->theoretical_certificate_upload) {
            $documentUpdates['theoretical_certificate_path'] = $this->storeDocument(
                $piloto,
                'theoretical_certificate_path',
                $this->theoretical_certificate_upload,
                'certificado-teorico'
            );
        }

        if ($requiresPracticalCertificate) {
            if ($this->practical_certificate_upload) {
                $documentUpdates['practical_certificate_path'] = $this->storeDocument(
                    $piloto,
                    'practical_certificate_path',
                    $this->practical_certificate_upload,
                    'certificado-practico'
                );
            }
        } else {
            $this->deleteStoredDocument($piloto, 'practical_certificate_path');
            $documentUpdates['practical_certificate_path'] = null;
        }

        if ($documentUpdates !== []) {
            $piloto->update($documentUpdates);
        }

        $this->resetForm();
        $this->showForm = false;
        $this->dispatch('piloto-saved');
    }

    public function delete(int $pilotoId): void
    {
        $piloto = $this->cliente?->pilotos()->findOrFail($pilotoId);

        if ($piloto->operaciones()->exists()) {
            return;
        }

        foreach ($this->documentFields() as $field) {
            $this->deleteStoredDocument($piloto, $field);
        }

        $piloto->delete();
        $this->resetForm();
        $this->showForm = $this->pilotos->isEmpty();
    }

    public function downloadDocument(int $pilotoId, string $field)
    {
        abort_unless(in_array($field, $this->documentFields(), true), 404);

        $piloto = $this->cliente?->pilotos()->findOrFail($pilotoId);
        $path = $piloto?->{$field};

        abort_unless(filled($path), 404);

        return Storage::disk('public')->download($path);
    }

    public function currentPiloto(): ?Piloto
    {
        if (! $this->editingPilotoId) {
            return null;
        }

        return $this->cliente?->pilotos()->findOrFail($this->editingPilotoId);
    }

    public function hasRadiofonistaCertificate(): bool
    {
        return $this->has_radiofonista_certificate === '1';
    }

    public function requiresPracticalCertificate(): bool
    {
        return $this->theoretical_certificate_level === Piloto::THEORY_STS;
    }

    /**
     * @return array<int, string>
     */
    protected function pdfRule(bool $required): array
    {
        return [
            $required ? 'required' : 'nullable',
            'file',
            'mimes:pdf',
            'max:10240',
        ];
    }

    protected function storeDocument(Piloto $piloto, string $field, $uploadedFile, string $documentKey): string
    {
        $this->deleteStoredDocument($piloto, $field);

        $folder = sprintf(
            'pilotos/cliente-%d/piloto-%d-%s',
            $piloto->cliente_id,
            $piloto->id,
            Str::slug($piloto->fullName() ?: 'piloto')
        );

        $fileName = now()->format('YmdHis').'-'.$documentKey.'.'.$uploadedFile->getClientOriginalExtension();

        return $uploadedFile->storeAs($folder, $fileName, 'public');
    }

    protected function deleteStoredDocument(Piloto $piloto, string $field): void
    {
        if (filled($piloto->{$field})) {
            Storage::disk('public')->delete($piloto->{$field});
        }
    }

    /**
     * @return array<int, string>
     */
    protected function documentFields(): array
    {
        return [
            'radiofonista_certificate_path',
            'dni_front_path',
            'dni_back_path',
            'theoretical_certificate_path',
            'practical_certificate_path',
        ];
    }

    protected function resetForm(): void
    {
        $this->reset([
            'editingPilotoId',
            'first_name',
            'last_name',
            'second_last_name',
            'dni_nie',
            'birth_date',
            'pilot_identification_number',
            'address',
            'country',
            'city',
            'province',
            'postal_code',
            'phone',
            'theoretical_certificate_level',
            'radiofonista_certificate_upload',
            'dni_front_upload',
            'dni_back_upload',
            'theoretical_certificate_upload',
            'practical_certificate_upload',
        ]);

        $this->has_radiofonista_certificate = '0';
    }
}; ?>

<section class="w-full">
    <x-pages::settings.layout heading="" subheading="">
        <div class="portal-hero portal-hero--indigo">
            <div class="portal-hero__row">
                <div>
                    <p class="portal-hero__eyebrow text-indigo-700 dark:text-indigo-300">Portal cliente</p>
                    <h1 class="portal-hero__title">Mis pilotos</h1>
                </div>

                @if ($this->pilotos->isNotEmpty() && ! $showForm)
                    <flux:button variant="primary" wire:click="startCreate">
                        Crear piloto
                    </flux:button>
                @endif
            </div>
        </div>

        @if ($showForm)
            @php
                $currentPiloto = $this->currentPiloto();
                $showRadiofonistaUpload = $this->hasRadiofonistaCertificate();
                $showCertificateDocuments = filled($theoretical_certificate_level);
                $requiresPracticalCertificate = $this->requiresPracticalCertificate();

                $documentButtons = [
                    'radiofonista_certificate_path' => 'Descargar certificado de radiofonista',
                    'dni_front_path' => 'Descargar DNI frontal',
                    'dni_back_path' => 'Descargar DNI trasero',
                    'theoretical_certificate_path' => 'Descargar certificado teorico',
                    'practical_certificate_path' => 'Descargar certificado practico',
                ];
            @endphp

            <div class="portal-form-shell">
                <div class="portal-form-header">
                    <div>
                        <h2 class="portal-form-title">
                            {{ $editingPilotoId ? 'Editar piloto' : 'Crear piloto' }}
                        </h2>
                        <p class="portal-form-text">
                            Completa los datos personales y la documentacion base del piloto.
                        </p>
                    </div>

                    @if ($this->pilotos->isNotEmpty())
                        <flux:button variant="ghost" wire:click="cancel">Volver al listado</flux:button>
                    @endif
                </div>

                <form wire:submit="save" class="portal-form-sections">
                    <div class="portal-form-section">
                        <h3 class="portal-form-section__title">Datos personales</h3>

                        <div class="mt-6 grid gap-6 md:grid-cols-3">
                            <flux:input wire:model="first_name" label="Nombre" type="text" required />
                            <flux:input wire:model="last_name" label="Apellido" type="text" required />
                            <flux:input wire:model="second_last_name" label="Segundo apellido" type="text" />
                        </div>

                        <div class="mt-6 grid gap-6 md:grid-cols-3">
                            <flux:input wire:model="dni_nie" label="DNI o NIE" type="text" required />
                            <flux:input wire:model="birth_date" label="Fecha de nacimiento" type="date" required />
                            <flux:input wire:model="pilot_identification_number" label="Numero de identificacion de piloto" type="text" placeholder="ESP-RP-XXXXXXXXXXXX" required />
                        </div>

                        <div class="mt-6 grid gap-6 md:grid-cols-2">
                            <flux:input wire:model="phone" label="Telefono" type="text" required />
                            <flux:input wire:model="address" label="Direccion completa" type="text" required />
                        </div>

                        <div class="mt-6 grid gap-6 md:grid-cols-3">
                            <flux:input wire:model="country" label="Pais" type="text" required />
                            <flux:input wire:model="city" label="Ciudad" type="text" required />
                            <flux:input wire:model="province" label="Provincia" type="text" required />
                            <flux:input wire:model="postal_code" label="Codigo postal" type="text" required />
                        </div>
                    </div>

                    <div class="portal-form-section">
                        <h3 class="portal-form-section__title">Certificado de radiofonista</h3>
                        <p class="portal-form-section__text">
                            Indica si el piloto dispone de este certificado. Si respondes que si, se habilitara la subida del PDF.
                        </p>

                        <div class="mt-6 flex flex-wrap gap-6">
                            <label class="flex items-center gap-2 text-sm font-medium text-neutral-700 dark:text-neutral-200">
                                <input type="radio" wire:model.live="has_radiofonista_certificate" value="1">
                                Si
                            </label>

                            <label class="flex items-center gap-2 text-sm font-medium text-neutral-700 dark:text-neutral-200">
                                <input type="radio" wire:model.live="has_radiofonista_certificate" value="0">
                                No
                            </label>
                        </div>

                        @if ($showRadiofonistaUpload)
                            <div class="portal-upload-card">
                                <label class="block text-sm font-medium text-neutral-900 dark:text-white">PDF del certificado de radiofonista</label>
                                @php
                                    $selectedRadiofonistaPdf = is_object($radiofonista_certificate_upload) && method_exists($radiofonista_certificate_upload, 'getClientOriginalName')
                                        ? $radiofonista_certificate_upload->getClientOriginalName()
                                        : null;
                                @endphp
                                <input id="piloto-radiofonista-pdf" type="file" wire:model="radiofonista_certificate_upload" accept=".pdf,application/pdf" class="hidden">
                                <div class="portal-upload-actions">
                                    <label for="piloto-radiofonista-pdf" class="inline-flex h-10 cursor-pointer items-center justify-center rounded-lg bg-cyan-500 px-4 text-sm font-medium text-white shadow-sm transition hover:bg-cyan-600">
                                        Seleccionar PDF
                                    </label>
                                    @if ($selectedRadiofonistaPdf)
                                        <span class="text-sm text-neutral-600 dark:text-neutral-300">{{ $selectedRadiofonistaPdf }}</span>
                                    @endif
                                </div>
                                @error('radiofonista_certificate_upload') <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror

                                @if ($currentPiloto?->radiofonista_certificate_path)
                                    <div class="mt-4">
                                        <flux:button type="button" variant="ghost" wire:click="downloadDocument({{ $currentPiloto->id }}, 'radiofonista_certificate_path')">
                                            {{ $documentButtons['radiofonista_certificate_path'] }}
                                        </flux:button>
                                    </div>
                                @endif
                            </div>
                        @endif
                    </div>

                    <div class="portal-form-section">
                        <h3 class="portal-form-section__title">Certificado de conocimientos teoricos</h3>
                        <p class="portal-form-section__text">
                            Selecciona el nivel del certificado. Segun tu eleccion, se mostraran los documentos obligatorios.
                        </p>

                        <div class="portal-choice-grid">
                            @foreach (Piloto::theoreticalCertificateOptions() as $value => $label)
                                <label class="portal-choice-card">
                                    <div class="portal-choice-card__row">
                                        <input type="radio" wire:model.live="theoretical_certificate_level" value="{{ $value }}">
                                        <span>{{ $label }}</span>
                                    </div>
                                </label>
                            @endforeach
                        </div>

                        @if ($showCertificateDocuments)
                            <div class="portal-upload-card">
                                <p class="text-sm font-semibold text-neutral-900 dark:text-white">Documentacion requerida</p>
                                <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-300">
                                    @if ($requiresPracticalCertificate)
                                        Para STS se pide DNI por delante y por detras, certificado teorico y certificado practico.
                                    @else
                                        Para {{ Piloto::theoreticalCertificateOptions()[$theoretical_certificate_level] ?? 'este nivel' }} se pide DNI por delante y por detras y certificado teorico.
                                    @endif
                                </p>

                                <div class="mt-6 grid gap-6 md:grid-cols-2">
                                    <div>
                                        <label class="block text-sm font-medium text-neutral-900 dark:text-white">DNI por delante en PDF</label>
                                        @php
                                            $selectedDniFrontPdf = is_object($dni_front_upload) && method_exists($dni_front_upload, 'getClientOriginalName')
                                                ? $dni_front_upload->getClientOriginalName()
                                                : null;
                                        @endphp
                                        <input id="piloto-dni-front-pdf" type="file" wire:model="dni_front_upload" accept=".pdf,application/pdf" class="hidden">
                                        <div class="portal-upload-actions">
                                            <label for="piloto-dni-front-pdf" class="inline-flex h-10 cursor-pointer items-center justify-center rounded-lg bg-cyan-500 px-4 text-sm font-medium text-white shadow-sm transition hover:bg-cyan-600">
                                                Seleccionar PDF
                                            </label>
                                            @if ($selectedDniFrontPdf)
                                                <span class="text-sm text-neutral-600 dark:text-neutral-300">{{ $selectedDniFrontPdf }}</span>
                                            @endif
                                        </div>
                                        @error('dni_front_upload') <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                                        @if ($currentPiloto?->dni_front_path)
                                            <div class="mt-3">
                                                <flux:button type="button" variant="ghost" wire:click="downloadDocument({{ $currentPiloto->id }}, 'dni_front_path')">
                                                    {{ $documentButtons['dni_front_path'] }}
                                                </flux:button>
                                            </div>
                                        @endif
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-neutral-900 dark:text-white">DNI por detras en PDF</label>
                                        @php
                                            $selectedDniBackPdf = is_object($dni_back_upload) && method_exists($dni_back_upload, 'getClientOriginalName')
                                                ? $dni_back_upload->getClientOriginalName()
                                                : null;
                                        @endphp
                                        <input id="piloto-dni-back-pdf" type="file" wire:model="dni_back_upload" accept=".pdf,application/pdf" class="hidden">
                                        <div class="portal-upload-actions">
                                            <label for="piloto-dni-back-pdf" class="inline-flex h-10 cursor-pointer items-center justify-center rounded-lg bg-cyan-500 px-4 text-sm font-medium text-white shadow-sm transition hover:bg-cyan-600">
                                                Seleccionar PDF
                                            </label>
                                            @if ($selectedDniBackPdf)
                                                <span class="text-sm text-neutral-600 dark:text-neutral-300">{{ $selectedDniBackPdf }}</span>
                                            @endif
                                        </div>
                                        @error('dni_back_upload') <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                                        @if ($currentPiloto?->dni_back_path)
                                            <div class="mt-3">
                                                <flux:button type="button" variant="ghost" wire:click="downloadDocument({{ $currentPiloto->id }}, 'dni_back_path')">
                                                    {{ $documentButtons['dni_back_path'] }}
                                                </flux:button>
                                            </div>
                                        @endif
                                    </div>
                                </div>

                                <div class="mt-6 grid gap-6 {{ $requiresPracticalCertificate ? 'md:grid-cols-2' : 'md:grid-cols-1' }}">
                                    <div>
                                        <label class="block text-sm font-medium text-neutral-900 dark:text-white">Certificado de conocimientos teoricos en PDF</label>
                                        @php
                                            $selectedTheoryPdf = is_object($theoretical_certificate_upload) && method_exists($theoretical_certificate_upload, 'getClientOriginalName')
                                                ? $theoretical_certificate_upload->getClientOriginalName()
                                                : null;
                                        @endphp
                                        <input id="piloto-theory-pdf" type="file" wire:model="theoretical_certificate_upload" accept=".pdf,application/pdf" class="hidden">
                                        <div class="portal-upload-actions">
                                            <label for="piloto-theory-pdf" class="inline-flex h-10 cursor-pointer items-center justify-center rounded-lg bg-cyan-500 px-4 text-sm font-medium text-white shadow-sm transition hover:bg-cyan-600">
                                                Seleccionar PDF
                                            </label>
                                            @if ($selectedTheoryPdf)
                                                <span class="text-sm text-neutral-600 dark:text-neutral-300">{{ $selectedTheoryPdf }}</span>
                                            @endif
                                        </div>
                                        @error('theoretical_certificate_upload') <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                                        @if ($currentPiloto?->theoretical_certificate_path)
                                            <div class="mt-3">
                                                <flux:button type="button" variant="ghost" wire:click="downloadDocument({{ $currentPiloto->id }}, 'theoretical_certificate_path')">
                                                    {{ $documentButtons['theoretical_certificate_path'] }}
                                                </flux:button>
                                            </div>
                                        @endif
                                    </div>

                                    @if ($requiresPracticalCertificate)
                                        <div>
                                            <label class="block text-sm font-medium text-neutral-900 dark:text-white">Certificado de conocimientos practicos en PDF</label>
                                            @php
                                                $selectedPracticalPdf = is_object($practical_certificate_upload) && method_exists($practical_certificate_upload, 'getClientOriginalName')
                                                    ? $practical_certificate_upload->getClientOriginalName()
                                                    : null;
                                            @endphp
                                            <input id="piloto-practical-pdf" type="file" wire:model="practical_certificate_upload" accept=".pdf,application/pdf" class="hidden">
                                            <div class="portal-upload-actions">
                                                <label for="piloto-practical-pdf" class="inline-flex h-10 cursor-pointer items-center justify-center rounded-lg bg-cyan-500 px-4 text-sm font-medium text-white shadow-sm transition hover:bg-cyan-600">
                                                    Seleccionar PDF
                                                </label>
                                                @if ($selectedPracticalPdf)
                                                    <span class="text-sm text-neutral-600 dark:text-neutral-300">{{ $selectedPracticalPdf }}</span>
                                                @endif
                                            </div>
                                            @error('practical_certificate_upload') <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                                            @if ($currentPiloto?->practical_certificate_path)
                                                <div class="mt-3">
                                                    <flux:button type="button" variant="ghost" wire:click="downloadDocument({{ $currentPiloto->id }}, 'practical_certificate_path')">
                                                        {{ $documentButtons['practical_certificate_path'] }}
                                                    </flux:button>
                                                </div>
                                            @endif
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endif
                    </div>

                    <div class="portal-form-actions">
                        <flux:button variant="primary" type="submit">
                            {{ $editingPilotoId ? 'Guardar cambios' : 'Guardar piloto' }}
                        </flux:button>

                        <x-action-message class="me-3" on="piloto-saved">
                            Guardado.
                        </x-action-message>
                    </div>
                </form>
            </div>
        @else
            @if (false)
            <div class="portal-record-list">
                @foreach ($this->pilotos as $piloto)
                    <div class="portal-record-card">
                        <div class="portal-record-card__header">
                            <div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <h2 class="portal-record-card__title">{{ $piloto->fullName() }}</h2>
                                    <span class="portal-badge portal-badge--indigo">
                                        {{ Piloto::theoreticalCertificateOptions()[$piloto->theoretical_certificate_level] ?? $piloto->theoretical_certificate_level }}
                                    </span>
                                    @if ($piloto->has_radiofonista_certificate)
                                        <span class="portal-badge portal-badge--sky">
                                            Radiofonista
                                        </span>
                                    @endif
                                </div>

                                <div class="portal-record-card__meta">
                                    DNI/NIE: {{ $piloto->dni_nie }} · Identificacion piloto: {{ $piloto->pilot_identification_number }}
                                </p>
                                <p>
                                    Certificacion maxima: {{ $piloto->maximum_pilot_certification }} · Telefono: {{ $piloto->phone }}
                                </p>
                                <p>
                                    {{ $piloto->city }}, {{ $piloto->province }} · {{ $piloto->country }}
                                </p>
                                </div>
                            </div>

                            <div class="portal-record-card__actions">
                                <flux:button variant="ghost" wire:click="edit({{ $piloto->id }})">
                                    Editar
                                </flux:button>
                                @if (! $piloto->operaciones()->exists())
                                    <flux:button variant="danger" wire:click="delete({{ $piloto->id }})">
                                        Borrar
                                    </flux:button>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
            @else
                <div class="portal-record-list">
                    @foreach ($this->pilotos as $piloto)
                        <div class="portal-record-card">
                            <div class="portal-record-card__header">
                                <div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h2 class="portal-record-card__title">{{ $piloto->fullName() }}</h2>
                                        <span class="portal-badge portal-badge--indigo">
                                            {{ Piloto::theoreticalCertificateOptions()[$piloto->theoretical_certificate_level] ?? $piloto->theoretical_certificate_level }}
                                        </span>
                                        @if ($piloto->has_radiofonista_certificate)
                                            <span class="portal-badge portal-badge--sky">
                                                Radiofonista
                                            </span>
                                        @endif
                                    </div>

                                    <div class="portal-record-card__meta">
                                        <p>
                                            DNI/NIE: {{ $piloto->dni_nie }} - Identificacion piloto: {{ $piloto->pilot_identification_number }}
                                        </p>
                                        <p>
                                            Telefono: {{ $piloto->phone }} - {{ $piloto->city }}, {{ $piloto->province }} - {{ $piloto->country }}
                                        </p>
                                    </div>
                                </div>

                                <div class="portal-record-card__actions">
                                    <flux:button variant="ghost" wire:click="edit({{ $piloto->id }})">
                                        Editar
                                    </flux:button>
                                    @if (! $piloto->operaciones()->exists())
                                        <flux:button variant="danger" wire:click="delete({{ $piloto->id }})">
                                            Borrar
                                        </flux:button>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        @endif
    </x-pages::settings.layout>
</section>
