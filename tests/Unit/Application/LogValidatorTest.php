<?php namespace App\Tests\Unit\Application;

use App\Application\LogValidator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;

class LogValidatorTest extends TestCase
{
    private LogValidator $validator;

    protected function setUp(): void
    {
        $symfonyValidator = Validation::createValidator();
        
        $this->validator = new LogValidator($symfonyValidator);
    }

    private function validLog(array $overrides = []): array
    {
        return array_merge([
            'timestamp' => '2026-02-28T10:00:00+00:00',
            'level' => 'error',
            'service' => 'auth-service',
            'message' => 'Something went wrong',
        ], $overrides);
    }

    public function testValidBatchPasses(): void
    {
        $errors = $this->validator->validate([
            'logs' => [$this->validLog()],
        ]);

        $this->assertSame([], $errors);
    }

    public function testValidBatchWithOptionalFields(): void
    {
        $errors = $this->validator->validate([
            'logs' => [
                $this->validLog([
                    'context' => ['user_id' => 42],
                    'trace_id' => 'abc-123',
                ]),
            ],
        ]);

        $this->assertSame([], $errors);
    }

    public function testMultipleValidLogs(): void
    {
        $errors = $this->validator->validate([
            'logs' => [
                $this->validLog(),
                $this->validLog([
                    'level' => 'info',
                    'message' => 'All good'
                ]),
            ],
        ]);

        $this->assertSame([], $errors);
    }

    public function testMissingLogsKey(): void
    {
        $errors = $this->validator->validate([]);

        $this->assertCount(1, $errors);
        $this->assertStringContainsString('"logs" must be an array', $errors[0]);
    }

    public function testLogsNotAnArray(): void
    {
        $errors = $this->validator->validate(['logs' => 'not-array']);

        $this->assertCount(1, $errors);
        $this->assertStringContainsString('"logs" must be an array', $errors[0]);
    }

    public function testEmptyLogsArray(): void
    {
        $errors = $this->validator->validate(['logs' => []]);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('at least one item', $errors[0]);
    }

    public function testExceedsMaxLogs(): void
    {
        $logs = array_fill(0, 1001, $this->validLog());

        $errors = $this->validator->validate(['logs' => $logs]);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('1000', $errors[0]);
    }

    public function testExactly1000LogsPasses(): void
    {
        $logs = array_fill(0, 1000, $this->validLog());

        $errors = $this->validator->validate(['logs' => $logs]);

        $this->assertSame([], $errors);
    }

    public function testMissingTimestamp(): void
    {
        $log = $this->validLog();
        unset($log['timestamp']);

        $errors = $this->validator->validate(['logs' => [$log]]);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('logs[0]', $errors[0]);
        $this->assertStringContainsString('timestamp', $errors[0]);
    }

    public function testMissingLevel(): void
    {
        $log = $this->validLog();
        unset($log['level']);

        $errors = $this->validator->validate(['logs' => [$log]]);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('level', $errors[0]);
    }

    public function testMissingService(): void
    {
        $log = $this->validLog();
        unset($log['service']);

        $errors = $this->validator->validate(['logs' => [$log]]);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('service', $errors[0]);
    }

    public function testMissingMessage(): void
    {
        $log = $this->validLog();
        unset($log['message']);

        $errors = $this->validator->validate(['logs' => [$log]]);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('message', $errors[0]);
    }

    public function testInvalidTimestampFormat(): void
    {
        $errors = $this->validator->validate([
            'logs' => [$this->validLog(['timestamp' => 'not-a-date'])],
        ]);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('timestamp', $errors[0]);
    }

    public function testBlankTimestamp(): void
    {
        $errors = $this->validator->validate([
            'logs' => [$this->validLog(['timestamp' => ''])],
        ]);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('timestamp', $errors[0]);
    }

    public function testLogEntryNotAnArray(): void
    {
        $errors = $this->validator->validate([
            'logs' => ['not-an-array'],
        ]);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('logs[0] must be an object', $errors[0]);
    }

    public function testInvalidTraceIdType(): void
    {
        $errors = $this->validator->validate([
            'logs' => [$this->validLog(['trace_id' => 123])],
        ]);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('trace_id', $errors[0]);
    }

    public function testMultipleErrorsCollected(): void
    {
        $errors = $this->validator->validate([
            'logs' => [
                ['level' => 'info'],
                $this->validLog(['timestamp' => 'bad']),
            ],
        ]);

        $this->assertGreaterThan(1, count($errors));
    }
}