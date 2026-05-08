<?php

use App\Models\Operacion;
use App\Models\OperacionTramite;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Tramites aprobados')] class extends Component {
    public Operacion $operacion;

    public function mount(Operacion $operacion): void
    {
        $cliente = Auth::user()->cliente;

        abort_unless($cliente?->isUnblocked(), 403);

        $this->operacion = $cliente
            ->operaciones()
            ->with([
                'piloto',
                'dron',
                'tramites' => fn ($query) => $query
                    ->orderByRaw("case
                        when status = '".OperacionTramite::STATUS_PENDING."' then 1
                        when status = '".OperacionTramite::STATUS_PROCESSED."' then 2
                        when status = '".OperacionTramite::STATUS_DENIED."' then 3
                        when status = '".OperacionTramite::STATUS_APPROVED."' then 4
                        else 5
                    end")
                    ->latest('processed_at')
                    ->latest('updated_at'),
            ])
            ->findOrFail($operacion->getKey());

        abort_unless($this->operacion->isConfirmed(), 403);
    }

    #[Computed]
    public function tramites()
    {
        return $this->operacion->tramites;
    }

    protected function formatDateValue(mixed $value): string
    {
        return match (true) {
            $value instanceof \DateTimeInterface => $value->format('d/m/Y'),
            filled($value) => Carbon::parse((string) $value)->format('d/m/Y'),
            default => 'Sin definir',
        };
    }

    protected function operationAddressLabel(): string
    {
        return trim(collect([
            $this->operacion->address ?: $this->operacion->location,
            $this->operacion->city,
            $this->operacion->province,
            $this->operacion->postal_code,
            $this->operacion->country,
        ])->filter()->implode(', ')) ?: 'Sin definir';
    }

    protected function operationDronLabel(): string
    {
        $dronName = trim(($this->operacion->dron?->manufacturer_name ?? '').' '.($this->operacion->dron?->model ?? '')) ?: 'Sin dron';

        if (! $this->operacion->dron || (! filled($this->operacion->dron->registration_number) && ! $this->operacion->dron->registration_not_applicable)) {
            return $dronName;
        }

        return $dronName.' - '.$this->operacion->dron->registrationLabel();
    }

    protected function tramiteStatusLabel(OperacionTramite $tramite): string
    {
        return match ($tramite->status) {
            OperacionTramite::STATUS_PENDING => 'Pendiente',
            OperacionTramite::STATUS_PROCESSED => 'Tramitado',
            OperacionTramite::STATUS_DENIED => 'Denegado',
            OperacionTramite::STATUS_APPROVED => 'Aprobado',
            default => $tramite->statusLabel(),
        };
    }

    protected function tramiteBadgeClass(OperacionTramite $tramite): string
    {
        return match ($tramite->status) {
            OperacionTramite::STATUS_PENDING => 'portal-badge portal-badge--sky',
            OperacionTramite::STATUS_PROCESSED => 'portal-badge portal-badge--amber',
            OperacionTramite::STATUS_DENIED => 'portal-badge portal-badge--danger',
            OperacionTramite::STATUS_APPROVED => 'portal-badge portal-badge--emerald',
            default => 'portal-badge portal-badge--neutral',
        };
    }

    protected function tramiteCardClass(OperacionTramite $tramite): string
    {
        return match ($tramite->status) {
            OperacionTramite::STATUS_PENDING => 'portal-record-card portal-tramite-card portal-tramite-card--pending',
            OperacionTramite::STATUS_PROCESSED => 'portal-record-card portal-tramite-card portal-tramite-card--processed',
            OperacionTramite::STATUS_DENIED => 'portal-record-card portal-tramite-card portal-tramite-card--denied',
            OperacionTramite::STATUS_APPROVED => 'portal-record-card portal-tramite-card portal-tramite-card--approved',
            default => 'portal-record-card portal-tramite-card',
        };
    }

    protected function tramiteStatusMessage(OperacionTramite $tramite): string
    {
        return match ($tramite->status) {
            OperacionTramite::STATUS_PENDING => 'El tramite esta pendiente de gestion por parte del gestor.',
            OperacionTramite::STATUS_PROCESSED => 'El gestor ya esta tramitando este tramite.',
            OperacionTramite::STATUS_DENIED => 'Este tramite ha sido denegado por el gestor.',
            OperacionTramite::STATUS_APPROVED => 'Este tramite ya ha sido aprobado por el gestor.',
            default => 'Estado del tramite actualizado.',
        };
    }

    /**
     * @return array<int, array{name: string, url: string}>
     */
    protected function attachmentLinks(OperacionTramite $tramite): array
    {
        $attachments = array_values(array_filter((array) ($tramite->attachments ?? [])));
        $names = array_values((array) ($tramite->attachment_file_names ?? []));

        return collect($attachments)
            ->map(function (string $path, int $index) use ($names): array {
                return [
                    'name' => $names[$index] ?? basename($path),
                    'url' => Storage::disk('public')->url($path),
                ];
            })
            ->all();
    }
}; ?>

<section class="portal-page">
    <x-pages::settings.layout heading="" subheading="">
        <div class="portal-hero portal-hero--emerald">
            <div class="portal-hero__row">
                <div>
                    <p class="portal-hero__eyebrow text-emerald-700 dark:text-emerald-300">Operacion confirmada</p>
                    <h1 class="portal-hero__title">Tramites aprobados para operacion {{ $operacion->reference }}</h1>

                    <div class="portal-spec-grid portal-spec-grid--compact mt-5">
                        <div class="portal-spec-card portal-spec-card--featured">
                            <p class="portal-spec-card__label">Fecha</p>
                            <p class="portal-spec-card__value">{{ $this->formatDateValue($operacion->operation_date) }}</p>
                        </div>

                        <div class="portal-spec-card portal-spec-card--featured">
                            <p class="portal-spec-card__label">Hora / rodaje estimado</p>
                            <p class="portal-spec-card__value">{{ $operacion->estimated_filming_schedule ?: 'Sin definir' }}</p>
                        </div>

                        <div class="portal-spec-card portal-spec-card--featured">
                            <p class="portal-spec-card__label">Direccion</p>
                            <p class="portal-spec-card__value">{{ $this->operationAddressLabel() }}</p>
                        </div>
                    </div>
                </div>

                <flux:button as="a" variant="ghost" :href="route('operaciones.index')" wire:navigate>
                    Volver a operaciones
                </flux:button>
            </div>
        </div>

        <div class="portal-panel portal-panel--soft mt-6">
            <div class="portal-spec-grid portal-spec-grid--compact">
                <div class="portal-spec-card portal-spec-card--featured">
                    <p class="portal-spec-card__label">Piloto</p>
                    <p class="portal-spec-card__value">{{ $operacion->piloto?->fullName() ?? 'Sin piloto' }}</p>
                </div>

                <div class="portal-spec-card portal-spec-card--featured portal-spec-card--wide">
                    <p class="portal-spec-card__label">Dron</p>
                    <p class="portal-spec-card__value">{{ $this->operationDronLabel() }}</p>
                </div>
            </div>
        </div>

        @if ($this->tramites->isEmpty())
            <div class="portal-empty-state">
                <h2 class="text-lg font-semibold text-neutral-900 dark:text-white">Todavia no hay tramites para esta operacion</h2>
                <p class="mt-2 text-sm text-neutral-700 dark:text-neutral-300">
                    Cuando el gestor empiece a crear tramites para esta operacion, apareceran aqui con su estado y sus documentos.
                </p>
            </div>
        @else
            <div class="portal-record-list portal-record-list--relaxed">
                @foreach ($this->tramites as $tramite)
                    @php
                        $attachments = $this->attachmentLinks($tramite);
                    @endphp

                    <div class="{{ $this->tramiteCardClass($tramite) }}">
                        <div class="portal-record-card__header">
                            <div class="w-full">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h2 class="portal-record-card__title">{{ $tramite->title }}</h2>
                                    <span class="{{ $this->tramiteBadgeClass($tramite) }}">{{ $this->tramiteStatusLabel($tramite) }}</span>
                                </div>

                                <p class="portal-record-card__text">{{ $this->tramiteStatusMessage($tramite) }}</p>

                                @if ($attachments)
                                    <div class="mt-5 grid gap-4">
                                        @foreach ($attachments as $attachment)
                                            <div class="portal-file-card">
                                                <div>
                                                    <p class="text-sm font-semibold text-neutral-900 dark:text-white">{{ $attachment['name'] }}</p>
                                                    <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-300">Documento adjunto del tramite para esta operacion.</p>
                                                </div>

                                                <div class="flex flex-wrap gap-3">
                                                    <flux:button as="a" variant="ghost" :href="$attachment['url']" target="_blank">
                                                        Ver PDF
                                                    </flux:button>
                                                    <flux:button as="a" variant="filled" :href="$attachment['url']" download>
                                                        Descargar
                                                    </flux:button>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @else
                                    <div class="portal-file-card mt-5">
                                        <div>
                                            <p class="text-sm font-semibold text-neutral-900 dark:text-white">Falta colgar documentacion</p>
                                            <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-300">Cuando el gestor suba la documentacion de este tramite, podras verla y descargarla aqui.</p>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </x-pages::settings.layout>
</section>
