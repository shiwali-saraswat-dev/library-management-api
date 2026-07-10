<?php

declare(strict_types=1);

namespace App\Exceptions\Category;

use App\Models\Category;

class CategoryNotTrashedExceptions extends \RuntimeException
{
    public function __construct(private readonly Category $category)
    {
        parent::__construct(
            "Category [{$category->name}] is not soft-deleted. Only soft-deleted categories can be restored or force-deleted."
        );
    }

    public function getCategory(): Category
    {
        return $this->category;
    }
}
