<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Domain error for the Agent Channels feature. Carries a machine-readable
 * code and an HTTP status so it can be rendered uniformly as JSON.
 */
class AgentCommsException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $status = 422,
    ) {
        parent::__construct($message);
    }

    public static function make(string $code, string $message, int $status = 422): self
    {
        return new self($code, $message, $status);
    }
}
