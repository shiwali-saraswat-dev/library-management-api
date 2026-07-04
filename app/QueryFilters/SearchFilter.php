<?php

namespace App\QueryFilters;

use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\Filters\Filter;

/**
 * A "Filter" class, in spatie/laravel-query-builder terms, is just an
 * invokable class: __invoke($query, $value, $property) receives the
 * Eloquent builder and the raw value from ?filter[search]=xyz, and decides
 * what to do with it. This is where the URL query string finally connects
 * to the scopeSearch() method on the Author model.
 */
class SearchFilter implements Filter
{
    public function __invoke(Builder $query, $value, string $property): Builder
    {
        return $query->search((string) $value);
    }
}