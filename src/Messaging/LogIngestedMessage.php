<?php namespace App\Messaging;

class LogIngestedMessage
{
    public function __construct(
        public readonly array $log,
        public readonly string $batchId,
        public readonly \DateTimeImmutable $publishedAt,
        public readonly int $retryCount = 0
    )
    {
    }
}
