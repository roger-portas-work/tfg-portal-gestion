<?php

namespace App\Filament\Widgets\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class DashboardOperationWindow
{
    public const QUERY_KEY = 'operations_window';

    public const DEFAULT = '7d';

    /**
     * @return array<string, array{label: string, description: string, days?: int, months?: int}>
     */
    public static function options(): array
    {
        return [
            '7d' => [
                'label' => '7 días',
                'description' => 'Operaciones desde hoy hasta 7 días vista',
                'days' => 7,
            ],
            '1m' => [
                'label' => '1 mes',
                'description' => 'Operaciones desde hoy hasta 1 mes vista',
                'months' => 1,
            ],
            '3m' => [
                'label' => '3 meses',
                'description' => 'Operaciones desde hoy hasta 3 meses vista',
                'months' => 3,
            ],
            'future' => [
                'label' => 'Futuras',
                'description' => 'Todas las operaciones futuras',
            ],
        ];
    }

    public static function selectedKey(): string
    {
        $key = request()->query(self::QUERY_KEY);

        if (blank($key)) {
            $key = self::keyFromReferer();
        }

        return array_key_exists((string) $key, self::options())
            ? (string) $key
            : self::DEFAULT;
    }

    /**
     * @return array{key: string, label: string, description: string, until: string|null}
     */
    public static function selected(): array
    {
        $key = self::selectedKey();
        $option = self::options()[$key];

        return [
            'key' => $key,
            'label' => $option['label'],
            'description' => $option['description'],
            'until' => self::untilDate($key),
        ];
    }

    public static function applyToQuery(Builder $query, string $column = 'operation_date'): Builder
    {
        $today = Carbon::today(config('app.timezone'))->toDateString();
        $until = self::selected()['until'];

        $query->whereDate($column, '>=', $today);

        if ($until) {
            $query->whereDate($column, '<=', $until);
        }

        return $query;
    }

    /**
     * @return array<int, array{key: string, label: string, description: string, active: bool, url: string}>
     */
    public static function filterOptions(): array
    {
        $activeKey = self::selectedKey();
        $currentQuery = request()->query();
        $baseUrl = url('/admin');

        return collect(self::options())
            ->map(fn (array $option, string $key): array => [
                'key' => $key,
                'label' => $option['label'],
                'description' => $option['description'],
                'active' => $key === $activeKey,
                'url' => $baseUrl.'?'.http_build_query(array_merge($currentQuery, [
                    self::QUERY_KEY => $key,
                ])),
            ])
            ->values()
            ->all();
    }

    protected static function untilDate(string $key): ?string
    {
        $option = self::options()[$key] ?? self::options()[self::DEFAULT];
        $today = Carbon::today(config('app.timezone'));

        if (isset($option['days'])) {
            return $today->copy()->addDays($option['days'])->toDateString();
        }

        if (isset($option['months'])) {
            return $today->copy()->addMonths($option['months'])->toDateString();
        }

        return null;
    }

    protected static function keyFromReferer(): ?string
    {
        $referer = request()->headers->get('referer');

        if (blank($referer)) {
            return null;
        }

        $query = parse_url($referer, PHP_URL_QUERY);

        if (blank($query)) {
            return null;
        }

        parse_str($query, $parameters);

        return $parameters[self::QUERY_KEY] ?? null;
    }
}
