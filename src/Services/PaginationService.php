<?php

namespace App\Services;

use App\Helpers\PaginationHelper;
use Illuminate\Database\Eloquent\Builder;

class PaginationService
{
    private PaginationHelper $helper;

    public function __construct(?PaginationHelper $helper = null)
    {
        $this->helper = $helper ?? new PaginationHelper();
    }

    public function paginate(Builder $query, array $params, array $config): array
    {
        $basePath = $config['basePath'] ?? '';
        $sortMap = $config['sortMap'] ?? [];
        $filters = $config['filters'] ?? [];
        $search = $config['search'] ?? null;
        $allowedPerPage = $config['allowedPerPage'] ?? [20, 50, 100];
        $defaultPerPage = $config['defaultPerPage'] ?? 20;

        $activeQuery = $this->applyFilters($query, $params, $filters);
        $activeQuery = array_merge($activeQuery, $this->applySearch($query, $params, $search));

        $sort = $params['sort'] ?? array_key_first($sortMap) ?? '';
        $dir = strtolower($params['dir'] ?? 'asc') === 'desc' ? 'desc' : 'asc';
        $sortColumn = $sortMap[$sort] ?? (empty($sortMap) ? null : reset($sortMap));
        if ($sortColumn) {
            $query->orderBy($sortColumn, $dir);
        }

        $perPage = $this->resolvePerPage($params, $defaultPerPage, $allowedPerPage);
        $page = $this->resolvePage($params);

        $total = $query->count();
        $meta = $this->helper->buildPaginationMeta($total, $page, $perPage);
        $page = $meta['page'];
        $lastPage = $meta['lastPage'];

        $items = $query
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get();

        $queryBase = array_merge($activeQuery, [
            'sort' => $sort,
            'dir' => $dir,
            'per_page' => $perPage,
        ]);

        return [
            'items' => $items,
            'query' => $queryBase,
            'sort' => $sort,
            'dir' => $dir,
            'perPage' => $perPage,
            'total' => $total,
            'pageUrls' => $this->helper->buildPageUrls($basePath, $queryBase, $page, $lastPage),
            'sortUrls' => empty($sortMap)
                ? []
                : $this->helper->buildSortUrls($basePath, $queryBase, array_keys($sortMap), $sort),
            ...$meta,
        ];
    }

    private function applyFilters(Builder $query, array $params, array $filters): array
    {
        $active = [];
        foreach ($filters as $param => $rule) {
            $value = $params[$param] ?? '';
            if ($value === '' || $value === null) {
                continue;
            }

            if (is_callable($rule)) {
                $rule($query, $value, $params);
            } elseif (is_array($rule)) {
                $column = $rule['column'] ?? $param;
                $operator = $rule['operator'] ?? '=';
                $like = $rule['like'] ?? false;
                $query->where($column, $operator, $like ? ('%' . $value . '%') : $value);
            } elseif (is_string($rule)) {
                $query->where($rule, $value);
            }

            $active[$param] = $value;
        }

        return $active;
    }

    private function applySearch(Builder $query, array $params, mixed $searchConfig): array
    {
        if ($searchConfig === null) {
            return [];
        }

        $param = is_array($searchConfig) ? ($searchConfig['param'] ?? 'search') : 'search';
        $value = trim((string)($params[$param] ?? ''));
        if ($value === '') {
            return [];
        }

        if (is_callable($searchConfig)) {
            $searchConfig($query, $value, $params);
            return [$param => $value];
        }

        $columns = is_array($searchConfig) ? ($searchConfig['columns'] ?? []) : [];
        if ($columns) {
            $query->where(function ($q) use ($columns, $value) {
                foreach ($columns as $column) {
                    $q->orWhere($column, 'like', '%' . $value . '%');
                }
            });
        }

        return [$param => $value];
    }

    private function resolvePerPage(array $params, int $default, array $allowed): int
    {
        $perPage = (int)($params['per_page'] ?? $default);
        return in_array($perPage, $allowed, true) ? $perPage : $default;
    }

    private function resolvePage(array $params): int
    {
        return max(1, (int)($params['page'] ?? 1));
    }
}
