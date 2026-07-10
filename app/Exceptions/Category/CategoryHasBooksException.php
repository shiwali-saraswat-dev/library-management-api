<?php

declare(strict_types=1);

namespace App\Exceptions\Category;

use App\Models\Category;

class CategoryHasBooksException extends \RuntimeException
{
    public function __construct(private readonly Category $category)
    {
        parent::__construct(
            "Category [{$category->name}] cannot be deleted because it has {$category->books_count} book(s) assigned to it."
        );
    }

    public function getCategory(): Category
    {
        return $this->category;
    }
}
