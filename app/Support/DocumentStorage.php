<?php

namespace App\Support;

use Illuminate\Support\Str;

class DocumentStorage
{
    public static function slug(?string $value, string $fallback = 'documento', int $limit = 60): string
    {
        $slug = Str::slug((string) $value);

        if (blank($slug)) {
            $slug = $fallback;
        }

        return Str::limit($slug, $limit, '');
    }

    public static function clienteSegment(mixed $id, ?string $name): string
    {
        return self::entitySegment('cliente', $id, $name, 'cliente');
    }

    public static function entitySegment(string $prefix, mixed $id, ?string $name, string $fallback): string
    {
        $slug = self::slug($name, $fallback, 50);

        return filled($id)
            ? "{$prefix}-{$id}-{$slug}"
            : "{$prefix}-{$slug}";
    }

    public static function folder(string ...$segments): string
    {
        $segments = array_values(array_filter(
            array_map(fn (string $segment): string => trim($segment, '/'), $segments),
            fn (string $segment): bool => $segment !== ''
        ));

        return implode('/', $segments);
    }

    /**
     * @param  array<int, string|null>  $parts
     */
    public static function pdfFileName(array $parts, ?string $originalName = null): string
    {
        $safeParts = array_values(array_filter(array_map(
            fn (?string $part): ?string => filled($part) ? self::slug($part, 'documento', 45) : null,
            $parts
        )));

        $safeParts[] = self::slug(pathinfo($originalName ?: 'documento', PATHINFO_FILENAME), 'documento', 50);
        $safeParts[] = Str::lower(Str::random(6));

        return now(config('app.timezone'))->format('YmdHis').'-'.implode('-', $safeParts).'.pdf';
    }
}
