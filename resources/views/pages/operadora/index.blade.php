<?php

use App\Models\OperadoraRequirement;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Operadora')] class extends Component {
    use WithFileUploads;

    /** @var array<int, string> */
    public array $textInputs = [];

    /** @var array<int, mixed> */
    public array $pdfUploads = [];

    public function mount(): void
    {
        abort_unless($this->cliente?->isUnblocked(), 403);

        foreach ($this->requirements as $requirement) {
            $this->textInputs[$requirement->id] = $requirement->text_value ?? '';
            $this->pdfUploads[$requirement->id] = null;
        }
    }

    #[Computed]
    public function cliente()
    {
        return Auth::user()->cliente;
    }

    #[Computed]
    public function requirements()
    {
        return $this->cliente
            ? $this->cliente
                ->operadoraRequirements()
                ->orderByDesc('is_required')
                ->latest('id')
                ->get()
            : collect();
    }

    #[Computed]
    public function completedCount(): int
    {
        return $this->requirements
            ->filter(fn (OperadoraRequirement $requirement): bool => $requirement->isCompleted())
            ->count();
    }

    #[Computed]
    public function pendingCount(): int
    {
        return $this->requirements->count() - $this->completedCount;
    }

    #[Computed]
    public function progressPercentage(): float
    {
        if ($this->requirements->isEmpty()) {
            return 0;
        }

        return round(($this->completedCount / $this->requirements->count()) * 100, 2);
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
        $path = $uploadedFile->store('operadora', 'public');

        $requirement->update([
            'file_path' => $path,
            'status' => OperadoraRequirement::STATUS_IN_REVIEW,
            'submitted_at' => now(),
        ]);

        unset($this->pdfUploads[$requirementId]);
        $this->dispatch('operadora-saved');
    }

    public function downloadPdf(int $requirementId)
    {
        $requirement = $this->cliente?->operadoraRequirements()->findOrFail($requirementId);

        abort_unless(filled($requirement->file_path), 404);

        return Storage::disk('public')->download($requirement->file_path);
    }

    public function completionLabel(OperadoraRequirement $requirement): string
    {
        return $requirement->isCompleted() ? 'Completado' : 'Pendiente';
    }
}; ?>

<section class="w-full">
    <x-pages::settings.layout heading="" subheading="">
        <div class="rounded-3xl border border-cyan-200 bg-gradient-to-br from-cyan-50 via-white to-sky-50 p-6 shadow-sm dark:border-cyan-800/60 dark:from-cyan-950/30 dark:via-neutral-900 dark:to-sky-950/30">
            <p class="text-sm uppercase tracking-[0.25em] text-cyan-700 dark:text-cyan-300">Portal cliente</p>
            <h1 class="mt-3 text-3xl font-semibold text-neutral-900 dark:text-white">Documentacion Operadora</h1>

            <div class="mt-6">
                <div class="flex items-center justify-between gap-4">
                    <p class="text-sm font-semibold text-neutral-900 dark:text-white">Progreso de requisitos</p>
                    <span class="text-sm font-semibold text-cyan-700 dark:text-cyan-300">
                        {{ rtrim(rtrim(number_format($this->progressPercentage, 2, '.', ''), '0'), '.') }}%
                    </span>
                </div>

                <div class="mt-3">
                    <x-ui.progress-bar
                        :value="$this->progressPercentage"
                        height="12px"
                        track-color="#cffafe"
                        fill-color="linear-gradient(90deg, #06b6d4 0%, #0ea5e9 100%)"
                    />
                </div>
            </div>
        </div>

        <div class="mt-6 grid gap-4 md:grid-cols-3">
            <div class="rounded-3xl border border-neutral-200 bg-gradient-to-br from-white to-neutral-50 p-5 shadow-sm dark:border-neutral-700 dark:from-neutral-900 dark:to-neutral-950">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-sm font-semibold text-neutral-500 dark:text-neutral-400">Total requisitos</p>
                        <p class="mt-3 text-3xl font-semibold text-neutral-900 dark:text-white">{{ $this->requirements->count() }}</p>
                    </div>

                    <div class="flex size-11 items-center justify-center rounded-2xl bg-neutral-900 text-white shadow-sm dark:bg-white dark:text-neutral-900">
                        <span class="text-lg font-bold">{{ $this->requirements->count() }}</span>
                    </div>
                </div>
            </div>

            <div class="rounded-3xl border border-red-200 bg-gradient-to-br from-red-50 to-orange-50 p-5 shadow-sm dark:border-red-800/60 dark:from-red-950/30 dark:to-orange-950/20">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-sm font-semibold text-red-700 dark:text-red-300">Pendientes</p>
                        <p class="mt-3 text-3xl font-semibold text-neutral-900 dark:text-white">{{ $this->pendingCount }}</p>
                    </div>

                    <div class="flex size-11 items-center justify-center rounded-2xl bg-red-500 text-white shadow-sm">
                        <span class="text-lg font-bold">!</span>
                    </div>
                </div>
            </div>

            <div class="rounded-3xl border border-emerald-300 bg-gradient-to-br from-emerald-100 to-teal-50 p-5 shadow-sm dark:border-emerald-700 dark:from-emerald-950/35 dark:to-teal-950/20">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-sm font-semibold text-emerald-800 dark:text-emerald-300">Completados</p>
                        <p class="mt-3 text-3xl font-semibold text-neutral-900 dark:text-white">{{ $this->completedCount }}</p>
                    </div>

                    <div class="flex size-11 items-center justify-center rounded-2xl bg-emerald-500 text-white shadow-sm">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="size-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-6 grid gap-5">
            @forelse ($this->requirements as $requirement)
                <div class="relative overflow-hidden rounded-3xl border {{ $requirement->isCompleted() ? 'border-emerald-400 bg-emerald-100/90 shadow-emerald-200/80 dark:border-emerald-600 dark:bg-emerald-950/35' : 'border-red-200 bg-white dark:border-red-800/60 dark:bg-neutral-900' }} p-6 shadow-sm">
                    <div class="absolute inset-y-0 left-0 w-2 {{ $requirement->isCompleted() ? 'bg-emerald-600' : 'bg-red-500' }}"></div>

                    @if ($requirement->isCompleted())
                        <div class="mb-5 flex items-center gap-3 rounded-2xl bg-emerald-600 px-4 py-3 text-white shadow-sm">
                            <div class="flex size-8 items-center justify-center rounded-full bg-white/20">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="size-4">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                </svg>
                            </div>
                            <div>
                                <p class="text-sm font-semibold">Requisito completado</p>
                                <p class="text-xs text-emerald-100">La informacion ya ha sido entregada correctamente.</p>
                            </div>
                        </div>
                    @endif

                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="text-xl font-semibold text-neutral-900 dark:text-white">{{ $requirement->name }}</h2>

                                <span class="rounded-full px-3 py-1 text-xs font-semibold shadow-sm {{ $requirement->isCompleted() ? 'bg-emerald-600 text-white dark:bg-emerald-500 dark:text-white' : 'bg-red-500 text-white dark:bg-red-500 dark:text-white' }}">
                                    {{ $this->completionLabel($requirement) }}
                                </span>

                                <span class="rounded-full px-3 py-1 text-xs font-medium shadow-sm {{ $requirement->is_required ? 'bg-yellow-400 text-yellow-950 dark:bg-yellow-400 dark:text-yellow-950' : 'bg-neutral-200 text-neutral-800 dark:bg-neutral-700 dark:text-neutral-100' }}">
                                    {{ $requirement->is_required ? 'Obligatorio' : 'Opcional' }}
                                </span>

                                <span class="rounded-full px-3 py-1 text-xs font-medium shadow-sm {{ $requirement->input_type === \App\Models\OperadoraRequirement::TYPE_TEXT ? 'bg-cyan-500 text-white dark:bg-cyan-500 dark:text-white' : 'bg-emerald-500 text-white dark:bg-emerald-500 dark:text-white' }}">
                                    {{ \App\Models\OperadoraRequirement::inputTypeOptions()[$requirement->input_type] ?? $requirement->input_type }}
                                </span>
                            </div>

                            @if (filled($requirement->instructions))
                                <p class="mt-3 text-sm text-neutral-600 dark:text-neutral-300">{{ $requirement->instructions }}</p>
                            @endif

                            @if ($requirement->submitted_at)
                                <p class="mt-3 text-xs text-neutral-500 dark:text-neutral-400">
                                    Ultima entrega: {{ $requirement->submitted_at->format('d/m/Y H:i') }}
                                </p>
                            @endif
                        </div>

                        <div class="flex size-12 items-center justify-center rounded-2xl {{ $requirement->isCompleted() ? 'bg-emerald-600 text-white shadow-md shadow-emerald-200 dark:bg-emerald-500' : 'bg-red-100 text-red-500 dark:bg-red-950/40 dark:text-red-300' }}">
                            @if ($requirement->isCompleted())
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="size-5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                </svg>
                            @else
                                <span class="text-base font-bold">!</span>
                            @endif
                        </div>
                    </div>

                    <div class="mt-5">
                        @if ($requirement->input_type === \App\Models\OperadoraRequirement::TYPE_TEXT)
                            <form wire:submit="saveTextRequirement({{ $requirement->id }})" class="space-y-4">
                                <flux:textarea
                                    wire:model="textInputs.{{ $requirement->id }}"
                                    label="Texto solicitado"
                                    rows="4"
                                />

                                <div class="flex items-center gap-3">
                                    <flux:button variant="primary" type="submit">
                                        Guardar cambios
                                    </flux:button>
                                    <x-action-message class="me-3" on="operadora-saved">
                                        Guardado.
                                    </x-action-message>
                                </div>
                            </form>
                        @else
                            <div class="space-y-4">
                                @if ($requirement->file_path)
                                    <div class="rounded-2xl border border-emerald-300 bg-emerald-200/80 p-4 text-sm font-medium text-emerald-900 dark:border-emerald-700 dark:bg-emerald-950/30 dark:text-emerald-200">
                                        PDF entregado correctamente.
                                    </div>
                                @endif

                                <form
                                    wire:submit="savePdfRequirement({{ $requirement->id }})"
                                    class="space-y-4"
                                    x-data="{ uploading: false, progress: 0 }"
                                    x-on:livewire-upload-start="uploading = true; progress = 0"
                                    x-on:livewire-upload-finish="uploading = false; progress = 100"
                                    x-on:livewire-upload-error="uploading = false"
                                    x-on:livewire-upload-cancel="uploading = false; progress = 0"
                                    x-on:livewire-upload-progress="progress = $event.detail.progress"
                                >
                                    <input
                                        id="operadora-pdf-{{ $requirement->id }}"
                                        type="file"
                                        wire:model="pdfUploads.{{ $requirement->id }}"
                                        accept=".pdf,application/pdf"
                                        class="hidden"
                                    />

                                    @php
                                        $selectedPdf = $pdfUploads[$requirement->id] ?? null;
                                        $selectedPdfName = is_object($selectedPdf) && method_exists($selectedPdf, 'getClientOriginalName')
                                            ? $selectedPdf->getClientOriginalName()
                                            : null;
                                    @endphp

                                    <div class="flex flex-wrap items-center gap-3">
                                        <label
                                            for="operadora-pdf-{{ $requirement->id }}"
                                            class="inline-flex h-10 cursor-pointer items-center justify-center rounded-lg bg-cyan-500 px-4 text-sm font-medium text-white shadow-sm transition hover:bg-cyan-600"
                                        >
                                            Seleccionar PDF
                                        </label>

                                        <flux:button variant="primary" type="submit">
                                            Guardar cambios
                                        </flux:button>

                                        @if ($requirement->file_path)
                                            <flux:button variant="ghost" wire:click="downloadPdf({{ $requirement->id }})">
                                                Descargar actual
                                            </flux:button>
                                        @endif

                                        <x-action-message class="me-3" on="operadora-saved">
                                            Guardado.
                                        </x-action-message>
                                    </div>

                                    <div x-cloak x-show="uploading" class="rounded-2xl border border-cyan-200 bg-cyan-50 p-4 dark:border-cyan-800/60 dark:bg-cyan-950/20">
                                        <div class="flex items-center justify-between gap-4">
                                            <p class="text-sm font-medium text-cyan-800 dark:text-cyan-200">Subiendo PDF...</p>
                                            <span class="text-sm font-semibold text-cyan-700 dark:text-cyan-300" x-text="`${progress}%`"></span>
                                        </div>

                                        <div class="mt-3 h-[10px] overflow-hidden rounded-full bg-cyan-100 dark:bg-cyan-900/40">
                                            <div
                                                class="h-full rounded-full bg-gradient-to-r from-cyan-500 to-sky-500 transition-all duration-300"
                                                x-bind:style="`width: ${progress}%`"
                                            ></div>
                                        </div>
                                    </div>

                                    @if ($selectedPdfName)
                                        <p class="text-sm font-medium text-cyan-700 dark:text-cyan-200">
                                            Archivo seleccionado: {{ $selectedPdfName }}
                                        </p>
                                    @endif

                                    @error("pdfUploads.$requirement->id")
                                        <p class="text-sm font-medium text-red-600 dark:text-red-400">
                                            {{ $message }}
                                        </p>
                                    @enderror
                                </form>
                            </div>
                        @endif
                    </div>
                </div>
            @empty
                <div class="rounded-3xl border border-dashed border-neutral-300 bg-neutral-50 p-6 text-sm text-neutral-600 dark:border-neutral-700 dark:bg-neutral-950/30 dark:text-neutral-300">
                    El gestor todavia no ha definido requisitos para Operadora.
                </div>
            @endforelse
        </div>
    </x-pages::settings.layout>
</section>
