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

    public string $requirementFilter = 'all';

    public string $requirementTypeFilter = 'all';

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
            OperadoraRequirement::STATUS_APPROVED => 0,
            OperadoraRequirement::STATUS_NEEDS_CHANGES => 1,
            OperadoraRequirement::STATUS_IN_REVIEW => 2,
            OperadoraRequirement::STATUS_PENDING => 3,
        ];

        return $this->cliente
            ->operadoraRequirements()
            ->get()
            ->sortBy(fn (OperadoraRequirement $requirement): string => sprintf(
                '%d-%d-%d-%010d',
                $statusOrder[$requirement->status ?? OperadoraRequirement::STATUS_PENDING] ?? 9,
                $requirement->is_required ? 0 : 1,
                $requirement->is_system_default ? 0 : 1,
                9999999999 - $requirement->id
            ))
            ->values();
    }

    #[Computed]
    public function displayedRequirements()
    {
        $requirements = match ($this->requirementFilter) {
            'pending' => $this->requirements->filter(fn (OperadoraRequirement $requirement): bool => $requirement->status === OperadoraRequirement::STATUS_PENDING)->values(),
            'review' => $this->requirements->filter(fn (OperadoraRequirement $requirement): bool => $requirement->status === OperadoraRequirement::STATUS_IN_REVIEW)->values(),
            'approved' => $this->requirements->filter(fn (OperadoraRequirement $requirement): bool => $requirement->status === OperadoraRequirement::STATUS_APPROVED)->values(),
            'changes' => $this->requirements->filter(fn (OperadoraRequirement $requirement): bool => $requirement->status === OperadoraRequirement::STATUS_NEEDS_CHANGES)->values(),
            default => $this->requirements,
        };

        return match ($this->requirementTypeFilter) {
            'required' => $requirements->filter(fn (OperadoraRequirement $requirement): bool => (bool) $requirement->is_required)->values(),
            'optional' => $requirements->filter(fn (OperadoraRequirement $requirement): bool => ! $requirement->is_required)->values(),
            default => $requirements,
        };
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
            ->filter(fn (OperadoraRequirement $requirement): bool => $requirement->status === OperadoraRequirement::STATUS_PENDING)
            ->count();
    }

    #[Computed]
    public function needsChangesCount(): int
    {
        return $this->requirements
            ->filter(fn (OperadoraRequirement $requirement): bool => $requirement->status === OperadoraRequirement::STATUS_NEEDS_CHANGES)
            ->count();
    }

    #[Computed]
    public function inReviewCount(): int
    {
        return $this->requirements
            ->filter(fn (OperadoraRequirement $requirement): bool => $requirement->status === OperadoraRequirement::STATUS_IN_REVIEW)
            ->count();
    }

    #[Computed]
    public function progressPercent(): int
    {
        $requiredRequirements = $this->requirements
            ->filter(fn (OperadoraRequirement $requirement): bool => (bool) $requirement->is_required);

        $totalRequired = $requiredRequirements->count();
        $completedRequired = $requiredRequirements
            ->filter(fn (OperadoraRequirement $requirement): bool => $requirement->status === OperadoraRequirement::STATUS_APPROVED)
            ->count();

        return $totalRequired > 0
            ? (int) round(($completedRequired / $totalRequired) * 100)
            : 0;
    }

    #[Computed]
    public function certificateRequirement(): ?OperadoraRequirement
    {
        return $this->requirements->first(
            fn (OperadoraRequirement $requirement): bool => (bool) $requirement->is_system_default
        );
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

    public function setRequirementFilter(string $filter): void
    {
        if (! in_array($filter, ['all', 'pending', 'review', 'approved', 'changes'], true)) {
            return;
        }

        $this->requirementFilter = $filter;
    }

    public function setRequirementTypeFilter(string $filter): void
    {
        if (! in_array($filter, ['all', 'required', 'optional'], true)) {
            return;
        }

        $this->requirementTypeFilter = $filter;
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

    public function requirementStatusMessage(OperadoraRequirement $requirement): string
    {
        return match ($requirement->status) {
            OperadoraRequirement::STATUS_APPROVED => 'Documento validado por el gestor.',
            OperadoraRequirement::STATUS_IN_REVIEW => 'Entrega enviada y pendiente de revision.',
            OperadoraRequirement::STATUS_NEEDS_CHANGES => 'El gestor ha solicitado correcciones.',
            default => 'Todavia no has enviado este requisito.',
        };
    }

    public function requirementTone(OperadoraRequirement $requirement): string
    {
        return match ($requirement->status) {
            OperadoraRequirement::STATUS_APPROVED => 'approved',
            OperadoraRequirement::STATUS_IN_REVIEW => 'review',
            OperadoraRequirement::STATUS_NEEDS_CHANGES => 'changes',
            default => 'pending',
        };
    }

    public function requirementIcon(OperadoraRequirement $requirement): string
    {
        return match ($requirement->status) {
            OperadoraRequirement::STATUS_APPROVED => 'document-check',
            default => 'document',
        };
    }

    public function formatDateValue(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('d/m/Y');
        }

        if (blank($value)) {
            return 'Sin definir';
        }

        try {
            return \Illuminate\Support\Carbon::parse((string) $value)->format('d/m/Y');
        } catch (\Throwable) {
            return (string) $value;
        }
    }

    public function formatFileSize(?int $bytes): string
    {
        if (! $bytes || $bytes <= 0) {
            return 'Tamano no disponible';
        }

        return $bytes >= 1024 * 1024
            ? number_format($bytes / (1024 * 1024), 1, ',', '.').' MB'
            : number_format($bytes / 1024, 0, ',', '.').' KB';
    }

    public function selectedPdfName(int $requirementId): ?string
    {
        $selectedPdf = $this->pdfUploads[$requirementId] ?? null;

        return is_object($selectedPdf) && method_exists($selectedPdf, 'getClientOriginalName')
            ? $selectedPdf->getClientOriginalName()
            : null;
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

<section class="portal-page portal-page--wide">
    <x-pages::settings.layout heading="" subheading="">
        @php
            $certificateRequirement = $this->certificateRequirement;
            $totalRequirements = $this->requirements->count();
            $requiredTotalRequirements = $this->requirements->filter(fn (OperadoraRequirement $requirement): bool => (bool) $requirement->is_required)->count();
            $optionalTotalRequirements = $this->requirements->filter(fn (OperadoraRequirement $requirement): bool => ! $requirement->is_required)->count();
            $displayedRequirements = $this->displayedRequirements;
            $requiredRequirements = $displayedRequirements->filter(fn (OperadoraRequirement $requirement): bool => (bool) $requirement->is_required)->values();
            $optionalRequirements = $displayedRequirements->filter(fn (OperadoraRequirement $requirement): bool => ! $requirement->is_required)->values();
            $selectedCertificateName = $certificateRequirement ? $this->selectedPdfName($certificateRequirement->id) : null;
            $certificateTone = $certificateRequirement ? $this->requirementTone($certificateRequirement) : 'pending';
        @endphp

        <div class="operadora-shell">
            <section class="portal-hero portal-hero--client">
                <div class="portal-hero__row">
                    <div>
                        <p class="portal-hero__eyebrow text-sky-700 dark:text-sky-300">Portal cliente</p>
                        <h1 class="portal-hero__title">Operadora</h1>
                        <p class="portal-hero__text">
                            Completa y gestiona los datos del certificado operador y la documentacion requerida por el gestor.
                        </p>

                    </div>

                    <div class="portal-hero__aside">
                        <div class="portal-hero__brand">
                            <img
                                src="{{ asset('images/logo-idronlex.png') }}"
                                alt="Idron Lex & Consulting"
                                class="portal-hero__logo"
                            >
                        </div>
                    </div>
                </div>
            </section>

            <section class="operadora-status-summary-grid" aria-label="Resumen de requisitos de operadora">
                <div class="operadora-status-summary-card operadora-status-summary-card--total">
                    <span class="operadora-status-summary-card__icon">
                        <flux:icon icon="document" variant="mini" class="size-5" />
                    </span>
                    <span>
                        <small>Aprobados / Total</small>
                        <strong>{{ $this->completedCount }}/{{ $totalRequirements }}</strong>
                        <em>Aprobados del total</em>
                    </span>
                </div>

                <div class="operadora-status-summary-card operadora-status-summary-card--pending">
                    <span class="operadora-status-summary-card__icon">
                        <flux:icon icon="clock" variant="mini" class="size-5" />
                    </span>
                    <span>
                        <small>Pendientes</small>
                        <strong>{{ $this->pendingCount }}</strong>
                        <em>Por subir</em>
                    </span>
                </div>

                <div class="operadora-status-summary-card operadora-status-summary-card--review">
                    <span class="operadora-status-summary-card__icon">
                        <flux:icon icon="information-circle" variant="mini" class="size-5" />
                    </span>
                    <span>
                        <small>En revision</small>
                        <strong>{{ $this->inReviewCount }}</strong>
                        <em>Pendientes del gestor</em>
                    </span>
                </div>

                <div class="operadora-status-summary-card operadora-status-summary-card--approved">
                    <span class="operadora-status-summary-card__icon">
                        <flux:icon icon="check-circle" variant="mini" class="size-5" />
                    </span>
                    <span>
                        <small>Aprobados</small>
                        <strong>{{ $this->completedCount }}</strong>
                        <em>Con visto bueno</em>
                    </span>
                </div>

                <div class="operadora-status-summary-card operadora-status-summary-card--changes">
                    <span class="operadora-status-summary-card__icon">
                        <flux:icon icon="exclamation-triangle" variant="mini" class="size-5" />
                    </span>
                    <span>
                        <small>Correccion</small>
                        <strong>{{ $this->needsChangesCount }}</strong>
                        <em>Requieren cambios</em>
                    </span>
                </div>
            </section>

            <section class="operadora-certificate-card operadora-certificate-card--{{ $certificateTone }}">
                <div class="operadora-certificate-card__header">
                    <div class="operadora-certificate-card__title">
                        <span class="operadora-certificate-card__icon">
                            <flux:icon icon="document-text" variant="mini" class="size-5" />
                        </span>
                        <div>
                            <h2>Certificado de Operador: datos y PDF</h2>
                            <p>Completa los datos del certificado y adjunta el PDF que los acredita.</p>
                        </div>
                    </div>

                    <div class="operadora-card-heading__badges">
                        @if ($certificateRequirement)
                            <span class="operadora-status-pill operadora-status-pill--{{ $certificateTone }}">
                                {{ $this->requirementStatusLabel($certificateRequirement) }}
                            </span>
                        @endif
                        <span class="operadora-status-pill operadora-status-pill--required">Obligatorio</span>
                    </div>
                </div>

                <div class="operadora-certificate-guide">
                    <flux:icon icon="information-circle" variant="mini" class="size-5" />
                    <span>Los datos introducidos deben coincidir exactamente con el PDF del certificado. El gestor revisara ambos antes de aprobarlo.</span>
                </div>

                <div class="operadora-certificate-steps">
                    <form wire:submit="saveProfile" class="operadora-certificate-step">
                        <div class="operadora-card-heading">
                            <div>
                                <h3><span>1</span> Paso 1 - Datos del certificado</h3>
                                <p>Rellena los campos exactamente como figuran en el certificado de operador.</p>
                            </div>
                            <span class="operadora-status-pill operadora-status-pill--info">Datos base</span>
                        </div>

                        <div class="operadora-form-grid">
                            <flux:input wire:model="first_name" label="Nombre" type="text" required />
                            <flux:input wire:model="last_name" label="Apellido" type="text" required />
                            <flux:input wire:model="second_last_name" label="Segundo apellido" type="text" />
                            <flux:input wire:model="registration_number" label="Numero de registro" type="text" required />
                            <flux:input wire:model="expiration_date" label="Fecha de caducidad" type="date" required />
                        </div>

                        <div class="operadora-info-callout">
                            <flux:icon icon="information-circle" variant="mini" class="size-5" />
                            <span>Revisa nombre, apellidos, numero de registro y fecha de caducidad antes de subir el PDF.</span>
                        </div>

                        <div class="operadora-actions">
                            <flux:button variant="primary" type="submit">Guardar datos</flux:button>
                            <x-action-message class="me-3" on="operadora-profile-saved">Guardado.</x-action-message>
                        </div>
                    </form>

                    <div class="operadora-certificate-step">
                        <div class="operadora-card-heading">
                            <div>
                                <h3><span>2</span> Paso 2 - PDF del certificado</h3>
                                <p>Sube el PDF del certificado de operador emitido por AESA.</p>
                            </div>
                        </div>

                        @if ($certificateRequirement)
                            <form
                                wire:submit="savePdfRequirement({{ $certificateRequirement->id }})"
                                x-data="{ uploading: false, progress: 0 }"
                                x-on:livewire-upload-start="uploading = true; progress = 0"
                                x-on:livewire-upload-finish="uploading = false; progress = 100"
                                x-on:livewire-upload-error="uploading = false"
                                x-on:livewire-upload-cancel="uploading = false; progress = 0"
                                x-on:livewire-upload-progress="progress = $event.detail.progress"
                            >
                                <input id="operadora-certificate-pdf-{{ $certificateRequirement->id }}" type="file" wire:model="pdfUploads.{{ $certificateRequirement->id }}" accept=".pdf,application/pdf" class="hidden" />

                                <label for="operadora-certificate-pdf-{{ $certificateRequirement->id }}" class="operadora-dropzone">
                                    <span class="operadora-dropzone__icon">
                                        <flux:icon icon="cloud-arrow-up" variant="mini" class="size-9" />
                                    </span>
                                    <strong>Arrastra tu archivo PDF aqui</strong>
                                    <span>o haz clic para seleccionar</span>
                                    <small>Formato PDF - Max. 10MB</small>
                                </label>

                                @if ($selectedCertificateName)
                                    <div class="operadora-selected-file">
                                        <flux:icon icon="document" variant="mini" class="size-5" />
                                        <span>{{ $selectedCertificateName }}</span>
                                    </div>
                                @endif

                                <div x-cloak x-show="uploading" class="operadora-upload-progress">
                                    <div class="operadora-upload-progress__row">
                                        <span>Subiendo PDF...</span>
                                        <strong x-text="`${progress}%`"></strong>
                                    </div>
                                    <div class="operadora-upload-progress__track">
                                        <div class="operadora-upload-progress__fill" x-bind:style="`width: ${progress}%`"></div>
                                    </div>
                                </div>

                                @if ($certificateRequirement->file_path)
                                    <div class="operadora-file-current operadora-file-current--{{ $certificateTone }}">
                                        <div class="operadora-file-current__icon">
                                            <flux:icon icon="document-text" variant="mini" class="size-6" />
                                        </div>
                                        <div>
                                            <p>{{ $certificateRequirement->original_file_name ?: 'Certificado operador.pdf' }}</p>
                                            <span>
                                                {{ $this->requirementStatusLabel($certificateRequirement) }} -
                                                Subido el {{ $this->formatDateValue($certificateRequirement->submitted_at ?? $certificateRequirement->updated_at) }}
                                                - {{ $this->formatFileSize($certificateRequirement->file_size) }}
                                            </span>
                                        </div>
                                        <span class="operadora-file-current__status-icon">
                                            <flux:icon :icon="$this->requirementIcon($certificateRequirement)" variant="mini" class="size-5" />
                                        </span>
                                    </div>

                                    <div class="operadora-actions">
                                        <flux:button type="button" variant="filled" color="zinc" wire:click="downloadPdf({{ $certificateRequirement->id }})">
                                            Descargar archivo actual
                                        </flux:button>
                                    </div>
                                @endif

                                @error("pdfUploads.$certificateRequirement->id")
                                    <p class="mt-3 text-sm font-medium text-red-600 dark:text-red-400">{{ $message }}</p>
                                @enderror

                                <x-action-message class="me-3" on="operadora-saved">Guardado.</x-action-message>
                            </form>
                        @else
                            <div class="operadora-empty-state">No se ha encontrado el requisito base del certificado.</div>
                        @endif
                    </div>
                </div>
            </section>

            <section class="operadora-section-card">
                <div class="operadora-requirements-header">
                    <div>
                        <h2 class="operadora-section-card__title">Requisitos de la operadora</h2>
                        <p class="operadora-section-card__text">Documentacion obligatoria para operar. Sube los documentos requeridos segun indique el gestor.</p>
                    </div>

                    <div class="operadora-progress-summary">
                        <div class="operadora-progress-summary__row">
                            <span>Progreso obligatorios</span>
                            <strong>{{ $this->progressPercent }}%</strong>
                        </div>
                        <div class="operadora-progress-summary__track">
                            <div class="operadora-progress-summary__fill" style="width: {{ $this->progressPercent }}%"></div>
                        </div>
                    </div>
                </div>

                <div class="operadora-filter-bar portal-filter-bar portal-filter-bar--compact">
                    <div class="portal-filter-header">
                        <span class="portal-filter-header__icon">
                            <flux:icon icon="funnel" variant="mini" class="size-7" />
                        </span>
                        <div>
                            <h3 class="portal-filter-header__title">Filtros</h3>
                            <p class="portal-filter-header__text">Encuentra rapidamente los requisitos que necesitas revisar.</p>
                        </div>
                    </div>

                    <div class="portal-filter-section">
                        <div class="portal-filter-section__heading">
                            <p class="portal-filter-section__title">Estado del requisito</p>
                        </div>

                        <div class="portal-filter-statuses operadora-filter-statuses">
                            <button type="button" wire:click="setRequirementFilter('all')" class="portal-filter-option operadora-filter-option operadora-filter-option--all {{ $requirementFilter === 'all' ? 'portal-filter-option--active' : '' }}" aria-pressed="{{ $requirementFilter === 'all' ? 'true' : 'false' }}">
                                <span class="portal-filter-option__dot"></span>
                                <span>Todos</span>
                                <strong>{{ $totalRequirements }}</strong>
                            </button>
                            <button type="button" wire:click="setRequirementFilter('pending')" class="portal-filter-option operadora-filter-option operadora-filter-option--pending {{ $requirementFilter === 'pending' ? 'portal-filter-option--active' : '' }}" aria-pressed="{{ $requirementFilter === 'pending' ? 'true' : 'false' }}">
                                <span class="portal-filter-option__dot"></span>
                                <span>Pendientes</span>
                                <strong>{{ $this->pendingCount }}</strong>
                            </button>
                            <button type="button" wire:click="setRequirementFilter('review')" class="portal-filter-option operadora-filter-option operadora-filter-option--review {{ $requirementFilter === 'review' ? 'portal-filter-option--active' : '' }}" aria-pressed="{{ $requirementFilter === 'review' ? 'true' : 'false' }}">
                                <span class="portal-filter-option__dot"></span>
                                <span>En revision</span>
                                <strong>{{ $this->inReviewCount }}</strong>
                            </button>
                            <button type="button" wire:click="setRequirementFilter('changes')" class="portal-filter-option operadora-filter-option operadora-filter-option--changes {{ $requirementFilter === 'changes' ? 'portal-filter-option--active' : '' }}" aria-pressed="{{ $requirementFilter === 'changes' ? 'true' : 'false' }}">
                                <span class="portal-filter-option__dot"></span>
                                <span>Corregir</span>
                                <strong>{{ $this->needsChangesCount }}</strong>
                            </button>
                            <button type="button" wire:click="setRequirementFilter('approved')" class="portal-filter-option operadora-filter-option operadora-filter-option--approved {{ $requirementFilter === 'approved' ? 'portal-filter-option--active' : '' }}" aria-pressed="{{ $requirementFilter === 'approved' ? 'true' : 'false' }}">
                                <span class="portal-filter-option__dot"></span>
                                <span>Aprobados</span>
                                <strong>{{ $this->completedCount }}</strong>
                            </button>
                        </div>
                    </div>

                    <div class="portal-filter-divider"></div>

                    <div class="portal-filter-section">
                        <div class="portal-filter-section__heading">
                            <p class="portal-filter-section__title">Tipo de requisito</p>
                        </div>

                        <div class="portal-filter-statuses operadora-filter-statuses operadora-filter-statuses--type">
                            <button type="button" wire:click="setRequirementTypeFilter('all')" class="portal-filter-option operadora-filter-option operadora-filter-option--all {{ $requirementTypeFilter === 'all' ? 'portal-filter-option--active' : '' }}" aria-pressed="{{ $requirementTypeFilter === 'all' ? 'true' : 'false' }}">
                                <span class="portal-filter-option__dot"></span>
                                <span>Todos</span>
                                <strong>{{ $totalRequirements }}</strong>
                            </button>
                            <button type="button" wire:click="setRequirementTypeFilter('required')" class="portal-filter-option operadora-filter-option operadora-filter-option--required {{ $requirementTypeFilter === 'required' ? 'portal-filter-option--active' : '' }}" aria-pressed="{{ $requirementTypeFilter === 'required' ? 'true' : 'false' }}">
                                <span class="portal-filter-option__dot"></span>
                                <span>Obligatorios</span>
                                <strong>{{ $requiredTotalRequirements }}</strong>
                            </button>
                            <button type="button" wire:click="setRequirementTypeFilter('optional')" class="portal-filter-option operadora-filter-option operadora-filter-option--optional {{ $requirementTypeFilter === 'optional' ? 'portal-filter-option--active' : '' }}" aria-pressed="{{ $requirementTypeFilter === 'optional' ? 'true' : 'false' }}">
                                <span class="portal-filter-option__dot"></span>
                                <span>Opcionales</span>
                                <strong>{{ $optionalTotalRequirements }}</strong>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="operadora-requirement-groups">
                    @foreach ([
                        ['kind' => 'required', 'title' => 'Requisitos obligatorios', 'description' => 'Documentos necesarios para validar la operadora.', 'items' => $requiredRequirements, 'countLabel' => $requiredRequirements->count().' requisitos', 'icon' => 'bookmark', 'visible' => $requirementTypeFilter !== 'optional'],
                        ['kind' => 'optional', 'title' => 'Documentacion adicional', 'description' => 'Documentos complementarios que pueden ser requeridos en el futuro.', 'items' => $optionalRequirements, 'countLabel' => $optionalRequirements->count().' opcionales', 'icon' => 'paper-clip', 'visible' => $requirementTypeFilter !== 'required'],
                    ] as $group)
                        @continue(! $group['visible'])

                        <div class="operadora-requirement-group operadora-requirement-group--{{ $group['kind'] }}">
                            <div class="operadora-requirement-group__header">
                                <div class="operadora-requirement-group__icon">
                                    <flux:icon icon="{{ $group['icon'] }}" variant="mini" class="size-5" />
                                </div>
                                <div>
                                    <h3>{{ $group['title'] }}</h3>
                                    <p>{{ $group['description'] }}</p>
                                </div>
                                <span>{{ $group['countLabel'] }}</span>
                            </div>

                            <div class="operadora-requirement-list">
                                @forelse ($group['items'] as $requirement)
                                    @php
                                        $tone = $this->requirementTone($requirement);
                                        $selectedPdfName = $this->selectedPdfName($requirement->id);
                                    @endphp

                                    <article id="requisito-operadora-{{ $requirement->id }}" class="operadora-requirement-row operadora-requirement-row--{{ $tone }} portal-anchor-target">
                                        <div class="operadora-requirement-main">
                                            <div class="operadora-requirement-icon">
                                                <flux:icon :icon="$this->requirementIcon($requirement)" variant="mini" class="size-6" />
                                            </div>

                                            <div class="operadora-requirement-copy">
                                                <div class="operadora-requirement-titleline">
                                                    <h3>{{ $requirement->name }}</h3>
                                                    <span class="operadora-status-pill operadora-status-pill--{{ $tone }}">
                                                        {{ $this->requirementStatusLabel($requirement) }}
                                                    </span>
                                                    <span class="operadora-status-pill {{ $requirement->is_required ? 'operadora-status-pill--required' : 'operadora-status-pill--neutral' }}">
                                                        {{ $requirement->is_required ? 'Obligatorio' : 'Opcional' }}
                                                    </span>
                                                </div>

                                                <p>{{ filled($requirement->instructions) ? $requirement->instructions : $this->requirementStatusMessage($requirement) }}</p>

                                                @if ($requirement->status === OperadoraRequirement::STATUS_NEEDS_CHANGES && filled($requirement->review_notes))
                                                    <div class="operadora-review-note">
                                                        Correccion solicitada: {{ $requirement->review_notes }}
                                                    </div>
                                                @endif
                                            </div>
                                        </div>

                                        <div class="operadora-requirement-action">
                                            @if ($requirement->input_type === OperadoraRequirement::TYPE_TEXT)
                                                <form wire:submit="saveTextRequirement({{ $requirement->id }})" class="operadora-text-form">
                                                    <flux:textarea wire:model="textInputs.{{ $requirement->id }}" label="Tu respuesta" rows="3" />
                                                    <div class="operadora-actions">
                                                        <flux:button variant="primary" type="submit">Guardar cambios</flux:button>
                                                        <x-action-message class="me-3" on="operadora-saved">Guardado.</x-action-message>
                                                    </div>
                                                </form>
                                            @else
                                                <form
                                                    wire:submit="savePdfRequirement({{ $requirement->id }})"
                                                    x-data="{ uploading: false, progress: 0 }"
                                                    x-on:livewire-upload-start="uploading = true; progress = 0"
                                                    x-on:livewire-upload-finish="uploading = false; progress = 100"
                                                    x-on:livewire-upload-error="uploading = false"
                                                    x-on:livewire-upload-cancel="uploading = false; progress = 0"
                                                    x-on:livewire-upload-progress="progress = $event.detail.progress"
                                                    class="operadora-row-upload"
                                                >
                                                    <input id="operadora-row-pdf-{{ $requirement->id }}" type="file" wire:model="pdfUploads.{{ $requirement->id }}" accept=".pdf,application/pdf" class="hidden" />

                                                    @if ($requirement->file_path)
                                                        <div class="operadora-row-file">
                                                            <div>
                                                                <span>Documento subido</span>
                                                                <strong>{{ $requirement->original_file_name ?: 'PDF cargado correctamente.' }}</strong>
                                                                <small>Subido el {{ $this->formatDateValue($requirement->submitted_at ?? $requirement->updated_at) }}</small>
                                                            </div>

                                                            <div class="operadora-row-file__actions">
                                                                <label for="operadora-row-pdf-{{ $requirement->id }}" class="operadora-row-file__replace">
                                                                    Cambiar PDF
                                                                </label>
                                                                <flux:button type="button" variant="filled" color="zinc" wire:click="downloadPdf({{ $requirement->id }})">
                                                                    Descargar
                                                                </flux:button>
                                                            </div>
                                                        </div>
                                                    @else
                                                        <label for="operadora-row-pdf-{{ $requirement->id }}" class="operadora-row-upload__trigger">
                                                            <strong>Seleccionar PDF</strong>
                                                            <span>o arrastrar el archivo aqui</span>
                                                        </label>
                                                    @endif

                                                    @if ($selectedPdfName)
                                                        <div class="operadora-selected-file">
                                                            <flux:icon icon="document" variant="mini" class="size-5" />
                                                            <span>{{ $selectedPdfName }}</span>
                                                        </div>
                                                    @endif

                                                    <div x-cloak x-show="uploading" class="operadora-upload-progress">
                                                        <div class="operadora-upload-progress__row">
                                                            <span>Subiendo PDF...</span>
                                                            <strong x-text="`${progress}%`"></strong>
                                                        </div>
                                                        <div class="operadora-upload-progress__track">
                                                            <div class="operadora-upload-progress__fill" x-bind:style="`width: ${progress}%`"></div>
                                                        </div>
                                                    </div>

                                                    @error("pdfUploads.$requirement->id")
                                                        <p class="text-sm font-medium text-red-600 dark:text-red-400">{{ $message }}</p>
                                                    @enderror

                                                    <x-action-message class="me-3" on="operadora-saved">Guardado.</x-action-message>
                                                </form>
                                            @endif
                                        </div>
                                    </article>
                                @empty
                                    <div class="operadora-empty-state">
                                        No hay {{ \Illuminate\Support\Str::lower($group['title']) }} para el filtro seleccionado.
                                    </div>
                                @endforelse
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        </div>
    </x-pages::settings.layout>
</section>
