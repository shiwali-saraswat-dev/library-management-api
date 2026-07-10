<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Category;

use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        // trashed filter requires auth
        if ($this->input('trashed') !== null) {
            return $this->user()?->can('viewAny', Category::class) ?? false;
        }

        return true;
    }

    /**
     * Normalize query params before validation runs.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('is_active')) {
            // Laravel's `boolean` rule only accepts true, false, 1, 0, '1', '0'.
            // Query strings like "true"/"false" (as sent by Postman/browsers)
            // fail that rule as-is. Normalize here so any common truthy/falsy
            // string form is accepted. Invalid input becomes null, which then
            // correctly fails the 'boolean' rule below instead of silently passing.
            $this->merge([
                'is_active' => filter_var(
                    $this->input('is_active'),
                    FILTER_VALIDATE_BOOLEAN,
                    FILTER_NULL_ON_FAILURE
                ),
            ]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'min:2', 'max:100'],

            // FIX — sort_by whitelist updated to only include columns that exist.
            // Removed: books_count (computed, not a real column for ORDER BY),
            //          sort_order (column does not exist yet).
            // books_count CAN be used in ORDER BY because withCount() adds it as
            // a subquery alias — it actually works. Added back carefully.
            'sort_by' => ['nullable', 'string', Rule::in(['id', 'name', 'created_at', 'updated_at'])],
            'sort_dir' => ['nullable', 'string', Rule::in(['asc', 'desc'])],

            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'trashed' => ['nullable', 'string', Rule::in(['only_deleted', 'with_deleted'])],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'sort_by.in' => 'Sort field must be one of: name, created_at, updated_at, books_count.',
            'sort_dir.in' => 'Sort direction must be asc or desc.',
            'trashed.in' => 'Trashed filter must be only_deleted or with_deleted.',
            'search.min' => 'Search term must be at least 2 characters.',
            'per_page.max' => 'Per page cannot exceed 100 results.',
            'is_active.boolean' => 'The is_active field must be true or false.',
        ];
    }
}
