<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CiudadanoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = (array) $this->resource;

        if (filled($data['foto_path'] ?? null)) {
            $data['foto_url'] = Storage::disk('public')->url($data['foto_path']);
        } elseif (isset($data['foto_url']) && is_string($data['foto_url']) && $data['foto_url'] !== '') {
            $data['foto_url'] = $this->normalizeStorageUrl($data['foto_url']);
        }

        return $data;
    }

    private function normalizeStorageUrl(string $value): string
    {
        if (! Str::startsWith($value, ['http://', 'https://'])) {
            return $value;
        }

        $path = parse_url($value, PHP_URL_PATH);

        if (! is_string($path) || ! Str::startsWith($path, '/storage/')) {
            return $value;
        }

        return rtrim((string) config('app.url'), '/').$path;
    }
}
