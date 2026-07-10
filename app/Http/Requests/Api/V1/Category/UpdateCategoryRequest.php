<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Category;

use App\Models\Category;
use Illuminate\Validation\Rule;

class UpdateCategoryRequest extends BaseCategoryRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('category_id')
            ? Category::findOrFail($this->route('category_id'))
            : Category::class
        );
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $categoryId = (int) $this->route('category_id');

        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'min:2',
                'max:100',
                Rule::unique('categories', 'name')
                    ->ignore($categoryId)
                    ->whereNull('deleted_at'),
            ],

            // Shared fields — all wrapped in 'sometimes' for partial update
            ...collect($this->categoryFieldRules())
                ->map(fn (array $rules) => ['sometimes', ...$rules])
                ->all(),
        ];
    }
}
