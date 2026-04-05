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
            ? $this->cliente->operadoraRequirements()->latest()->get()
            : collect();
    }

    public function saveTextRequirement(int $requirementId): void
    {
        $requirement = $this->cliente?->operadoraRequirements()->findOrFail($requirementId);

        abort_unless($requirement->input_type === OperadoraRequirement::TYPE_TEXT, 403);

        $this->validate([
            "textInputs.$requirementId" => ['required', 'string'],
        ]);

        // Cuando el cliente rellena el texto, el requisito pasa a revision.
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

    public function statusLabel(string $status): string
    {
        return OperadoraRequirement::statusOptions()[$status] ?? $status;
    }
}; ?>

<section class="w-full">
    <div class="mx-auto flex w-full max-w-6xl flex-col gap-6 px-4 py-6 sm:px-6 lg:px-8">
        <div class="rounded-3xl border border-cyan-200 bg-gradient-to-br from-cyan-50 via-white to-sky-50 p-6 shadow-sm dark:border-cyan-800/60 dark:from-cyan-950/30 dark:via-neutral-900 dark:to-sky-950/30">
            <p class="text-sm uppercase tracking-[0.25em] text-cyan-700 dark:text-cyan-300">Portal cliente</p>
            <h1 class="mt-3 text-3xl font-semibold text-neutral-900 dark:text-white">Documentacion Operadora</h1>
            <p class="mt-3 max-w-3xl text-sm text-neutral-700 dark:text-neutral-300">
                Aqui completas los requisitos de operadora que el gestor haya definido para tu cliente. Cada requisito puede pedir un PDF o un texto.
            </p>
        </div>

        <div class="grid gap-4 md:grid-cols-3">
            <div class="rounded-2xl border border-neutral-200 bg-white p-5 shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
                <p class="text-sm text-neutral-500">Total requisitos</p>
                <p class="mt-2 text-2xl font-semibold text-neutral-900 dark:text-white">{{ $this->requirements->count() }}</p>
            </div>

            <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5 shadow-sm dark:border-amber-800/60 dark:bg-amber-950/20">
                <p class="text-sm text-amber-700 dark:text-amber-300">Pendientes</p>
                <p class="mt-2 text-2xl font-semibold text-neutral-900 dark:text-white">{{ $this->cliente->pendingOperadoraRequirementsCount() }}</p>
            </div>

            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5 shadow-sm dark:border-emerald-800/60 dark:bg-emerald-950/20">
                <p class="text-sm text-emerald-700 dark:text-emerald-300">Completados</p>
                <p class="mt-2 text-2xl font-semibold text-neutral-900 dark:text-white">{{ $this->cliente->completedOperadoraRequirementsCount() }}</p>
            </div>
        </div>

        <div class="grid gap-4">
            @forelse ($this->requirements as $requirement)
                <div class="rounded-3xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="text-xl font-semibold text-neutral-900 dark:text-white">{{ $requirement->name }}</h2>
                                <span class="rounded-full border px-3 py-1 text-xs font-medium {{ $requirement->isCompleted() ? 'border-emerald-300 bg-emerald-100 text-emerald-800 dark:border-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-200' : 'border-amber-300 bg-amber-100 text-amber-800 dark:border-amber-700 dark:bg-amber-900/40 dark:text-amber-200' }}">
                                    {{ $this->statusLabel($requirement->status) }}
                                </span>
                                <span class="rounded-full border border-neutral-300 bg-neutral-100 px-3 py-1 text-xs font-medium text-neutral-700 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-300">
                                    {{ $requirement->is_required ? 'Obligatorio' : 'Opcional' }}
                                </span>
                                <span class="rounded-full border border-cyan-300 bg-cyan-100 px-3 py-1 text-xs font-medium text-cyan-800 dark:border-cyan-700 dark:bg-cyan-900/40 dark:text-cyan-200">
                                    {{ \App\Models\OperadoraRequirement::inputTypeOptions()[$requirement->input_type] ?? $requirement->input_type }}
                                </span>
                            </div>

                            @if (filled($requirement->instructions))
                                <p class="mt-3 text-sm text-neutral-600 dark:text-neutral-300">{{ $requirement->instructions }}</p>
                            @endif

                            @if ($requirement->submitted_at)
                                <p class="mt-2 text-xs text-neutral-500 dark:text-neutral-400">
                                    Ultima entrega: {{ $requirement->submitted_at->format('d/m/Y H:i') }}
                                </p>
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
                                        Guardar texto
                                    </flux:button>
                                    <x-action-message class="me-3" on="operadora-saved">
                                        Guardado.
                                    </x-action-message>
                                </div>
                            </form>
                        @else
                            <div class="space-y-4">
                                @if ($requirement->file_path)
                                    <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-neutral-700 dark:border-emerald-800/60 dark:bg-emerald-950/20 dark:text-neutral-300">
                                        PDF entregado correctamente.
                                    </div>
                                @endif

                                <form wire:submit="savePdfRequirement({{ $requirement->id }})" class="space-y-4">
                                    <input type="file" wire:model="pdfUploads.{{ $requirement->id }}" accept="application/pdf" class="block w-full text-sm text-neutral-700 dark:text-neutral-300" />

                                    <div class="flex items-center gap-3">
                                        <flux:button variant="primary" type="submit">
                                            Subir PDF
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
    </div>
</section>
