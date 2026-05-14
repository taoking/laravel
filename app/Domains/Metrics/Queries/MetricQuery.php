<?php

namespace App\Domains\Metrics\Queries;

use App\Domains\Metrics\Models\Metric;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class MetricQuery
{
    private const ALLOWED_SORTS = [
        'id',
        'name',
        'code',
        'status',
        'created_at',
        'updated_at',
    ];

    public function paginate(array $filters): LengthAwarePaginator
    {
        $sort = $filters['sort'] ?? 'id';
        $direction = strtolower($filters['direction'] ?? 'desc');

        if (! in_array($sort, self::ALLOWED_SORTS, true)) {
            throw ValidationException::withMessages([
                'sort' => 'The selected sort field is invalid.',
            ]);
        }

        if (! in_array($direction, ['asc', 'desc'], true)) {
            throw ValidationException::withMessages([
                'direction' => 'The selected sort direction is invalid.',
            ]);
        }

        return Metric::query()
            ->with(['category', 'latestValue.region', 'latestValue.frequency'])
            ->when($filters['keyword'] ?? null, function ($query, string $keyword) {
                $query->where(function ($query) use ($keyword) {
                    $query->where('name', 'like', "%{$keyword}%")
                        ->orWhere('code', 'like', "%{$keyword}%");
                });
            })
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['category_id'] ?? null, fn ($query, int $categoryId) => $query->where('metric_category_id', $categoryId))
            ->when($filters['category_code'] ?? null, fn ($query, string $code) => $query->whereHas('category', fn ($query) => $query->where('code', $code)))
            ->when($filters['region_id'] ?? null, fn ($query, int $regionId) => $query->whereHas('values', fn ($query) => $query->where('region_id', $regionId)))
            ->when($filters['frequency_code'] ?? null, fn ($query, string $code) => $query->whereHas('values.frequency', fn ($query) => $query->where('code', $code)))
            ->when($filters['date_from'] ?? null, fn ($query, string $date) => $query->whereHas('values', fn ($query) => $query->whereDate('period_date', '>=', $date)))
            ->when($filters['date_to'] ?? null, fn ($query, string $date) => $query->whereHas('values', fn ($query) => $query->whereDate('period_date', '<=', $date)))
            ->orderBy($sort, $direction)
            ->paginate(min((int) ($filters['per_page'] ?? 20), 100));
    }
}
