<?php

use App\Models\Operacion;
use App\Models\OperacionTramite;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
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
     * @return array<int, array{label: string, meta: string, completed: bool}>
     */
    protected function timelineSteps(): array
    {
        $tramitesCount = $this->tramites->count();
        $approvedCount = $this->tramites->where('status', OperacionTramite::STATUS_APPROVED)->count();

        return [
            [
                'label' => 'Solicitud creada',
                'meta' => optional($this->operacion->created_at)->format('d/m/Y') ?: 'Creada',
                'completed' => true,
            ],
            [
                'label' => 'Confirmacion gestor',
                'meta' => $this->operacion->isConfirmed() ? 'Operacion confirmada' : 'Pendiente',
                'completed' => $this->operacion->isConfirmed(),
            ],
            [
                'label' => 'Tramitando tramites',
                'meta' => $tramitesCount > 0
                    ? $tramitesCount.' '.($tramitesCount === 1 ? 'tramite creado' : 'tramites creados')
                    : 'Pendiente',
                'completed' => $this->hasTramitesCreated(),
            ],
            [
                'label' => 'Tramites aprobados',
                'meta' => $this->allTramitesApproved()
                    ? 'Todos aprobados'
                    : ($tramitesCount > 0 ? $approvedCount.' / '.$tramitesCount.' aprobados' : 'Pendiente'),
                'completed' => $this->allTramitesApproved(),
            ],
        ];
    }

    /**
     * @return array{pending: int, processed: int, denied: int, approved: int}
     */
    protected function tramiteStatusCounts(): array
    {
        return [
            'pending' => $this->tramites->where('status', OperacionTramite::STATUS_PENDING)->count(),
            'processed' => $this->tramites->where('status', OperacionTramite::STATUS_PROCESSED)->count(),
            'denied' => $this->tramites->where('status', OperacionTramite::STATUS_DENIED)->count(),
            'approved' => $this->tramites->where('status', OperacionTramite::STATUS_APPROVED)->count(),
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
            OperacionTramite::STATUS_PENDING => 'portal-tramites-row portal-tramites-row--pending',
            OperacionTramite::STATUS_PROCESSED => 'portal-tramites-row portal-tramites-row--processed',
            OperacionTramite::STATUS_DENIED => 'portal-tramites-row portal-tramites-row--denied',
            OperacionTramite::STATUS_APPROVED => 'portal-tramites-row portal-tramites-row--approved',
            default => 'portal-tramites-row',
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
     * @return array<int, array{name: string, view_url: string, download_url: string}>
     */
    protected function attachmentLinks(OperacionTramite $tramite): array
    {
        $attachments = array_values(array_filter((array) ($tramite->attachments ?? [])));
        $names = array_values((array) ($tramite->attachment_file_names ?? []));

        return collect($attachments)
            ->map(function (string $path, int $index) use ($tramite, $names): array {
                $parameters = [
                    'operacion' => $this->operacion,
                    'tramite' => $tramite,
                    'attachment' => $index,
                ];

                return [
                    'name' => $names[$index] ?? basename($path),
                    'view_url' => route('operaciones.tramites.documentos.show', $parameters),
                    'download_url' => route('operaciones.tramites.documentos.show', [
                        ...$parameters,
                        'download' => true,
                    ]),
                ];
            })
            ->all();
    }
}; ?>

<section class="portal-page portal-page--wide">
    <x-pages::settings.layout heading="" subheading="">
        @php
            $allApproved = $this->allTramitesApproved();
            $tramiteCounts = $this->tramiteStatusCounts();
        @endphp

        <div class="portal-hero portal-hero--client">
            <div class="portal-hero__row">
                <div class="portal-operations-hero__copy">
                    <p class="portal-hero__eyebrow text-sky-700 dark:text-sky-300">Portal cliente</p>
                    <h1 class="portal-hero__title">Tramites de operacion</h1>
                    <p class="portal-hero__text">
                        Consulta el estado documental de {{ $operacion->reference }} y descarga los PDFs disponibles.
                    </p>

                    <div class="portal-hero__actions">
                        <flux:button as="a" variant="primary" :href="route('operaciones.index')" wire:navigate>
                            Volver a operaciones
                        </flux:button>
                    </div>
                </div>

                <div class="portal-operations-hero__aside">
                    <div class="portal-hero__brand">
                        <img
                            src="{{ asset('images/logo-idronlex.png') }}"
                            alt="Idron Lex & Consulting"
                            class="portal-hero__logo"
                        >
                    </div>
                </div>
            </div>
        </div>

        <div class="portal-tramites-hero {{ $allApproved ? 'portal-tramites-hero--approved' : 'portal-tramites-hero--active' }}">
            <div class="portal-tramites-hero__header">
                <span class="portal-tramites-status">
                    <flux:icon icon="check-circle" variant="mini" class="size-4" />
                    Operacion confirmada
                </span>

                <h1 class="portal-tramites-hero__title">
                    <span>{{ $allApproved ? 'Tramites aprobados para la operacion:' : 'Tramites para la operacion:' }}</span>
                    {{ $operacion->reference }}
                </h1>
            </div>

            <div class="portal-tramites-info-grid">
                <div class="portal-tramites-info-card">
                    <span class="portal-tramites-info-card__icon">
                        <flux:icon icon="calendar-days" variant="mini" class="size-5" />
                    </span>
                    <span>
                        <small>Fecha</small>
                        <strong>{{ $this->formatDateValue($operacion->operation_date) }}</strong>
                    </span>
                </div>

                <div class="portal-tramites-info-card">
                    <span class="portal-tramites-info-card__icon">
                        <flux:icon icon="clock" variant="mini" class="size-5" />
                    </span>
                    <span>
                        <small>Hora / rodaje estimado</small>
                        <strong>{{ $operacion->estimated_filming_schedule ?: 'Sin definir' }}</strong>
                    </span>
                </div>

                <div class="portal-tramites-info-card">
                    <span class="portal-tramites-info-card__icon">
                        <flux:icon icon="map-pin" variant="mini" class="size-5" />
                    </span>
                    <span>
                        <small>Direccion</small>
                        <strong>{{ $this->operationAddressLabel() }}</strong>
                    </span>
                </div>
            </div>

            <div class="portal-tramites-timeline">
                @foreach ($this->timelineSteps() as $step)
                    <div class="portal-tramites-timeline__step {{ $step['completed'] ? 'portal-tramites-timeline__step--completed' : '' }}">
                        <div class="portal-tramites-timeline__track">
                            <span class="portal-tramites-timeline__circle">
                                @if ($step['completed'])
                                    <flux:icon icon="check" variant="micro" class="size-3.5" />
                                @else
                                    {{ $loop->iteration }}
                                @endif
                            </span>

                            @if (! $loop->last)
                                <span class="portal-tramites-timeline__line"></span>
                            @endif
                        </div>

                        <div class="portal-tramites-timeline__content">
                            <strong>{{ $step['label'] }}</strong>
                            <small>{{ $step['meta'] }}</small>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="portal-tramites-asset-grid">
            <div class="portal-tramites-asset-card">
                <span class="portal-tramites-asset-card__icon">
                    <flux:icon icon="user" variant="mini" class="size-5" />
                </span>

                <span class="portal-tramites-asset-card__content">
                    <small>Piloto</small>
                    <strong>{{ $operacion->piloto?->displayNameWithIdentification() ?? 'Sin piloto' }}</strong>
                </span>
            </div>

            <div class="portal-tramites-asset-card">
                <span class="portal-tramites-asset-card__icon">
                    <flux:icon icon="paper-airplane" variant="mini" class="size-5" />
                </span>

                <span class="portal-tramites-asset-card__content">
                    <small>Dron</small>
                    <strong>{{ $this->operationDronLabel() }}</strong>
                </span>
            </div>
        </div>

        <div class="portal-tramites-panel">
            <div class="portal-tramites-panel__header">
                <div>
                    <h2>Seguimiento de tramites</h2>
                    <p>Aqui puedes consultar el estado de cada tramite y descargar la documentacion disponible.</p>
                </div>

                <div class="portal-tramites-summary">
                    <span class="portal-tramites-summary__item portal-tramites-summary__item--pending">Pendientes <strong>{{ $tramiteCounts['pending'] }}</strong></span>
                    <span class="portal-tramites-summary__item portal-tramites-summary__item--processed">Tramitados <strong>{{ $tramiteCounts['processed'] }}</strong></span>
                    <span class="portal-tramites-summary__item portal-tramites-summary__item--denied">Denegados <strong>{{ $tramiteCounts['denied'] }}</strong></span>
                    <span class="portal-tramites-summary__item portal-tramites-summary__item--approved">Aprobados <strong>{{ $tramiteCounts['approved'] }}</strong></span>
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
                <div class="portal-tramites-list">
                    @foreach ($this->tramites as $tramite)
                        @php
                            $attachments = $this->attachmentLinks($tramite);
                            $missingDocumentTitle = match ($tramite->status) {
                                OperacionTramite::STATUS_DENIED => 'Tramite denegado',
                                OperacionTramite::STATUS_APPROVED => 'La documentacion aparecera aqui',
                                default => 'Falta colgar documentacion',
                            };
                            $missingDocumentText = $tramite->status === OperacionTramite::STATUS_DENIED
                                ? 'Este tramite ha sido denegado por el gestor. Si hay documentacion asociada, aparecera aqui cuando este disponible.'
                                : 'Cuando el gestor suba la documentacion de este tramite, podras verla y descargarla aqui.';
                        @endphp

                        <article class="{{ $this->tramiteCardClass($tramite) }}">
                            <div class="portal-tramites-row__main">
                                <span class="portal-tramites-row__icon">
                                    @switch($tramite->status)
                                        @case(OperacionTramite::STATUS_APPROVED)
                                            <flux:icon icon="check-circle" variant="mini" class="size-5" />
                                            @break

                                        @case(OperacionTramite::STATUS_DENIED)
                                            <flux:icon icon="x-circle" variant="mini" class="size-5" />
                                            @break

                                        @case(OperacionTramite::STATUS_PROCESSED)
                                            <flux:icon icon="clock" variant="mini" class="size-5" />
                                            @break

                                        @default
                                            <flux:icon icon="clock" variant="mini" class="size-5" />
                                    @endswitch
                                </span>

                                <div class="portal-tramites-row__copy">
                                    <div class="portal-tramites-row__title-line">
                                        <h3>{{ $tramite->title }}</h3>
                                        <span class="{{ $this->tramiteBadgeClass($tramite) }}">{{ $this->tramiteStatusLabel($tramite) }}</span>
                                    </div>
                                    <p>{{ $this->tramiteStatusMessage($tramite) }}</p>
                                </div>
                            </div>

                            <div class="portal-tramites-row__documents">
                                @if ($attachments)
                                    <div class="portal-tramites-file-list">
                                        @foreach ($attachments as $attachment)
                                            <div class="portal-tramites-file">
                                                <span class="portal-tramites-file__icon">PDF</span>
                                                <strong>{{ $attachment['name'] }}</strong>

                                                <div class="portal-tramites-file__actions">
                                                    <a href="{{ $attachment['view_url'] }}" target="_blank" rel="noopener noreferrer" class="portal-tramites-file-action portal-tramites-file-action--ghost">
                                                        Ver PDF
                                                    </a>
                                                    <a href="{{ $attachment['download_url'] }}" class="portal-tramites-file-action portal-tramites-file-action--primary">
                                                        Descargar
                                                    </a>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @else
                                    <div class="portal-tramites-empty-doc">
                                        <span>
                                            <flux:icon icon="folder-open" variant="mini" class="size-5" />
                                        </span>
                                        <div>
                                            <strong>{{ $missingDocumentTitle }}</strong>
                                            <p>{{ $missingDocumentText }}</p>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </div>
    </x-pages::settings.layout>
</section>
