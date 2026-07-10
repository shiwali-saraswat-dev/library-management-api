<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Category;

use Illuminate\Foundation\Http\FormRequest;

abstract class BaseCategoryRequest extends FormRequest
{
    /**
     * Shared field rules for columns that exist in the current categories table.
     *
     * FIX — removed color, icon, sort_order rules entirely.
     * Those columns do not exist in the table yet. Sending them in the request
     * body is harmless (they'll be ignored by $fillable), but validating them
     * as if they're real fields is misleading. Remove the commented lines only
     * after running a migration that adds those columns.
     *
     * @return array<string, array<int, mixed>>
     */
    protected function categoryFieldRules(): array
    {
        return [
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],

            // Add these back after migration:
            // 'color'      => ['nullable', 'string', 'regex:/^#([A-Fa-f0-9]{6})$/'],
            // 'icon'       => ['nullable', 'string', 'max:50'],
            // 'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.unique' => 'A category with this name already exists.',
            'name.min' => 'Category name must be at least 2 characters.',
            'name.max' => 'Category name cannot exceed 100 characters.',

            // Add these back after migration:
            // 'color.regex' => 'Color must be a valid hex code (e.g. #FF5733).',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'is_active' => 'active status',
        ];
    }
}
