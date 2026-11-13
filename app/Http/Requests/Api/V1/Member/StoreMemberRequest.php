<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Member;

use App\Models\Member;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Member::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => Str::lower(trim($this->input('email')))]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            // Members live in the `users` table. Its unique index also covers
            // soft-deleted rows, so the rule must not ignore them.
            'email' => ['required', 'email:rfc', 'max:255', Rule::unique('users', 'email')],
            'phone' => ['nullable', 'string', 'regex:/^\+?[0-9\s\-]{7,20}$/'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'A user with this email already exists.',
            'phone.regex' => 'Phone must be 7-20 digits and may include +, spaces or dashes.',
            'is_active.boolean' => 'The is_active field must be true or false.',
        ];
    }
}