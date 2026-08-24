<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Donation;
use App\Support\SearchFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class DonorService
{
    public function paginate(array $params, string $organizationId): LengthAwarePaginator
    {
        $perPage = max(1, min((int) ($params['perPage'] ?? 20), 100));
        $sort = $this->normalizeSort($params);
        $search = SearchFilter::fromArray($params);

        $query = Donation::query()
            ->where('organization_id', $organizationId)
            ->when(($city = $this->param($params, 'filter.city')) && $city !== 'all', fn (Builder $builder) => $builder->where('city', $city))
            ->when($search !== '', function (Builder $builder) use ($search): void {
                $builder->where(function (Builder $inner) use ($search): void {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('city', 'like', "%{$search}%");
                });
            });

        match ($sort) {
            'name' => $query->orderBy('name'),
            '-name' => $query->orderByDesc('name'),
            'donatedAt' => $query->orderBy('created_at'),
            '-donatedAt' => $query->orderByDesc('created_at'),
            default => $query->orderByDesc('created_at'),
        };

        return $query->paginate($perPage);
    }

    public function create(array $attributes, string $organizationId, string $userId): Donation
    {
        return Donation::query()->create([
            'organization_id' => $organizationId,
            'name' => $attributes['name'],
            'email' => $attributes['email'],
            'phone' => $attributes['phone'],
            'city' => null,
            'created_by' => $userId,
        ]);
    }

    public function update(Donation $donation, array $attributes, string $organizationId): Donation
    {
        $donation->update([
            'name' => $attributes['name'],
            'email' => $attributes['email'],
            'phone' => $attributes['phone'],
        ]);

        return $donation->refresh();
    }

    private function normalizeSort(array $params): string
    {
        $sort = (string) ($params['sort'] ?? '');
        if ($sort !== '') {
            return $sort;
        }

        $sortBy = (string) ($params['sortBy'] ?? '');

        return match ($sortBy) {
            'date_oldest' => 'donatedAt',
            'name_asc' => 'name',
            'name_desc' => '-name',
            default => '-donatedAt',
        };
    }

    private function param(array $params, string $key): mixed
    {
        if (array_key_exists($key, $params)) {
            return $params[$key];
        }

        $flatKey = str_replace('.', '_', $key);
        if (array_key_exists($flatKey, $params)) {
            return $params[$flatKey];
        }

        return data_get($params, $key);
    }
}
