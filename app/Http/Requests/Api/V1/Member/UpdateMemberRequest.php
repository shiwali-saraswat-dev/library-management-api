<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Member;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        // The controller authorizes against the loaded member via the policy.
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => Str::lower(trim($this->input('email')))]);
        }
    }

    public function rules(): array
    {
        $memberId = (int) $this->route('member_id');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => [
                'sometimes', 'required', 'email:rfc', 'max:255',
                Rule::unique('users', 'email')->ignore($memberId),
            ],
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