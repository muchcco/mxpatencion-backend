<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class CatalogItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = (array) $this->resource;

        if (isset($data['logo_url']) && is_string($data['logo_url']) && $data['logo_url'] !== '') {
            $data['logo_url'] = $this->normalizeUrl($data['logo_url']);
        }

        return $data;
    }

    private function normalizeUrl(string $value): string
    {
        $logoBaseUrl = rtrim((string) config('app.entity_logo_base_url'), '/');
        $logoPathMarker = '/api/public/entidad-atencion/logo/';

        if (Str::startsWith($value, ['http://', 'https://'])) {
            $path = parse_url($value, PHP_URL_PATH);

            if (is_string($path) && str_contains($path, $logoPathMarker)) {
                return $logoBaseUrl.'/'.ltrim(str($path)->after($logoPathMarker)->toString(), '/');
            }

            return $value;
        }

        return $logoBaseUrl.'/'.ltrim($value, '/');
    }
}
