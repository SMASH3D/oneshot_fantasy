<?php

declare(strict_types=1);

namespace App\Exception;

/**
 * Domain rule failure for fantasy flows (HTTP mapping via FantasyViolationSubscriber).
 */
final class FantasyViolation extends \RuntimeException
{
    public function __construct(
        string $message,
        private readonly string $violationCode,
        private readonly int $httpStatus = 409,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function getViolationCode(): string
    {
        return $this->violationCode;
    }

    public function getHttpStatus(): int
    {
        return $this->httpStatus;
    }
}
