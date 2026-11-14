<?php

declare(strict_types=1);

namespace App\Http\Resources\Concerns;

trait HasLinks
{
    /**
     * Build a single HATEOAS link entry for a named route, using the
     * resource's underlying model as the route-model-binding parameter.
     *
     * @param  string  $route   Named route, e.g. 'members.show'
     * @param  string  $method  HTTP verb this link represents, e.g. 'GET', 'PUT', 'DELETE'
     * @return array{href: string, method: string}
     */
    protected function link(string $route, string $method = 'GET'): array
    {
        return [
            'href' => route($route, $this->resource),
            'method' => $method,
        ];
    }
}