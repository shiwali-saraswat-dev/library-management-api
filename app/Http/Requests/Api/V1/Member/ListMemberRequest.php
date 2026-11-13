<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Member;

use App\Models\Member;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        if ($this->input('trashed') !== null) {
            return $this->user()?->can('viewAny', Member::class) ?? false;
        }

        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('is_active')) {
            $this->merge([
                'is_active' => filter_var(
                    $this->input('is_active'),
                    FILTER_VALIDATE_BOOLEAN,
                    FILTER_NULL_ON_FAILURE
                ),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'min:2', 'max:100'],
            'sort_by' => ['nullable', 'string', Rule::in(['name', 'email', 'created_at', 'updated_at'])],
            'sort_dir' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'trashed' => ['nullable', 'string', Rule::in(['only_deleted', 'with_deleted'])],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'sort_by.in' => 'Sort field must be one of: name, email, created_at, updated_at.',
            'sort_dir.in' => 'Sort direction must be asc or desc.',
            'trashed.in' => 'Trashed filter must be only_deleted or with_deleted.',
            'search.min' => 'Search term must be at least 2 characters.',
            'per_page.max' => 'Per page cannot exceed 100 results.',
            'is_active.boolean' => 'The is_active field must be true or false.',
        ];
    }
}