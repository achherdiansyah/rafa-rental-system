<?php

namespace App\Services\Reports\Concerns;

use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;

/**
 * Shared filter / pagination / sort helpers for row-level report services.
 */
trait FiltersAndPagination
{
    /**
     * @param  Builder  $query
     * @param  array<string, mixed>  $filters
     * @param  array<string, array{0: string, 1: string}>  $map  filterKey => [column, op]
     */
    protected function applyPlain($query, array $filters, array $map): void
    {
        foreach ($map as $key => [$column, $op]) {
            if (empty($filters[$key])) {
                continue;
            }
            if ($op === 'eq') {
                $query->where($column, $filters[$key]);
            } elseif ($op === 'date_min') {
                $query->where($column, '>=', Carbon::parse($filters[$key])->toDateString());
            } elseif ($op === 'date_max') {
                $query->where($column, '<=', Carbon::parse($filters[$key])->toDateString());
            }
        }
    }

    protected function enumValue(mixed $value): string
    {
        return $value === null ? '' : (string) $value;
    }

    /**
     * @param  Builder  $query
     * @param  array<string, mixed>  $filters
     * @param  array<string, string>  $sortable  sortKey => column (whitelist)
     */
    protected function paginate($query, array $filters, array $sortable): LengthAwarePaginator
    {
        $sortBy = $filters['sort_by'] ?? null;
        $sortDir = strtolower((string) ($filters['sort_dir'] ?? 'desc'));

        $column = $sortBy ? ($sortable[$sortBy] ?? null) : null;
        if ($column) {
            $query->orderBy($column, $sortDir === 'asc' ? 'asc' : 'desc');
        } elseif ($defaultKey = $sortable['id desc'] ?? $sortable[array_key_first($sortable)] ?? null) {
            $query->orderBy($defaultKey, 'desc');
        }

        $perPage = min(max((int) ($filters['per_page'] ?? 15), 1), 1000);
        $page = max(1, (int) ($filters['page'] ?? 1));

        return $query->paginate($perPage, ['*'], 'page', $page)->withQueryString();
    }
}
