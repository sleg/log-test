<?php namespace App\Application;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class LogValidator
{
    public function __construct(private ValidatorInterface $validator)
    {
    }

    public function validate(array $payload): array
    {
        // Existance check
        if ( ! isset($payload['logs']) ||
            ! is_array($payload['logs'])
        ) {
            return ['"logs" must be an array'];
        }

        $logs = $payload['logs'];

        // Rest of checks
        return array_merge(
            $this->validateMinItems($logs),
            $this->validateMaxItems($logs),
            $this->validateLogEntries($logs)
        );
    }

    protected function validateMinItems(array $logs): array
    {
        if (count($logs) !== 0) {
            return [];
        }

        return ['"logs" must contain at least one item'];
    }

    protected function validateMaxItems(array $logs): array
    {
        if (count($logs) <= 1000) {
            return [];
        }

        return ['"logs" max items shouldn\'t be greater than 1000'];
    }

    protected function validateLogEntries(array $logs): array
    {
        $errors = [];

        foreach ($logs as $index => $log) {
            if ( ! is_array($log)) {
                $errors[] = sprintf('logs[%d] must be an object', $index);
                continue;
            }

            $entryErrors = $this->validator->validate($log, $this->getRules());

            foreach ($entryErrors as $error) {
                $errors[] = sprintf(
                    'logs[%d].%s: %s',
                    $index,
                    $error->getPropertyPath(),
                    $error->getMessage()
                );
            }
        }

        return $errors;
    }
    
    protected function getRules(): Assert\Collection
    {
        return new Assert\Collection([
            'timestamp' => [
                new Assert\NotBlank(),
                new Assert\Type('string'),
                new Assert\DateTime(format: \DateTime::ATOM),
            ],
            'level' => [
                new Assert\NotBlank(),
                new Assert\Type('string'),
            ],
            'service' => [
                new Assert\NotBlank(),
                new Assert\Type('string'),
            ],
            'message' => [
                new Assert\NotBlank(),
                new Assert\Type('string'),
            ],
            'context' => new Assert\Optional([
                new Assert\Type('array'),
            ]),
            'trace_id' => new Assert\Optional([
                new Assert\Type('string'),
            ]),
        ]);
    }
}