<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Category;

use App\Models\Category;
use Illuminate\Validation\Rule;

class StoreCategoryRequest extends BaseCategoryRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Category::class);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required', 'string', 'min:2', 'max:100',
                Rule::unique('categories', 'name')->whereNull('deleted_at'),
            ],

            // Spread shared rules from BaseCategoryRequest.
            // FIX — BaseCategoryRequest must only include fields that exist
            // in the current table. color, icon, sort_order removed from base.
            ...$this->categoryFieldRules(),
        ];
    }
}
