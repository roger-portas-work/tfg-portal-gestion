<?php

use App\Models\OperadoraProfile;
use App\Models\OperadoraRequirement;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Operadora')] class extends Component {
    use WithFileUploads;

    public string $first_name = '';

    public string $last_name = '';

    public string $second_last_name = '';

    public string $registration_number = '';

    public string $expiration_date = '';

    /** @var array<int, string> */
    public array $textInputs = [];

    /** @var array<int, mixed> */
    public array $pdfUploads = [];

    public function mount(): void
    {
        abort_unless($this->cliente?->isUnblocked(), 403);

        $this->cliente->ensureOperadoraSetup();
        $this->loadProfile();
        $this->syncRequirementInputs();
    }

    #[Computed]
    public function cliente()
    {
        return Auth::user()->cliente;
    }

    #[Computed]
    public function operadoraProfile(): ?OperadoraProfile
    {
        if (! $this->cliente) {
            return null;
        }

        return $this->cliente->operadoraProfile()->first();
    }

    #[Computed]
    public function requirements()
    {
        if (! $this->cliente) {
            return collect();
        }

        $this->cliente->ensureDefaultOperadoraRequirement();

        $statusOrder = [
            OperadoraRequirement::STATUS_PENDING => 0,
            OperadoraRequirement::STATUS_NEEDS_CHANGES => 1,
            OperadoraRequirement::STATUS_IN_REVIEW => 2,
            OperadoraRequirement::STATUS_APPROVED => 3,
        ];

        return $this->cliente
            ->operadoraRequirements()
            ->get()
            ->sortBy(fn (OperadoraRequirement $requirement): string => sprintf(
                '%d-%d-%d-%010d',
                $requirement->is_system_default ? 0 : 1,
                $requirement->is_required ? 0 : 1,
                $statusOrder[$requirement->status ?? OperadoraRequirement::STATUS_PENDING] ?? 9,
                9999999999 - $requirement->id
            ))
            ->values();
    }

    #[Computed]
    public function completedCount(): int
    {
        return $this->requirements
            ->filter(fn (OperadoraRequirement $requirement): bool => $requirement->status === OperadoraRequirement::STATUS_APPROVED)
            ->count();
    }

    #[Computed]
    public function pendingCount(): int
    {
        return $this->requirements
            ->filter(fn (OperadoraRequirement $requirement): bool => in_array($requirement->status, [
                OperadoraRequirement::STATUS_PENDING,
                OperadoraRequirement::STATUS_NEEDS_CHANGES,
            ], true))
            ->count();
    }

    #[Computed]
    public function inReviewCount(): int
    {
        return $this->requirements
            ->filter(fn (OperadoraRequirement $requirement): bool => $requirement->status === OperadoraRequirement::STATUS_IN_REVIEW)
            ->count();
    }

    public function saveProfile(): void
    {
        abort_unless($this->cliente, 403);

        $validated = $this->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'second_last_name' => ['nullable', 'string', 'max:255'],
            'registration_number' => ['required', 'string', 'max:255'],
            'expiration_date' => ['required', 'date'],
        ]);

        $profile = $this->cliente->ensureOperadoraProfile();

        $profile->update([
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'second_last_name' => $validated['second_last_name'] ?: null,
            'registration_number' => Str::upper(trim($validated['registration_number'])),
            'expiration_date' => $validated['expiration_date'],
        ]);

        $this->dispatch('operadora-profile-saved');
    }

    public function saveTextRequirement(int $requirementId): void
    {
        $requirement = $this->cliente?->operadoraRequirements()->findOrFail($requirementId);

        abort_unless($requirement->input_type === OperadoraRequirement::TYPE_TEXT, 403);

        $this->validate([
            "textInputs.$requirementId" => ['required', 'string'],
        ]);

        $requirement->update([
            'text_value' => $this->textInputs[$requirementId],
            'status' => OperadoraRequirement::STATUS_IN_REVIEW,
            'review_notes' => null,
            'reviewed_at' => null,
            'submitted_at' => now(),
        ]);

        $this->dispatch('operadora-saved');
    }

    public function savePdfRequirement(int $requirementId): void
    {
        $requirement = $this->cliente?->operadoraRequirements()->findOrFail($requirementId);

        abort_unless($requirement->input_type === OperadoraRequirement::TYPE_PDF, 403);

        $this->validate([
            "pdfUploads.$requirementId" => ['required', 'file', 'mimes:pdf', 'max:10240'],
        ]);

        $uploadedFile = $this->pdfUploads[$requirementId];
        $requirementFolder = sprintf('operadora/cliente-%d/%s', $this->cliente->id, Str::slug($requirement->name ?: 'requisito'));

        if (filled($requirement->file_path)) {
            Storage::disk('public')->delete($requirement->file_path);
        }

        $storedFileName = now()->format('YmdHis').'-'.Str::slug(pathinfo($uploadedFile->getClientOriginalName(), PATHINFO_FILENAME));
        $path = $uploadedFile->storeAs($requirementFolder, $storedFileName.'.'.$uploadedFile->getClientOriginalExtension(), 'public');

        $requirement->update([
            'file_path' => $path,
            'original_file_name' => $uploadedFile->getClientOriginalName(),
            'mime_type' => $uploadedFile->getClientMimeType(),
            'file_size' => $uploadedFile->getSize(),
            'status' => OperadoraRequirement::STATUS_IN_REVIEW,
            'review_notes' => null,
            'reviewed_at' => null,
            'submitted_at' => now(),
        ]);

        unset($this->pdfUploads[$requirementId]);
        $this->dispatch('operadora-saved');
    }

    public function updated(string $property): void
    {
        if (! Str::startsWith($property, 'pdfUploads.')) {
            return;
        }

        $requirementId = (int) Str::after($property, 'pdfUploads.');

        if (! isset($this->pdfUploads[$requirementId]) || blank($this->pdfUploads[$requirementId])) {
            return;
        }

        $this->savePdfRequirement($requirementId);
    }

    public function downloadPdf(int $requirementId)
    {
        $requirement = $this->cliente?->operadoraRequirements()->findOrFail($requirementId);

        abort_unless(filled($requirement->file_path), 404);

        return Storage::disk('public')->download($requirement->file_path);
    }

    public function requirementStatusLabel(OperadoraRequirement $requirement): string
    {
        return match ($requirement->status) {
            OperadoraRequirement::STATUS_APPROVED => 'Aprobado',
            OperadoraRequirement::STATUS_IN_REVIEW => 'En revision',
            OperadoraRequirement::STATUS_NEEDS_CHANGES => 'Corregir',
            default => 'Pendiente',
        };
    }

    protected function loadProfile(): void
    {
        $profile = $this->operadoraProfile ?? $this->cliente?->ensureOperadoraProfile();

        if (! $profile) {
            return;
        }

        $looksAutoFilledFromCliente =
            blank($profile->registration_number)
            && blank($profile->expiration_date)
            && filled($this->cliente)
            && ($profile->first_name ?? '') === ($this->cliente->name ?? '')
            && ($profile->last_name ?? '') === ($this->cliente->last_name ?? '')
            && ($profile->second_last_name ?? '') === ($this->cliente->second_last_name ?? '');

        $this->first_name = $looksAutoFilledFromCliente ? '' : ($profile->first_name ?? '');
        $this->last_name = $looksAutoFilledFromCliente ? '' : ($profile->last_name ?? '');
        $this->second_last_name = $looksAutoFilledFromCliente ? '' : ($profile->second_last_name ?? '');
        $this->registration_number = $profile->registration_number ?? '';
        $this->expiration_date = match (true) {
            $profile->expiration_date instanceof \DateTimeInterface => $profile->expiration_date->format('Y-m-d'),
            filled($profile->expiration_date) => (string) $profile->expiration_date,
            default => '',
        };
    }

    protected function syncRequirementInputs(): void
    {
        foreach ($this->requirements as $requirement) {
            $this->textInputs[$requirement->id] = $requirement->text_value ?? '';
            $this->pdfUploads[$requirement->id] = null;
        }
    }
}; ?>

<section class="portal-page">
    <x-pages::settings.layout heading="" subheading="">
        <div class="portal-hero portal-hero--sky">
            <div class="portal-hero__row">
                <div>
                    <p class="portal-hero__eyebrow text-sky-700 dark:text-sky-300">Portal cliente</p>
                    <h1 class="portal-hero__title">Operadora</h1>
                    <p class="portal-hero__text">
                        Completa primero los datos del certificado operador y, debajo, entrega la documentacion que el gestor te solicite.
                    </p>
                </div>
            </div>

            <div class="mt-4 flex flex-wrap gap-3 text-sm">
                <span class="portal-badge portal-badge--danger">Pendientes: {{ $this->pendingCount }}</span>
                <span class="portal-badge portal-badge--amber">En revision: {{ $this->inReviewCount }}</span>
                <span class="portal-badge portal-badge--emerald">Completados: {{ $this->completedCount }}</span>
            </div>
        </div>

        <div class="portal-form-shell mt-6">
            <div class="portal-form-header">
                <div>
                    <h2 class="portal-form-title">Datos del certificado operador</h2>
                    <p class="portal-form-text">
                        Completa esta ficha con los mismos datos que aparecen en tu certificado.
                    </p>
                </div>
            </div>

            <form wire:submit="saveProfile" class="portal-form-sections">
                <div class="portal-form-section">
                    <div class="flex flex-wrap items-center gap-3">
                        <h3 class="portal-form-section__title">Ficha de operadora</h3>
                        <span class="portal-badge portal-badge--sky">Despues, sube aqui debajo el CERTIFICADO OPERADOR</span>
                    </div>

                    <div class="mt-6 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-4 text-sm leading-6 text-amber-900 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-200">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-amber-700 dark:text-amber-300">Importante</p>
                        <p class="mt-2">
                            Los datos que introduzcas deben coincidir exactamente con los que aparecen en el PDF <strong>CERTIFICADO OPERADOR</strong>.
                        </p>
                    </div>

                    <div class="mt-6 grid gap-6 md:grid-cols-3">
                        <flux:input wire:model="first_name" label="Nombre" type="text" required />
                        <flux:input wire:model="last_name" label="Apellido" type="text" required />
                        <flux:input wire:model="second_last_name" label="Segundo apellido" type="text" />
                    </div>

                    <div class="mt-6 grid gap-6 md:grid-cols-2">
                        <flux:input wire:model="registration_number" label="Numero de registro" type="text" required />
                        <flux:input wire:model="expiration_date" label="Fecha de caducidad" type="date" required />
                    </div>
                </div>

                <div class="portal-form-actions">
                    <flux:button variant="primary" type="submit">Guardar datos</flux:button>
                    <x-action-message class="me-3" on="operadora-profile-saved">Guardado.</x-action-message>
                </div>
            </form>
        </div>

        <div class="portal-form-shell mt-6">
            <div class="portal-form-header">
                <div>
                    <h2 class="portal-form-title">Requisitos operadora</h2>
                </div>
            </div>

            <div class="portal-status-legend">
                <span class="portal-status-legend__label">Leyenda</span>
                <span class="portal-badge portal-badge--danger">Pendiente</span>
                <span class="portal-badge portal-badge--amber">En revision</span>
                <span class="portal-badge portal-badge--sky">Corregir</span>
                <span class="portal-badge portal-badge--emerald">Aprobado</span>
            </div>

            <div class="portal-record-list">
                @forelse ($this->requirements as $requirement)
                    @php
                        $statusColor = match ($requirement->status) {
                            OperadoraRequirement::STATUS_APPROVED => 'portal-badge--emerald',
                            OperadoraRequirement::STATUS_IN_REVIEW => 'portal-badge--amber',
                            OperadoraRequirement::STATUS_NEEDS_CHANGES => 'portal-badge--sky',
                            default => 'portal-badge--danger',
                        };

                        $cardStatusClass = match ($requirement->status) {
                            OperadoraRequirement::STATUS_APPROVED => 'portal-record-card--approved',
                            OperadoraRequirement::STATUS_IN_REVIEW => 'portal-record-card--review',
                            OperadoraRequirement::STATUS_NEEDS_CHANGES => 'portal-record-card--changes',
                            default => 'portal-record-card--pending',
                        };

                        $typeColor = $requirement->input_type === OperadoraRequirement::TYPE_TEXT
                            ? 'portal-badge--indigo'
                            : 'portal-badge--slate';

                        $statusMessage = match ($requirement->status) {
                            OperadoraRequirement::STATUS_APPROVED => 'Este requisito ya ha sido validado por el gestor.',
                            OperadoraRequirement::STATUS_IN_REVIEW => 'Tu entrega ya esta enviada y pendiente de revision.',
                            OperadoraRequirement::STATUS_NEEDS_CHANGES => 'El gestor ha solicitado correcciones en este requisito.',
                            default => 'Todavia no has enviado este requisito.',
                        };
                    @endphp

                    <article class="portal-record-card {{ $cardStatusClass }}">
                        <div class="portal-record-card__header">
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="portal-record-card__title">{{ $requirement->name }}</h3>
                                    <span class="portal-badge {{ $statusColor }}">{{ $this->requirementStatusLabel($requirement) }}</span>
                                    <span class="portal-badge {{ $requirement->is_required ? 'portal-badge--neutral' : 'portal-badge--indigo' }}">
                                        {{ $requirement->is_required ? 'Obligatorio' : 'Opcional' }}
                                    </span>
                                    <span class="portal-badge {{ $typeColor }}">
                                        {{ OperadoraRequirement::inputTypeOptions()[$requirement->input_type] ?? $requirement->input_type }}
                                    </span>
                                </div>

                                <div class="portal-record-card__meta">
                                    <p>{{ $statusMessage }}</p>
                                    @if (filled($requirement->instructions))
                                        <p>Indicaciones: {{ $requirement->instructions }}</p>
                                    @endif
                                    @if ($requirement->submitted_at)
                                        <p>Ultima entrega: {{ \Illuminate\Support\Carbon::parse($requirement->submitted_at)->format('d/m/Y H:i') }}</p>
                                    @endif
                                </div>

                                @if ($requirement->status === OperadoraRequirement::STATUS_NEEDS_CHANGES && filled($requirement->review_notes))
                                    <p class="portal-record-card__text">
                                        Correccion solicitada: {{ $requirement->review_notes }}
                                    </p>
                                @endif
                            </div>
                        </div>

                        @if ($requirement->input_type === OperadoraRequirement::TYPE_TEXT)
                            <form wire:submit="saveTextRequirement({{ $requirement->id }})" class="mt-6 space-y-4">
                                <div class="portal-upload-card">
                                    <p class="text-sm font-semibold text-neutral-900 dark:text-white">Respuesta por texto</p>
                                    <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-300">
                                        Escribe la informacion solicitada y guardala para enviarla al gestor.
                                    </p>

                                    <div class="mt-4">
                                        <flux:textarea wire:model="textInputs.{{ $requirement->id }}" label="Tu respuesta" rows="5" />
                                    </div>
                                </div>

                                <div class="portal-form-actions">
                                    <flux:button variant="primary" type="submit">Guardar cambios</flux:button>
                                    <x-action-message class="me-3" on="operadora-saved">Guardado.</x-action-message>
                                </div>
                            </form>
                        @else
                            <div class="mt-6 space-y-4">
                                @if ($requirement->file_path)
                                    <div class="portal-upload-card portal-upload-card--compact">
                                        <p class="text-sm font-semibold text-neutral-900 dark:text-white">Documento actual</p>
                                        <p class="portal-upload-filename mt-1">
                                            {{ $requirement->original_file_name ?: 'PDF cargado correctamente.' }}
                                        </p>

                                        <div class="mt-4">
                                            <flux:button variant="filled" color="zinc" wire:click="downloadPdf({{ $requirement->id }})">Descargar actual</flux:button>
                                        </div>
                                    </div>
                                @endif

                                <form wire:submit="savePdfRequirement({{ $requirement->id }})" class="space-y-4" x-data="{ uploading: false, progress: 0 }" x-on:livewire-upload-start="uploading = true; progress = 0" x-on:livewire-upload-finish="uploading = false; progress = 100" x-on:livewire-upload-error="uploading = false" x-on:livewire-upload-cancel="uploading = false; progress = 0" x-on:livewire-upload-progress="progress = $event.detail.progress">
                                    <input id="operadora-pdf-{{ $requirement->id }}" type="file" wire:model="pdfUploads.{{ $requirement->id }}" accept=".pdf,application/pdf" class="hidden" />

                                    @php
                                        $selectedPdf = $pdfUploads[$requirement->id] ?? null;
                                        $selectedPdfName = is_object($selectedPdf) && method_exists($selectedPdf, 'getClientOriginalName')
                                            ? $selectedPdf->getClientOriginalName()
                                            : null;
                                    @endphp

                                    <div class="portal-upload-card portal-upload-card--compact">
                                        <p class="text-sm font-semibold text-neutral-900 dark:text-white">Subir documento PDF</p>
                                        <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-300">
                                            Selecciona el archivo y se enviara automaticamente a este requisito.
                                        </p>

                                        <div class="portal-upload-actions mt-4">
                                            <label for="operadora-pdf-{{ $requirement->id }}" class="inline-flex h-10 cursor-pointer items-center justify-center rounded-lg bg-cyan-500 px-4 text-sm font-medium text-white shadow-sm transition hover:bg-cyan-600">
                                                Seleccionar PDF
                                            </label>

                                            @if ($selectedPdfName)
                                                <span class="portal-upload-filename">{{ $selectedPdfName }}</span>
                                            @endif
                                        </div>

                                        <div x-cloak x-show="uploading" class="mt-4">
                                            <div class="flex items-center justify-between gap-4 text-sm">
                                                <p class="font-medium text-cyan-800 dark:text-cyan-200">Subiendo PDF...</p>
                                                <span class="font-semibold text-cyan-700 dark:text-cyan-300" x-text="`${progress}%`"></span>
                                            </div>

                                            <div class="mt-3 h-[10px] overflow-hidden rounded-full bg-cyan-100 dark:bg-cyan-900/40">
                                                <div class="h-full rounded-full bg-gradient-to-r from-cyan-500 to-sky-500 transition-all duration-300" x-bind:style="`width: ${progress}%`"></div>
                                            </div>
                                        </div>

                                        @error("pdfUploads.$requirement->id")
                                            <p class="mt-3 text-sm font-medium text-red-600 dark:text-red-400">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <x-action-message class="me-3" on="operadora-saved">Guardado.</x-action-message>
                                </form>
                            </div>
                        @endif
                    </article>
                @empty
                    <div class="portal-empty-state">
                        El gestor todavia no ha definido documentacion adicional para Operadora.
                    </div>
                @endforelse
            </div>
        </div>
    </x-pages::settings.layout>
</section>
