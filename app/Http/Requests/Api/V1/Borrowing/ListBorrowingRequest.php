<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Borrowing;

use App\Models\Borrowing;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ListBorrowingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('viewAny', Borrowing::class);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'string', 'min:2', 'max:100'],
            'status' => ['sometimes', Rule::in(['active', 'overdue', 'returned'])],
            'member_id' => ['sometimes', 'integer', 'min:1'],
            'book_id' => ['sometimes', 'integer', 'min:1'],
            'sort_by' => ['sometimes', Rule::in(['borrowed_at', 'due_at', 'returned_at', 'created_at'])],
            'sort_dir' => ['sometimes', Rule::in(['asc', 'desc'])],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
            'cursor' => ['sometimes', 'string'],
        ];
    }
}
