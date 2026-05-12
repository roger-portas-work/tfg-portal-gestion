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
        $dronName = $this->operacion->dron?->displayNameWithSerial() ?? 'Sin dron';

        if (! $this->operacion->dron) {
            return $dronName;
        }

        return $dronName;
    }

    protected function pilotoVerified(): bool
    {
        $piloto = $this->operacion->piloto;

        return filled($piloto?->fullName()) && filled($piloto?->pilot_identification_number);
    }

    /**
     * @return array{label: string, class: string}|null
     */
    protected function dronChip(): ?array
    {
        $dron = $this->operacion->dron;

        if (! $dron) {
            return null;
        }

        if ($dron->registration_not_applicable) {
            return [
                'label' => 'Matricula: No aplica',
                'class' => 'portal-chip portal-chip--neutral',
            ];
        }

        if (filled($dron->registration_number)) {
            return [
                'label' => 'Matricula: '.$dron->registrationLabel(),
                'class' => 'portal-chip portal-chip--success',
            ];
        }

        return null;
    }

    protected function allTramitesApproved(): bool
    {
        return $this->tramites->isNotEmpty()
            && $this->tramites->every(fn (OperacionTramite $tramite): bool => $tramite->status === OperacionTramite::STATUS_APPROVED);
    }

    protected function hasTramitesCreated(): bool
    {
        return $this->tramites->isNotEmpty();
    }

    /**
     * @return array<int, array{label: string, date: ?string, completed: bool}>
     */
    protected function timelineSteps(): array
    {
        return [
            [
                'label' => 'Solicitud creada',
                'date' => optional($this->operacion->created_at)->format('d/m/Y'),
                'completed' => true,
            ],
            [
                'label' => 'Confirmacion gestor',
                'date' => null,
                'completed' => $this->operacion->isConfirmed(),
            ],
            [
                'label' => 'Tramitando tramites',
                'date' => null,
                'completed' => $this->hasTramitesCreated(),
            ],
            [
                'label' => 'Tramites aprobados',
                'date' => null,
                'completed' => $this->allTramitesApproved(),
            ],
        ];
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

<section class="portal-page portal-page--wide">
    <x-pages::settings.layout heading="" subheading="">
        <div class="flex justify-end">
            <flux:button as="a" variant="primary" :href="route('operaciones.index')" wire:navigate>
                Volver a operaciones
            </flux:button>
        </div>

        <div class="portal-panel portal-operation-overview mt-4">
            <div class="portal-operation-overview__header">
                <span class="portal-operation-overview__status">
                    <flux:icon icon="check-circle" variant="mini" class="size-4" />
                    Operacion confirmada
                </span>

                    <h1 class="portal-operation-overview__title">
                        <span class="block">Tramites aprobados para la operacion:</span>
                        <span class="block">{{ $operacion->reference }}</span>
                    </h1>
            </div>

            <div class="portal-operation-overview__stats">
                <div class="portal-operation-overview__stat">
                    <div class="portal-operation-overview__icon-wrap">
                        <flux:icon icon="calendar-days" variant="mini" class="portal-operation-overview__icon" />
                    </div>
                    <div>
                        <p class="portal-operation-overview__label">Fecha</p>
                        <p class="portal-operation-overview__value">{{ $this->formatDateValue($operacion->operation_date) }}</p>
                    </div>
                </div>

                <div class="portal-operation-overview__stat">
                    <div class="portal-operation-overview__icon-wrap">
                        <flux:icon icon="clock" variant="mini" class="portal-operation-overview__icon" />
                    </div>
                    <div>
                        <p class="portal-operation-overview__label">Hora / rodaje estimado</p>
                        <p class="portal-operation-overview__value">{{ $operacion->estimated_filming_schedule ?: 'Sin definir' }}</p>
                    </div>
                </div>

                <div class="portal-operation-overview__stat">
                    <div class="portal-operation-overview__icon-wrap">
                        <flux:icon icon="map-pin" variant="mini" class="portal-operation-overview__icon" />
                    </div>
                    <div>
                        <p class="portal-operation-overview__label">Direccion</p>
                        <p class="portal-operation-overview__value">{{ $this->operationAddressLabel() }}</p>
                    </div>
                </div>
            </div>

            <div class="portal-operation-progress">
                @foreach ($this->timelineSteps() as $step)
                    <div class="portal-operation-progress__step">
                        <div class="portal-operation-progress__track">
                            <span class="portal-operation-progress__circle {{ $step['completed'] ? 'portal-operation-progress__circle--completed' : '' }}">
                                @if ($step['completed'])
                                    <flux:icon icon="check" variant="micro" class="size-3.5" />
                                @else
                                    {{ $loop->iteration }}
                                @endif
                            </span>

                            @if (! $loop->last)
                                <span class="portal-operation-progress__line {{ $step['completed'] ? 'portal-operation-progress__line--completed' : '' }}"></span>
                            @endif
                        </div>

                        <div class="portal-operation-progress__content">
                            <p class="portal-operation-progress__label">{{ $step['label'] }}</p>
                            @if ($step['date'])
                                <p class="portal-operation-progress__meta">{{ $step['date'] }}</p>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="portal-panel portal-panel--soft mt-6">
            <div class="portal-operation-assets">
                <div class="portal-operation-asset">
                    <div class="portal-operation-asset__icon-wrap">
                        <flux:icon icon="user" variant="mini" class="portal-operation-asset__icon" />
                    </div>

                    <div class="portal-operation-asset__content">
                        <p class="portal-operation-overview__label">Piloto</p>
                        <p class="portal-operation-overview__value">{{ $operacion->piloto?->displayNameWithIdentification() ?? 'Sin piloto' }}</p>
                    </div>

                    @if ($this->pilotoVerified())
                        <span class="portal-chip portal-chip--success">Verificado</span>
                    @endif
                </div>

                <div class="portal-operation-asset">
                    <div class="portal-operation-asset__icon-wrap">
                        <flux:icon icon="paper-airplane" variant="mini" class="portal-operation-asset__icon" />
                    </div>

                    <div class="portal-operation-asset__content">
                        <p class="portal-operation-overview__label">Dron</p>
                        <p class="portal-operation-overview__value">{{ $this->operationDronLabel() }}</p>
                    </div>

                    @if ($this->dronChip())
                        <span class="{{ $this->dronChip()['class'] }}">{{ $this->dronChip()['label'] }}</span>
                    @endif
                </div>
            </div>
        </div>

        <div class="portal-panel mt-6">
            <div class="portal-operation-tramites__header">
                <div>
                    <p class="portal-hero__eyebrow text-emerald-700 dark:text-emerald-300">Seguimiento de tramites</p>
                    <h2 class="portal-form-title">Tramites de la operacion</h2>
                    <p class="portal-form-text">Aqui puedes consultar el estado de cada tramite y descargar la documentacion disponible.</p>
                </div>
            </div>

            @if ($this->tramites->isEmpty())
                <div class="portal-empty-state portal-operation-tramites__empty">
                    <div class="portal-operation-tramites__empty-icon">
                        <flux:icon icon="folder-open" variant="mini" class="size-7" />
                    </div>
                    <h3 class="text-lg font-semibold text-neutral-900 dark:text-white">Todavia no hay tramites para esta operacion</h3>
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
        </div>
    </x-pages::settings.layout>
</section>
