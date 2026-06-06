<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Clientes\ClienteResource;
use App\Models\OperadoraRequirement;
use Filament\Widgets\Widget;

class OperadoraRequirementsReviewWidget extends Widget
{
    protected string $view = 'filament.widgets.operadora-requirements-review-widget';

    protected int | string | array $columnSpan = 'full';

    protected static ?int $sort = 6;

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $requirements = OperadoraRequirement::query()
            ->with('cliente')
            ->where('status', OperadoraRequirement::STATUS_IN_REVIEW)
            ->oldest('submitted_at')
            ->limit(6)
            ->get()
            ->map(fn (OperadoraRequirement $requirement): array => [
                'name' => $requirement->name,
                'cliente' => $requirement->cliente?->fullName() ?: 'Sin cliente',
                'type' => OperadoraRequirement::inputTypeOptions()[$requirement->input_type] ?? $requirement->input_type,
                'is_required' => $requirement->is_required,
                'submitted_at' => $requirement->submitted_at?->format('d/m/Y H:i') ?: 'Sin fecha',
                'url' => ClienteResource::getUrl('edit', [
                    'record' => $requirement->cliente_id,
                    'focus' => 'operadora-revision',
                    'requirement' => $requirement->id,
                ]),
            ]);

        return [
            'requirements' => $requirements,
        ];
    }
}
