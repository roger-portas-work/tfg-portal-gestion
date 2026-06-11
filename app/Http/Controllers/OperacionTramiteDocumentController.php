<?php

namespace App\Http\Controllers;

use App\Models\Operacion;
use App\Models\OperacionTramite;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class OperacionTramiteDocumentController extends Controller
{
    public function __invoke(Request $request, Operacion $operacion, OperacionTramite $tramite, int $attachment)
    {
        $cliente = $request->user()?->cliente;

        abort_unless($cliente && (int) $operacion->cliente_id === (int) $cliente->id, 404);
        abort_unless($operacion->isConfirmed(), 403);
        abort_unless((int) $tramite->operacion_id === (int) $operacion->getKey(), 404);

        $attachments = array_values(array_filter((array) ($tramite->attachments ?? [])));
        $names = array_values((array) ($tramite->attachment_file_names ?? []));
        $path = $attachments[$attachment] ?? null;

        abort_unless(is_string($path) && $this->isSafeStoragePath($path), 404);

        $disk = Storage::disk('local');

        abort_unless($disk->exists($path), 404);

        $fileName = $names[$attachment] ?? basename($path);

        if ($request->boolean('download')) {
            return $disk->download($path, $fileName);
        }

        return response()->file($disk->path($path), [
            'Content-Disposition' => 'inline; filename="'.$this->escapeHeaderFileName($fileName).'"',
            'Content-Type' => 'application/pdf',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    protected function isSafeStoragePath(string $path): bool
    {
        $path = str_replace('\\', '/', $path);

        return ! str_starts_with($path, '/')
            && $path !== '..'
            && ! str_contains($path, '../')
            && ! str_contains($path, '/..');
    }

    protected function escapeHeaderFileName(string $fileName): string
    {
        return addcslashes($fileName, "\"\\");
    }
}
