<?php

namespace App\Helpers;

class PaginationHelper
{
    public function buildPaginationMeta(int $total, int $page, int $perPage): array
    {
        $lastPage = max(1, (int)ceil($total / $perPage));
        $page = min($page, $lastPage);
        $rangeStart = $total ? (($page - 1) * $perPage + 1) : 0;
        $rangeEnd = $total ? min($total, $page * $perPage) : 0;

        return [
            'page' => $page,
            'lastPage' => $lastPage,
            'rangeStart' => $rangeStart,
            'rangeEnd' => $rangeEnd,
        ];
    }

    public function buildQueryString(string $basePath, array $params): string
    {
        $filtered = array_filter($params, function ($value) {
            return $value !== '' && $value !== null;
        });

        return $basePath . (empty($filtered) ? '' : ('?' . http_build_query($filtered)));
    }

    public function buildPageUrls(string $basePath, array $queryBase, int $page, int $lastPage): array
    {
        $prev = $page > 1
            ? $this->buildQueryString($basePath, array_merge($queryBase, ['page' => $page - 1]))
            : null;
        $next = $page < $lastPage
            ? $this->buildQueryString($basePath, array_merge($queryBase, ['page' => $page + 1]))
            : null;

        return [
            'prev' => $prev,
            'next' => $next,
        ];
    }

    public function buildSortUrls(
        string $basePath,
        array $queryBase,
        array $sortKeys,
        string $defaultSort
    ): array {
        $currentSort = $queryBase['sort'] ?? $defaultSort;
        $currentDir = $queryBase['dir'] ?? 'asc';

        $build = function (string $sortKey) use ($basePath, $queryBase, $currentSort, $currentDir): string {
            $dir = ($currentSort === $sortKey && $currentDir === 'asc') ? 'desc' : 'asc';
            return $this->buildQueryString($basePath, array_merge($queryBase, [
                'sort' => $sortKey,
                'dir' => $dir,
                'page' => 1,
            ]));
        };

        $urls = [];
        foreach ($sortKeys as $sortKey) {
            $urls[$sortKey] = $build($sortKey);
        }

        return $urls;
    }
}
