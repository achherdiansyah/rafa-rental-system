<?php

namespace App\Services\Region;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Thin cached proxy for Indonesian administrative regions (province -> regency
 * -> district -> village). No secret keys are required.
 */
class RegionService
{
    public const CACHE_TTL = 2592000; // 30 days

    public function provinces(): array
    {
        return $this->fetch('provinces.json');
    }

    public function regencies(string $provinceCode): array
    {
        return $this->fetch("regencies/{$provinceCode}.json");
    }

    public function districts(string $regencyCode): array
    {
        return $this->fetch("districts/{$regencyCode}.json");
    }

    public function villages(string $districtCode): array
    {
        return $this->fetch("villages/{$districtCode}.json");
    }

    public function hasChild(string $parentCode, string $childCode): bool
    {
        return $this->childrenOf($parentCode)->contains(fn ($item) => (string) $item['id'] === $childCode);
    }

    /**
     * @return \Illuminate\Support\Collection<int, array{id:string,name:string}>
     */
    private function childrenOf(string $code): \Illuminate\Support\Collection
    {
        // Dot depth identifies the child type (province=0, regency=1, district=2).
        $items = match (substr_count($code, '.')) {
            0 => $this->regencies($code),
            1 => $this->districts($code),
            2 => $this->villages($code),
            default => [],
        };

        return collect($items);
    }

    public function childName(string $parentCode, string $childCode): ?string
    {
        $child = $this->childrenOf($parentCode)->first(fn ($item) => (string) $item['id'] === $childCode);

        return $child['name'] ?? null;
    }

    /**
     * @return array<int, array{id:string,name:string}>
     */
    private function fetch(string $path): array
    {
        $base = rtrim((string) config('services.region.base_url', 'https://www.emsifa.com/api-wilayah-indonesia/api'), '/');
        $key = 'regions:'.md5($base.$path);

        return Cache::remember($key, self::CACHE_TTL, function () use ($base, $path) {
            $response = Http::timeout(8)->acceptJson()->get("{$base}/{$path}");

            if (! $response->successful()) {
                throw new \RuntimeException('Layanan data wilayah tidak tersedia.');
            }

            $data = $response->json();
            if (! is_array($data)) {
                throw new \RuntimeException('Format data wilayah tidak valid.');
            }

            return array_values(array_map(fn ($item) => [
                'id' => (string) ($item['id'] ?? ''),
                'name' => (string) ($item['name'] ?? ''),
            ], array_filter($data, fn ($item) => is_array($item) && isset($item['id'], $item['name']))));
        });
    }
}

