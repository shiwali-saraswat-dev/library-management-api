<?php

declare(strict_types=1);

namespace App\Exceptions;

final class IdempotencyKeyRequiredException extends ApiException
{
    public function __construct()
    {
        parent::__construct('The Idempotency-Key header is required for this request.', 'IDEMPOTENCY_KEY_REQUIRED', 400);
    }
}
