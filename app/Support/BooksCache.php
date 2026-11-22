<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Version-namespaced cache keys for the book catalogue ("tagging" without a tag-capable driver).
 *
 * Extracted from BookController so that OTHER modules can invalidate the book cache too:
 * a checkout or return changes available_copies, which the cached book responses expose.
 */
final class BooksCache
{
    private const VERSION_KEY = 'books:cache_version';

    public function version(): int
    {
        return (int) Cache::get(self::VERSION_KEY, 1);
    }

    public function key(string $key): string
    {
        return 'v'.$this->version().':'.$key;
    }

    /** Makes every existing books:* entry unreachable; they age out via their TTL. */
    public function flush(): void
    {
        Cache::add(self::VERSION_KEY, 1);
        Cache::increment(self::VERSION_KEY);
    }
}
