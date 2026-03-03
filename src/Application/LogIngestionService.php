<?php namespace App\Application;

use App\Domain\LogBatchRequest;
use Symfony\Component\Messenger\MessageBusInterface;

class LogIngestionService
{
    public function __construct(
        private LogValidator $validator,
        private MessageBusInterface $bus,
        private LogMessageFactory $factory
    )
    {
    }

    public function validate(LogBatchRequest $request): array
    {
        return $this->validator->validate([
            'logs' => $request->logs
        ]);
    }

    public function dispatch(LogBatchRequest $request): string
    {
        $batchId = $this->generateBatchId();

        foreach ($request->logs as $log) {
            $message = $this->factory->buildMessage($log, $batchId);
            $stamp = $this->factory->buildAmqpStamp('logs.ingest', $log['level'] ?? null);

            $this->bus->dispatch($message, [$stamp]);
        }

        return $batchId;
    }

    private function generateBatchId(): string
    {
        return 'batch_' . bin2hex(random_bytes(16));
    }
}
