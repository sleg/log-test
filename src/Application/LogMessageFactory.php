<?php namespace App\Application;

use App\Messaging\LogIngestedMessage;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpStamp;

class LogMessageFactory
{
    public function buildMessage(array $log, string $batchId): LogIngestedMessage
    {
        return new LogIngestedMessage(
            log: $log,
            batchId: $batchId,
            publishedAt: new \DateTimeImmutable(),
            retryCount: 0
        );
    }

    public function buildAmqpStamp(string $routingKey, ?string $level): AmqpStamp
    {
        $priority = $this->determinePriority($level);

        return new AmqpStamp(
            routingKey: $routingKey,
            flags: AMQP_NOPARAM,
            attributes: [
                'priority' => $priority,
                'delivery_mode' => 2,
            ]
        );
    }

    private function determinePriority(?string $level): int
    {
        return match (strtolower((string) $level)) {
            'error', 'critical', 'alert', 'emergency' => 9,
            'warning', 'warn' => 5,
            default => 1,
        };
    }
}
