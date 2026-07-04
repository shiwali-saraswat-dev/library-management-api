<?php

namespace App\Http\Requests\Api\V1\Author;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAuthorRequest extends FormRequest
{
    /**
     * Authorization is delegated to the Policy (see AuthorPolicy + controller),
     * not duplicated here. Keeping "can this user do this" in one place
     * (the Policy) avoids the two mechanisms drifting out of sync.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Validation rules run BEFORE the controller method body executes.
     * If these fail, Laravel automatically returns a 422 with a JSON
     * error bag — the controller never even gets called.
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],

            'bio' => ['nullable', 'string', 'max:5000'],

            'dob' => ['nullable', 'date', 'before:today'],

            'country' => ['nullable', 'string', 'max:100']
        ];
    }

    /**
     * Custom messages: optional, but a senior touch — API consumers get
     * a message they can show directly in a UI instead of Laravel's generic default.
     */
    public function messages(): array
    {
        return [
            'birth_date.before' => 'Birth date must be a date in the past.'
        ];
    }
}