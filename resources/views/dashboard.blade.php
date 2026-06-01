<x-layouts::app :title="__('Dashboard')">
    @php
        $user = auth()->user();
        $cliente = $user?->cliente;
        $isClientePortal = $user?->role === \App\Models\User::ROLE_CLIENTE && $cliente;
    @endphp

    @if ($isClientePortal)
        @php
            $profileCompleted = $cliente->profile_completed;
            $hasDrones = $cliente->drones()->exists();
            $dronesCount = $cliente->drones()->count();
            $isUnblocked = $cliente->isUnblocked();
            $pilotosCount = $cliente->pilotos()->count();
            $pendingOperaciones = $cliente->pendingOperacionesCount();
            $rejectedOperaciones = $cliente->rejectedOperacionesCount();
            $confirmedOperaciones = $cliente->confirmedOperacionesCount();
            $operadoraRequirements = $cliente->operadoraRequirements()->get();
            $requiredOperadoraRequirements = $operadoraRequirements->filter(fn ($requirement): bool => (bool) $requirement->is_required);
            $operadoraClientActionStatuses = [
                \App\Models\OperadoraRequirement::STATUS_PENDING,
                \App\Models\OperadoraRequirement::STATUS_NEEDS_CHANGES,
            ];
            $pendingOperadora = $operadoraRequirements->filter(fn ($requirement): bool => in_array($requirement->status, $operadoraClientActionStatuses, true))->count();
            $pendingRequiredOperadora = $requiredOperadoraRequirements->filter(fn ($requirement): bool => in_array($requirement->status, $operadoraClientActionStatuses, true))->count();
            $pendingOptionalOperadora = $operadoraRequirements->filter(fn ($requirement): bool => ! $requirement->is_required && in_array($requirement->status, $operadoraClientActionStatuses, true))->count();
            $pendingUploadOperadora = $requiredOperadoraRequirements->filter(fn ($requirement): bool => $requirement->status === \App\Models\OperadoraRequirement::STATUS_PENDING)->count();
            $inReviewOperadora = $requiredOperadoraRequirements->filter(fn ($requirement): bool => $requirement->status === \App\Models\OperadoraRequirement::STATUS_IN_REVIEW)->count();
            $needsChangesOperadora = $requiredOperadoraRequirements->filter(fn ($requirement): bool => $requirement->status === \App\Models\OperadoraRequirement::STATUS_NEEDS_CHANGES)->count();
            $completedOperadora = $requiredOperadoraRequirements->filter(fn ($requirement): bool => $requirement->status === \App\Models\OperadoraRequirement::STATUS_APPROVED)->count();
            $completedRequiredOperadora = $requiredOperadoraRequirements->filter(fn ($requirement): bool => $requirement->status === \App\Models\OperadoraRequirement::STATUS_APPROVED)->count();
            $completedOnboardingSteps = collect([$profileCompleted, $hasDrones])->filter()->count();
            $onboardingProgress = (int) (($completedOnboardingSteps / 2) * 100);
            $operacionesCount = $cliente->operaciones()->count();
            $totalOperadora = $operadoraRequirements->count();
            $totalRequiredOperadora = $requiredOperadoraRequirements->count();
            $operadoraProgress = $totalRequiredOperadora > 0 ? (int) round(($completedRequiredOperadora / $totalRequiredOperadora) * 100) : 100;
            $canCreateOperations = $dronesCount > 0 && $pilotosCount > 0;
            $createOperationHref = $canCreateOperations ? route('operaciones.index', ['crear' => 1]) : route('pilotos.index');
            $createOperationLabel = $canCreateOperations ? 'Crear operacion' : 'Anadir piloto';
            $createOperationText = $canCreateOperations ? 'Nueva solicitud' : 'Necesitas un piloto para crear operaciones';
            $upcomingOperacionesCount = $cliente->operaciones()
                ->whereDate('operation_date', '>=', now()->toDateString())
                ->count();
            $upcomingDocumentationLimit = now()->addDays(30)->toDateString();
            $confirmedUpcomingForAlerts = $cliente->operaciones()
                ->withCount([
                    'tramites',
                    'tramites as approved_tramites_count' => fn ($query) => $query->where('status', \App\Models\OperacionTramite::STATUS_APPROVED),
                ])
                ->where('status', \App\Models\Operacion::STATUS_CONFIRMED)
                ->whereDate('operation_date', '>=', now()->toDateString())
                ->whereDate('operation_date', '<=', $upcomingDocumentationLimit)
                ->orderBy('operation_date')
                ->get();
            $upcomingIncompleteDocumentationOperations = $confirmedUpcomingForAlerts
                ->filter(fn ($operacion): bool => (int) ($operacion->tramites_count ?? 0) > 0 && (int) ($operacion->approved_tramites_count ?? 0) < (int) ($operacion->tramites_count ?? 0))
                ->values();
            $upcomingConfirmedWithoutTramites = $confirmedUpcomingForAlerts
                ->filter(fn ($operacion): bool => (int) ($operacion->tramites_count ?? 0) === 0)
                ->values();
            $totalPendingTramites = \App\Models\OperacionTramite::query()
                ->whereHas('operacion', fn ($query) => $query->where('cliente_id', $cliente->id))
                ->where('status', \App\Models\OperacionTramite::STATUS_PENDING)
                ->count();
            $totalProcessedTramites = \App\Models\OperacionTramite::query()
                ->whereHas('operacion', fn ($query) => $query->where('cliente_id', $cliente->id))
                ->where('status', \App\Models\OperacionTramite::STATUS_PROCESSED)
                ->count();
            $totalDeniedTramites = \App\Models\OperacionTramite::query()
                ->whereHas('operacion', fn ($query) => $query->where('cliente_id', $cliente->id))
                ->where('status', \App\Models\OperacionTramite::STATUS_DENIED)
                ->count();
            $totalApprovedTramites = \App\Models\OperacionTramite::query()
                ->whereHas('operacion', fn ($query) => $query->where('cliente_id', $cliente->id))
                ->where('status', \App\Models\OperacionTramite::STATUS_APPROVED)
                ->count();
            $dronesWithInsuranceAttention = $cliente->drones()
                ->whereNotNull('insurance_valid_until')
                ->whereDate('insurance_valid_until', '<=', now()->addDays(30)->toDateString())
                ->orderBy('insurance_valid_until')
                ->get();
            $pendingAesaDrones = $cliente->drones()
                ->where('aesa_registration_status', \App\Models\Dron::AESA_STATUS_MANAGER)
                ->latest()
                ->get();
            $createDronHref = route('drones.index', ['crear' => 1]);
            $createPilotoHref = route('pilotos.index', ['crear' => 1]);
            $requiredOperadoraAttentionTasks = $requiredOperadoraRequirements
                ->filter(fn ($requirement): bool => in_array($requirement->status, $operadoraClientActionStatuses, true))
                ->sortBy(function ($requirement): string {
                    $statusOrder = [
                        \App\Models\OperadoraRequirement::STATUS_NEEDS_CHANGES => 0,
                        \App\Models\OperadoraRequirement::STATUS_PENDING => 1,
                    ];

                    return sprintf(
                        '%d-%010d',
                        $statusOrder[$requirement->status] ?? 9,
                        $requirement->id
                    );
                })
                ->values();
            $dashboardOperadoraTasks = $requiredOperadoraRequirements
                ->filter(fn ($requirement): bool => in_array($requirement->status, $operadoraClientActionStatuses, true))
                ->sortBy(function ($requirement): string {
                    $statusOrder = [
                        \App\Models\OperadoraRequirement::STATUS_NEEDS_CHANGES => 0,
                        \App\Models\OperadoraRequirement::STATUS_PENDING => 1,
                    ];

                    return sprintf(
                        '%d-%d-%010d',
                        $requirement->is_required ? 0 : 1,
                        $statusOrder[$requirement->status] ?? 9,
                        $requirement->id
                    );
                })
                ->take(3);
            $upcomingOperaciones = $cliente->operaciones()
                ->with(['piloto', 'dron'])
                ->withCount([
                    'tramites',
                    'tramites as pending_tramites_count' => fn ($query) => $query->where('status', \App\Models\OperacionTramite::STATUS_PENDING),
                    'tramites as processed_tramites_count' => fn ($query) => $query->where('status', \App\Models\OperacionTramite::STATUS_PROCESSED),
                    'tramites as denied_tramites_count' => fn ($query) => $query->where('status', \App\Models\OperacionTramite::STATUS_DENIED),
                    'tramites as approved_tramites_count' => fn ($query) => $query->where('status', \App\Models\OperacionTramite::STATUS_APPROVED),
                ])
                ->whereDate('operation_date', '>=', now()->toDateString())
                ->orderBy('operation_date')
                ->limit(3)
                ->get();
            $dashboardAlerts = [];

            if ($upcomingIncompleteDocumentationOperations->isNotEmpty()) {
                $firstOperation = $upcomingIncompleteDocumentationOperations->first();

                $dashboardAlerts[] = [
                    'tone' => 'danger',
                    'icon' => 'exclamation-triangle',
                    'title' => $upcomingIncompleteDocumentationOperations->count().' '.($upcomingIncompleteDocumentationOperations->count() === 1 ? 'operacion con documentacion incompleta' : 'operaciones con documentacion incompleta'),
                    'text' => 'confirmadas en los proximos 30 dias',
                    'action' => 'Ver operacion',
                    'href' => route('operaciones.index').'#operacion-'.$firstOperation->id,
                ];
            }

            if ($upcomingConfirmedWithoutTramites->isNotEmpty()) {
                $firstOperation = $upcomingConfirmedWithoutTramites->first();

                $dashboardAlerts[] = [
                    'tone' => 'warning',
                    'icon' => 'folder-open',
                    'title' => $upcomingConfirmedWithoutTramites->count().' '.($upcomingConfirmedWithoutTramites->count() === 1 ? 'operacion confirmada sin tramites' : 'operaciones confirmadas sin tramites'),
                    'text' => 'pendientes de gestion documental',
                    'action' => 'Ver operacion',
                    'href' => route('operaciones.index').'#operacion-'.$firstOperation->id,
                ];
            }

            if ($dronesWithInsuranceAttention->isNotEmpty()) {
                $firstDron = $dronesWithInsuranceAttention->first();

                $dashboardAlerts[] = [
                    'tone' => 'amber',
                    'icon' => 'exclamation-triangle',
                    'title' => $dronesWithInsuranceAttention->count().' '.($dronesWithInsuranceAttention->count() === 1 ? 'seguro de dron por revisar' : 'seguros de dron por revisar'),
                    'text' => 'vencidos o proximos a vencer',
                    'action' => 'Ver dron',
                    'href' => route('drones.index').'#dron-'.$firstDron->id,
                ];
            }

            if ($pendingAesaDrones->isNotEmpty()) {
                $firstDron = $pendingAesaDrones->first();

                $dashboardAlerts[] = [
                    'tone' => 'sky',
                    'icon' => 'paper-airplane',
                    'title' => $pendingAesaDrones->count().' '.($pendingAesaDrones->count() === 1 ? 'dron pendiente de registro AESA' : 'drones pendientes de registro AESA'),
                    'text' => 'gestionados por el gestor',
                    'action' => 'Ver dron',
                    'href' => route('drones.index').'#dron-'.$firstDron->id,
                ];
            }

            if ($pendingRequiredOperadora > 0) {
                $firstRequirement = $requiredOperadoraAttentionTasks->first();

                $dashboardAlerts[] = [
                    'tone' => 'amber',
                    'icon' => 'folder',
                    'title' => $pendingRequiredOperadora.' '.($pendingRequiredOperadora === 1 ? 'obligatorio pendiente' : 'obligatorios pendientes'),
                    'text' => 'en documentacion de operadora',
                    'action' => 'Revisar documentacion',
                    'href' => $firstRequirement ? route('operadora.index').'#requisito-operadora-'.$firstRequirement->id : route('operadora.index'),
                ];
            }

            if ($dashboardAlerts === []) {
                $dashboardAlerts[] = [
                    'tone' => 'success',
                    'icon' => 'check-circle',
                    'title' => 'Portal al dia',
                    'text' => 'sin avisos importantes ahora mismo',
                    'action' => 'Ver operaciones',
                    'href' => route('operaciones.index'),
                ];
            }
        @endphp

        <div class="portal-page">
            @if (! $isUnblocked)
                <div class="portal-hero portal-hero--danger">
                    <div class="portal-dashboard-split">
                        <div class="portal-dashboard-main">
                            <div class="flex flex-col gap-8 md:gap-10">
                                <div class="flex flex-wrap items-center gap-4">
                                    <span class="portal-chip portal-chip--danger">
                                        Portal bloqueado
                                    </span>
                                    <span class="portal-chip portal-chip--neutral">
                                        {{ $completedOnboardingSteps }}/2 pasos completados
                                    </span>
                                </div>

                                <div class="space-y-5">
                                    <p class="portal-hero__eyebrow text-red-700 dark:text-red-300">Portal cliente</p>
                                    <h1 class="text-4xl font-semibold text-neutral-900 dark:text-white">
                                        Hola, {{ $cliente->fullName() ?: $user->name }}
                                    </h1>
                                    <p class="max-w-2xl text-lg leading-8 text-neutral-700 dark:text-neutral-300">
                                        {{ $profileCompleted
                                            ? 'Tu ficha ya esta completada. Ahora registra tu primer dron para terminar de activar el portal.'
                                            : 'Completa tu ficha para activar tu portal y poder registrar tu primer dron.' }}
                                    </p>
                                </div>

                                <div class="mt-3">
                                    <div class="flex items-center justify-between text-sm font-medium text-neutral-700 dark:text-neutral-300">
                                        <span>Progreso de activacion</span>
                                        <span class="portal-chip portal-chip--danger">
                                            {{ $onboardingProgress }}%
                                        </span>
                                    </div>

                                    <div class="mt-3">
                                        <x-ui.progress-bar
                                            :value="$onboardingProgress"
                                            height="12px"
                                            track-color="#fee2e2"
                                            fill-color="linear-gradient(90deg, #f97316 0%, #ef4444 100%)"
                                        />
                                    </div>
                                </div>

                                <div class="mt-4 flex flex-wrap items-center gap-4">
                                    <flux:button
                                        as="a"
                                        variant="primary"
                                        :href="$profileCompleted ? route('drones.index') : route('profile.edit')"
                                        wire:navigate
                                    >
                                        {{ $profileCompleted ? 'Registrar mi primer dron' : 'Completar mi ficha' }}
                                    </flux:button>

                                    <span class="portal-chip portal-chip--warning">
                                        {{ $profileCompleted ? 'Siguiente accion: dron' : 'Accion requerida ahora' }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="portal-dashboard-aside">
                            <div class="portal-panel portal-panel--soft">
                                <p class="text-base font-semibold text-neutral-900 dark:text-white">Pasos para activar tu portal</p>
                                <p class="mt-2 text-sm text-neutral-600 dark:text-neutral-300">
                                    Sigue este orden para desbloquear el resto del portal.
                                </p>
                            </div>

                            <div class="{{ $profileCompleted ? 'portal-step-card portal-step-card--complete' : 'portal-step-card portal-step-card--pending' }}">
                                <div class="portal-step-card__row">
                                    <div class="portal-step-card__lead">
                                        <div class="portal-step-card__icon {{ $profileCompleted ? 'bg-emerald-600 text-white shadow-sm shadow-emerald-300/70 dark:bg-emerald-500 dark:text-white' : 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-200' }}">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 9V5.25A2.25 2.25 0 0 1 10.5 3h6a2.25 2.25 0 0 1 2.25 2.25v13.5A2.25 2.25 0 0 1 16.5 21h-6a2.25 2.25 0 0 1-2.25-2.25V15M12 9l3 3m0 0-3 3m3-3H2.25" />
                                            </svg>
                                        </div>
                                        <div>
                                            <p class="portal-step-card__title">Completar ficha del cliente</p>
                                            <p class="portal-step-card__text">
                                                {{ $profileCompleted ? 'Ficha completada correctamente.' : 'Anade tus datos personales y la informacion base para activar el portal.' }}
                                            </p>
                                        </div>
                                    </div>

                                    <span class="{{ $profileCompleted ? 'portal-chip portal-chip--success' : 'portal-chip portal-chip--warning' }}">
                                        {{ $profileCompleted ? 'Completada' : 'Pendiente' }}
                                    </span>
                                </div>

                                @if (! $profileCompleted)
                                    <div class="portal-step-card__footer">
                                        <flux:button as="a" variant="primary" :href="route('profile.edit')" wire:navigate>
                                            Completar ahora
                                        </flux:button>
                                    </div>
                                @endif
                            </div>

                            <div class="portal-step-card portal-step-card--muted">
                                <div class="portal-step-card__row">
                                    <div class="portal-step-card__lead">
                                        <div class="portal-step-card__icon bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-200">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 9V5.25A2.25 2.25 0 0 1 10.5 3h6a2.25 2.25 0 0 1 2.25 2.25v13.5A2.25 2.25 0 0 1 16.5 21h-6a2.25 2.25 0 0 1-2.25-2.25V15M12 9l3 3m0 0-3 3m3-3H2.25" />
                                            </svg>
                                        </div>
                                        <div>
                                            <p class="portal-step-card__title">Registrar 1 dron</p>
                                            <p class="portal-step-card__text">
                                                {{ $profileCompleted
                                                    ? 'Ya puedes registrar tu primer dron para desbloquear el resto del portal.'
                                                    : 'Este paso se activara cuando tu ficha del cliente este completada.' }}
                                            </p>
                                        </div>
                                    </div>

                                    <span class="{{ $profileCompleted ? 'portal-chip portal-chip--warning' : 'portal-chip portal-chip--danger' }}">
                                        {{ $profileCompleted ? 'Pendiente' : 'Bloqueado' }}
                                    </span>
                                </div>

                                @if ($profileCompleted)
                                    <div class="portal-step-card__footer">
                                        <flux:button as="a" variant="primary" :href="route('drones.index')" wire:navigate>
                                            Registrar ahora
                                        </flux:button>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @else
                <div class="portal-dashboard-unlocked">
                    <div class="portal-dashboard-topbar">
                        <div>
                            <h1 class="portal-dashboard-title">
                                Hola, {{ $cliente->fullName() ?: $user->name }}
                            </h1>
                            <p class="portal-dashboard-subtitle">
                                Este es el estado de tu portal y tus proximas operaciones.
                            </p>
                        </div>

                        <div class="portal-dashboard-statusbar">
                            <span class="portal-dashboard-status portal-dashboard-status--success">
                                <flux:icon icon="check-circle" variant="mini" class="size-4" />
                                Portal activo
                            </span>
                            <span class="portal-dashboard-status portal-dashboard-status--sky">
                                <flux:icon icon="calendar-days" variant="mini" class="size-4" />
                                {{ $upcomingOperacionesCount }} {{ $upcomingOperacionesCount === 1 ? 'operacion proxima' : 'operaciones proximas' }}
                            </span>
                            <span class="portal-dashboard-status {{ $pendingOperadora > 0 ? 'portal-dashboard-status--warning' : 'portal-dashboard-status--success' }}">
                                <flux:icon icon="{{ $pendingOperadora > 0 ? 'exclamation-triangle' : 'check-circle' }}" variant="mini" class="size-4" />
                                {{ $pendingOperadora > 0 ? 'Operadora: requiere atencion' : 'Operadora al dia' }}
                            </span>
                            <flux:button as="a" variant="primary" :href="$createOperationHref" wire:navigate>
                                <flux:icon icon="plus" variant="mini" class="size-4" />
                                {{ $createOperationLabel }}
                            </flux:button>
                        </div>
                    </div>

                    <div class="portal-dashboard-alert-grid">
                        @foreach ($dashboardAlerts as $alert)
                            <a href="{{ $alert['href'] }}" class="portal-dashboard-alert portal-dashboard-alert--{{ $alert['tone'] }}">
                                <span class="portal-dashboard-alert__icon">
                                    <flux:icon icon="{{ $alert['icon'] }}" variant="mini" class="size-5" />
                                </span>
                                <span class="portal-dashboard-alert__body">
                                    <strong>{{ $alert['title'] }}</strong>
                                    <small>{{ $alert['text'] }}</small>
                                    <span>{{ $alert['action'] }} &rarr;</span>
                                </span>
                            </a>
                        @endforeach
                    </div>

                    <div class="portal-dashboard-workspace">
                        <section class="portal-panel portal-dashboard-operations-panel">
                            <div class="portal-dashboard-section-header">
                                <div class="portal-dashboard-section-header__lead">
                                    <span class="portal-dashboard-section-icon portal-dashboard-section-icon--sky">
                                        <flux:icon icon="calendar-days" variant="mini" class="size-5" />
                                    </span>
                                    <div>
                                        <h2 class="portal-dashboard-section-header__title">Proximas operaciones</h2>
                                        <p class="portal-dashboard-section-header__text">Sigue el estado de tus operaciones y su documentacion asociada.</p>
                                    </div>
                                </div>
                                <flux:button as="a" variant="primary" :href="route('operaciones.index')" wire:navigate>
                                    Ver todas las operaciones
                                </flux:button>
                            </div>

                            <div class="portal-dashboard-operation-list">
                                @forelse ($upcomingOperaciones as $operacion)
                                    @php
                                        $operationDateValue = $operacion->operation_date instanceof \DateTimeInterface
                                            ? \Illuminate\Support\Carbon::instance($operacion->operation_date)
                                            : (filled($operacion->operation_date) ? \Illuminate\Support\Carbon::parse((string) $operacion->operation_date) : null);
                                        $operationMonthNames = [1 => 'ENE', 2 => 'FEB', 3 => 'MAR', 4 => 'ABR', 5 => 'MAY', 6 => 'JUN', 7 => 'JUL', 8 => 'AGO', 9 => 'SEP', 10 => 'OCT', 11 => 'NOV', 12 => 'DIC'];
                                        $operationDay = $operationDateValue?->format('d') ?? '--';
                                        $operationMonth = $operationDateValue ? $operationMonthNames[(int) $operationDateValue->format('n')] : '';
                                        $operationAddress = trim(collect([
                                            $operacion->address ?: $operacion->location,
                                            $operacion->city,
                                            $operacion->province,
                                        ])->filter()->implode(', ')) ?: 'Sin direccion';
                                        $tramitesCount = (int) ($operacion->tramites_count ?? 0);
                                        $pendingTramites = (int) ($operacion->pending_tramites_count ?? 0);
                                        $processedTramites = (int) ($operacion->processed_tramites_count ?? 0);
                                        $deniedTramites = (int) ($operacion->denied_tramites_count ?? 0);
                                        $approvedTramites = (int) ($operacion->approved_tramites_count ?? 0);
                                        $operationDron = trim(($operacion->dron?->manufacturer_name ?? '').' '.($operacion->dron?->model ?? '')) ?: 'Sin dron';
                                        $operationDronSerial = filled($operacion->dron?->drone_serial_number)
                                            ? $operacion->dron->drone_serial_number
                                            : 'Sin numero de serie';
                                        $operationProcessesCompleted = $operacion->isConfirmed() && $tramitesCount > 0 && $approvedTramites === $tramitesCount;
                                        $operationTone = $operationProcessesCompleted
                                            ? 'completed'
                                            : ($operacion->isConfirmed()
                                                ? 'confirmed'
                                                : ($operacion->isRejected() ? 'rejected' : 'pending'));
                                        $operationBadgeClass = $operationProcessesCompleted || $operacion->isConfirmed()
                                            ? 'portal-badge--emerald'
                                            : ($operacion->isRejected() ? 'portal-badge--danger' : 'portal-badge--amber');
                                        $operationNotice = $operacion->isRejected()
                                            ? 'Operacion rechazada'
                                            : ($operacion->isPending()
                                                ? 'Pendiente de confirmacion'
                                                : ($deniedTramites > 0
                                                    ? $deniedTramites.' '.($deniedTramites === 1 ? 'tramite denegado' : 'tramites denegados')
                                                    : ($operationProcessesCompleted ? 'Tramites aprobados' : 'Documentacion en curso')));
                                        $operationActionHref = $operacion->isConfirmed()
                                            ? route('operaciones.tramites-aprobados', $operacion)
                                            : route('operaciones.index').'#operacion-'.$operacion->id;
                                        $operationActionLabel = $operacion->isConfirmed()
                                            ? ($deniedTramites > 0 ? 'Revisar documentacion' : 'Ver tramites')
                                            : 'Ver operaciones';
                                    @endphp

                                    <article class="portal-dashboard-operation portal-dashboard-operation--{{ $operationTone }}">
                                        <div class="portal-dashboard-operation__date">
                                            <strong>{{ $operationDay }}</strong>
                                            <span>{{ $operationMonth }}</span>
                                        </div>

                                        <div class="portal-dashboard-operation__summary">
                                            <p>{{ $operacion->estimated_filming_schedule ?: 'Hora sin definir' }}</p>
                                            <h3>{{ $operacion->reference }}</h3>
                                            <span>
                                                <flux:icon icon="map-pin" variant="mini" class="size-4" />
                                                {{ $operationAddress }}
                                            </span>
                                        </div>

                                        <div class="portal-dashboard-operation__assets">
                                            <span>
                                                <flux:icon icon="user" variant="mini" class="size-4" />
                                                <span>
                                                    <small>Piloto</small>
                                                    <strong>{{ $operacion->piloto?->fullName() ?? 'Sin piloto' }}</strong>
                                                </span>
                                            </span>
                                            <span>
                                                <flux:icon icon="paper-airplane" variant="mini" class="size-4" />
                                                <span>
                                                    <small>Dron</small>
                                                    <strong>{{ $operationDron }}</strong>
                                                    <em>Serie: {{ $operationDronSerial }}</em>
                                                </span>
                                            </span>
                                        </div>

                                        <div class="portal-dashboard-operation__state">
                                            <div class="portal-dashboard-operation__badges">
                                                <span class="portal-badge {{ $operationBadgeClass }}">{{ $operacion->statusLabel() }}</span>
                                                @if ($operationNotice)
                                                    <span class="portal-badge {{ $deniedTramites > 0 || $operacion->isRejected() ? 'portal-badge--danger' : ($operacion->isPending() ? 'portal-badge--amber' : 'portal-badge--sky') }}">{{ $operationNotice }}</span>
                                                @endif
                                            </div>

                                            @if ($operacion->isConfirmed())
                                                <div class="portal-dashboard-tramites">
                                                    <span><i class="portal-dashboard-dot portal-dashboard-dot--amber"></i>Pendientes <strong>{{ $pendingTramites }}</strong></span>
                                                    <span><i class="portal-dashboard-dot portal-dashboard-dot--sky"></i>Procesados <strong>{{ $processedTramites }}</strong></span>
                                                    <span><i class="portal-dashboard-dot portal-dashboard-dot--danger"></i>Denegados <strong>{{ $deniedTramites }}</strong></span>
                                                    <span><i class="portal-dashboard-dot portal-dashboard-dot--success"></i>Aprobados <strong>{{ $approvedTramites }}</strong></span>
                                                </div>
                                            @else
                                                <p class="portal-dashboard-operation__note">
                                                    {{ $operacion->isPending() ? 'El gestor debe confirmar esta operacion antes de crear tramites.' : 'Esta operacion no admite nuevos tramites mientras este rechazada.' }}
                                                </p>
                                            @endif

                                            <a href="{{ $operationActionHref }}" class="portal-dashboard-operation__action">
                                                {{ $operationActionLabel }}
                                                <flux:icon icon="chevron-right" variant="mini" class="size-4" />
                                            </a>
                                        </div>
                                    </article>
                                @empty
                                    <div class="portal-empty-state portal-dashboard-empty">
                                        Todavia no tienes operaciones futuras programadas.
                                    </div>
                                @endforelse
                            </div>

                        </section>

                        <aside class="portal-panel portal-dashboard-doc-panel">
                            <div class="portal-dashboard-section-header">
                                <div class="portal-dashboard-section-header__lead">
                                    <span class="portal-dashboard-section-icon portal-dashboard-section-icon--blue">
                                        <flux:icon icon="document-text" variant="mini" class="size-5" />
                                    </span>
                                    <div>
                                        <h2 class="portal-dashboard-section-header__title">Documentacion de operadora</h2>
                                        <p class="portal-dashboard-section-header__text">Control de obligatorios y revisiones.</p>
                                    </div>
                                </div>
                            </div>

                            <div class="portal-dashboard-doc-progress">
                                <div class="portal-dashboard-progress-ring" style="--portal-dashboard-progress: {{ $operadoraProgress }}%;">
                                    <span>{{ $operadoraProgress }}%</span>
                                </div>
                                <div>
                                    <strong>
                                        {{ $totalRequiredOperadora > 0 ? $completedRequiredOperadora.' de '.$totalRequiredOperadora : 'Sin obligatorios' }}
                                    </strong>
                                    <small>{{ $totalRequiredOperadora > 0 ? 'obligatorios aprobados' : 'configurados ahora mismo' }}</small>
                                </div>
                            </div>

                            <div class="portal-dashboard-doc-stats">
                                <span>
                                    <strong class="text-amber-600 dark:text-amber-300">{{ $pendingUploadOperadora }}</strong>
                                    Pendientes
                                </span>
                                <span>
                                    <strong class="text-sky-600 dark:text-sky-300">{{ $inReviewOperadora }}</strong>
                                    En revision
                                </span>
                                <span>
                                    <strong class="text-emerald-600 dark:text-emerald-300">{{ $completedOperadora }}</strong>
                                    Aprobados
                                </span>
                            </div>

                            <div class="portal-dashboard-doc-list">
                                <h3>Requiere tu atencion</h3>

                                @forelse ($dashboardOperadoraTasks as $requirement)
                                    @php
                                        $requirementTone = match ($requirement->status) {
                                            \App\Models\OperadoraRequirement::STATUS_NEEDS_CHANGES => 'danger',
                                            \App\Models\OperadoraRequirement::STATUS_IN_REVIEW => 'sky',
                                            default => 'amber',
                                        };
                                        $requirementLabel = match ($requirement->status) {
                                            \App\Models\OperadoraRequirement::STATUS_NEEDS_CHANGES => 'Corregir',
                                            \App\Models\OperadoraRequirement::STATUS_IN_REVIEW => 'En revision',
                                            default => 'Pendiente',
                                        };
                                        $requirementText = match ($requirement->status) {
                                            \App\Models\OperadoraRequirement::STATUS_NEEDS_CHANGES => 'Requiere correccion',
                                            \App\Models\OperadoraRequirement::STATUS_IN_REVIEW => 'En revision por el gestor',
                                            default => 'Pendiente de subir',
                                        };
                                    @endphp

                                    <a href="{{ route('operadora.index').'#requisito-operadora-'.$requirement->id }}" class="portal-dashboard-doc-item portal-dashboard-doc-item--{{ $requirementTone }}">
                                        <span class="portal-dashboard-doc-item__icon">
                                            <flux:icon icon="document" variant="mini" class="size-4" />
                                        </span>
                                        <span>
                                            <strong>{{ $requirement->name }}</strong>
                                            <small>{{ $requirementText }}</small>
                                        </span>
                                        <em>{{ $requirementLabel }}</em>
                                    </a>
                                @empty
                                    <div class="portal-dashboard-doc-item portal-dashboard-doc-item--success">
                                        <span class="portal-dashboard-doc-item__icon">
                                            <flux:icon icon="check-circle" variant="mini" class="size-4" />
                                        </span>
                                        <span>
                                            <strong>Sin acciones pendientes</strong>
                                            <small>Los obligatorios no requieren accion ahora.</small>
                                        </span>
                                        <em>Al dia</em>
                                    </div>
                                @endforelse
                            </div>

                            <flux:button as="a" variant="filled" :href="route('operadora.index')" wire:navigate class="w-full">
                                Ir a documentacion de operadora
                            </flux:button>
                        </aside>
                    </div>

                    <div class="portal-dashboard-summary-grid">
                        <a href="{{ route('operaciones.index') }}" wire:navigate class="portal-dashboard-summary-card portal-dashboard-summary-card--blue">
                            <span class="portal-dashboard-summary-card__icon portal-dashboard-summary-card__icon--sky">
                                <flux:icon icon="clipboard-document-list" variant="mini" class="size-5" />
                            </span>
                            <span>
                                <span class="portal-dashboard-summary-card__label">Operaciones</span>
                                <strong>{{ $operacionesCount }}</strong>
                                <span class="portal-dashboard-summary-card__text">Total operaciones</span>
                                <span class="portal-dashboard-summary-card__mini portal-dashboard-summary-card__mini--states">
                                    <span><i class="portal-dashboard-dot portal-dashboard-dot--amber"></i>Pendientes <b>{{ $totalPendingTramites }}</b></span>
                                    <span><i class="portal-dashboard-dot portal-dashboard-dot--sky"></i>Procesados <b>{{ $totalProcessedTramites }}</b></span>
                                    <span><i class="portal-dashboard-dot portal-dashboard-dot--danger"></i>Denegados <b>{{ $totalDeniedTramites }}</b></span>
                                    <span><i class="portal-dashboard-dot portal-dashboard-dot--success"></i>Aprobados <b>{{ $totalApprovedTramites }}</b></span>
                                </span>
                                <span class="portal-dashboard-summary-card__ops">
                                    {{ $upcomingOperacionesCount }} proximas, {{ $confirmedOperaciones }} confirmadas, {{ $pendingOperaciones }} pendientes, {{ $rejectedOperaciones }} rechazadas
                                </span>
                            </span>
                        </a>

                        <a href="{{ route('operadora.index') }}" wire:navigate class="portal-dashboard-summary-card portal-dashboard-summary-card--green">
                            <span class="portal-dashboard-summary-card__icon {{ $pendingRequiredOperadora > 0 ? 'portal-dashboard-summary-card__icon--danger' : 'portal-dashboard-summary-card__icon--emerald' }}">
                                <flux:icon icon="folder" variant="mini" class="size-5" />
                            </span>
                            <span>
                                <span class="portal-dashboard-summary-card__label">Documentacion de operadora</span>
                                <strong>{{ $operadoraProgress }}%</strong>
                                <span class="portal-dashboard-summary-card__text">Obligatorios aprobados</span>
                                <span class="portal-dashboard-summary-card__mini portal-dashboard-summary-card__mini--states">
                                    <span><i class="portal-dashboard-dot portal-dashboard-dot--amber"></i>Pendientes <b>{{ $pendingUploadOperadora }}</b></span>
                                    <span><i class="portal-dashboard-dot portal-dashboard-dot--sky"></i>En revision <b>{{ $inReviewOperadora }}</b></span>
                                    <span><i class="portal-dashboard-dot portal-dashboard-dot--danger"></i>Corregir <b>{{ $needsChangesOperadora }}</b></span>
                                    <span><i class="portal-dashboard-dot portal-dashboard-dot--success"></i>Aprobados <b>{{ $completedOperadora }}</b></span>
                                </span>
                            </span>
                        </a>

                        <a href="{{ route('drones.index') }}" wire:navigate class="portal-dashboard-summary-card portal-dashboard-summary-card--purple">
                            <span class="portal-dashboard-summary-card__icon portal-dashboard-summary-card__icon--indigo">
                                <flux:icon icon="paper-airplane" variant="mini" class="size-5" />
                            </span>
                            <span>
                                <span class="portal-dashboard-summary-card__label">Drones</span>
                                <strong>{{ $dronesCount }}</strong>
                                <span class="portal-dashboard-summary-card__text">Registrados en tu expediente</span>
                                <span class="portal-dashboard-summary-card__link">Ver drones &rarr;</span>
                            </span>
                        </a>

                        <a href="{{ route('pilotos.index') }}" wire:navigate class="portal-dashboard-summary-card portal-dashboard-summary-card--orange">
                            <span class="portal-dashboard-summary-card__icon portal-dashboard-summary-card__icon--orange">
                                <flux:icon icon="identification" variant="mini" class="size-5" />
                            </span>
                            <span>
                                <span class="portal-dashboard-summary-card__label">Pilotos</span>
                                <strong>{{ $pilotosCount }}</strong>
                                <span class="portal-dashboard-summary-card__text">Disponibles para operar</span>
                                <span class="portal-dashboard-summary-card__link">Ver pilotos &rarr;</span>
                            </span>
                        </a>
                    </div>

                    <section class="portal-panel portal-dashboard-quick-actions">
                        <h2 class="portal-dashboard-section-header__title">Acciones rapidas</h2>

                        <div class="portal-dashboard-action-grid">
                            <a href="{{ $createOperationHref }}" wire:navigate class="portal-dashboard-action">
                                <span class="portal-dashboard-action__icon portal-dashboard-action__icon--blue">
                                    <flux:icon icon="plus" variant="mini" class="size-5" />
                                </span>
                                <span>
                                    <strong>{{ $createOperationLabel }}</strong>
                                    <small>{{ $createOperationText }}</small>
                                </span>
                            </a>

                            <a href="{{ route('operaciones.index') }}" wire:navigate class="portal-dashboard-action">
                                <span class="portal-dashboard-action__icon portal-dashboard-action__icon--sky">
                                    <flux:icon icon="clipboard-document-list" variant="mini" class="size-5" />
                                </span>
                                <span>
                                    <strong>Ver operaciones</strong>
                                    <small>Todas mis operaciones</small>
                                </span>
                            </a>

                            <a href="{{ route('operadora.index') }}" wire:navigate class="portal-dashboard-action">
                                <span class="portal-dashboard-action__icon portal-dashboard-action__icon--green">
                                    <flux:icon icon="document-text" variant="mini" class="size-5" />
                                </span>
                                <span>
                                    <strong>Revisar operadora</strong>
                                    <small>Documentacion obligatoria</small>
                                </span>
                            </a>

                            <a href="{{ $createPilotoHref }}" wire:navigate class="portal-dashboard-action">
                                <span class="portal-dashboard-action__icon portal-dashboard-action__icon--purple">
                                    <flux:icon icon="user" variant="mini" class="size-5" />
                                </span>
                                <span>
                                    <strong>Crear piloto</strong>
                                    <small>Nuevo piloto</small>
                                </span>
                            </a>

                            <a href="{{ $createDronHref }}" wire:navigate class="portal-dashboard-action">
                                <span class="portal-dashboard-action__icon portal-dashboard-action__icon--orange">
                                    <flux:icon icon="paper-airplane" variant="mini" class="size-5" />
                                </span>
                                <span>
                                    <strong>Crear dron</strong>
                                    <small>Nuevo dron</small>
                                </span>
                            </a>
                        </div>
                    </section>
                </div>
            @endif
        </div>
    @else
        <div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl">
            <div class="grid auto-rows-min gap-4 md:grid-cols-3">
                <div class="relative aspect-video overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-700">
                    <x-placeholder-pattern class="absolute inset-0 size-full stroke-gray-900/20 dark:stroke-neutral-100/20" />
                </div>
                <div class="relative aspect-video overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-700">
                    <x-placeholder-pattern class="absolute inset-0 size-full stroke-gray-900/20 dark:stroke-neutral-100/20" />
                </div>
                <div class="relative aspect-video overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-700">
                    <x-placeholder-pattern class="absolute inset-0 size-full stroke-gray-900/20 dark:stroke-neutral-100/20" />
                </div>
            </div>
            <div class="relative h-full flex-1 overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-700">
                <x-placeholder-pattern class="absolute inset-0 size-full stroke-gray-900/20 dark:stroke-neutral-100/20" />
            </div>
        </div>
    @endif
</x-layouts::app>
