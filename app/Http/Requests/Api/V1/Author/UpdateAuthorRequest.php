<?php

namespace App\Http\Requests\Api\V1\Author;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAuthorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * 'sometimes' means: only validate this field if it's actually present
     * in the request body. This makes the endpoint support PATCH semantics
     * (partial update) as well as PUT (full replace) with the same rule set —
     * a field simply left out of the payload is not touched or invalidated.
     */
    public function rules(): array
    {
        // route('author') is the Author model resolved by route-model-binding
        // for this {author} segment — see routes/api.php further down.
        $authorId = $this->route('author')?->id;

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'bio' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'dob' => ['sometimes', 'nullable', 'date', 'before:today'],
            'country' => ['sometimes', 'nullable', 'string', 'max:100'],
        ];
    }
}
