<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Borrowing;

use App\Models\Borrowing;
use App\Models\Member;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreBorrowingRequest extends FormRequest
{
    /** Runs BEFORE validation, so an unauthorised caller gets 403, not a 422 that leaks field rules. */
    public function authorize(): bool
    {
        return Gate::allows('create', Borrowing::class);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'book_id' => ['required', 'integer', Rule::exists('books', 'id')->whereNull('deleted_at')],
            'member_id' => [
                'required',
                'integer',
                // Members are users with the member role (Member has the global scope), not any user.
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! Member::query()->whereKey($value)->exists()) {
                        $fail('The selected member is invalid.');
                    }
                },
            ],
        ];
    }
}
