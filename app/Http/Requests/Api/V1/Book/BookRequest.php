<?php

namespace App\Http\Requests\Api\V1\Book;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $bookId = $this->route('book')?->id;

        return [
            'isbn' => [
                'required', 'string', 'max:20',
                Rule::unique('books', 'isbn')->ignore($bookId),
            ],

            'title' => [
                'required', 'string', 'max:255',
            ],

            'author_id' => [
                'required', 'integer', 'exists:authors,id',
            ],

            'category_id' => [
                'nullable', 'integer', 'exists:categories,id',
            ],

            'genre' => [
                'required', 'string', 'max:100',
            ],

            'total_copies' => [
                'required', 'integer', 'min:0',
            ],

            'available_copies' => [
                'required', 'integer', 'min:0', 'lte:total_copies',
            ],

            'published_at' => [
                'nullable', 'date',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'available_copies.lte' => 'Available copies cannot exceed total copies.',
        ];
    }
}
