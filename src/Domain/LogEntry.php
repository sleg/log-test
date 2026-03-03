<?php namespace App\Domain;

class LogEntry
{
    public function __construct(
        public readonly string $timestamp,
        public readonly string $level,
        public readonly string $service,
        public readonly string $message,
        public readonly ?array $context = null,
        public readonly ?string $traceId = null
    )
    {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            timestamp: $data['timestamp'],
            level: $data['level'],
            service: $data['service'],
            message: $data['message'],
            context: $data['context'] ?? null,
            traceId: $data['trace_id'] ?? null
        );
    }

    public function toArray(): array
    {
        $result = [
            'timestamp' => $this->timestamp,
            'level' => $this->level,
            'service' => $this->service,
            'message' => $this->message
        ];

        if ($this->context !== null) {
            $result['context'] = $this->context;
        }

        if ($this->traceId !== null) {
            $result['trace_id'] = $this->traceId;
        }

        return $result;
    }
}
