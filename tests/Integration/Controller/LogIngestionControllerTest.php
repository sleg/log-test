<?php namespace App\Tests\Integration\Controller;

use App\Messaging\LogIngestedMessage;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

class LogIngestionControllerTest extends WebTestCase
{
    private function validPayload(array $logs = null): string
    {
        $logs ??= [
            [
                'timestamp' => '2026-02-28T10:00:00+00:00',
                'level' => 'error',
                'service' => 'auth-service',
                'message' => 'Login failed',
            ],
        ];

        return json_encode(['logs' => $logs]);
    }

    public function testValidRequestReturns202(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/logs/ingest', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], $this->validPayload());

        $this->assertResponseStatusCodeSame(202);

        $response = json_decode($client->getResponse()->getContent(), true);
        
        $this->assertSame('accepted', $response['status']);
        $this->assertArrayHasKey('batch_id', $response);
        $this->assertSame(1, $response['logs_count']);
    }

    public function testMultipleLogsReturnsCorrectCount(): void
    {
        $client = static::createClient();

        $logs = [[
            'timestamp' => '2026-02-28T10:00:00+00:00',
            'level' => 'error',
            'service' => 'auth',
            'message' => 'Message 1',
        ], [
            'timestamp' => '2026-02-28T10:01:00+00:00',
            'level' => 'info',
            'service' => 'api',
            'message' => 'Message 22',
        ]];

        $client->request('POST', '/api/logs/ingest', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], $this->validPayload($logs));

        $this->assertResponseStatusCodeSame(202);

        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame(2, $response['logs_count']);
    }

    public function testMessagesDispatched(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/logs/ingest', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], $this->validPayload());

        /** @var InMemoryTransport $transport */
        $transport = self::getContainer()->get('messenger.transport.logs_transport');
        $messages = $transport->getSent();

        $this->assertCount(1, $messages);
        $this->assertInstanceOf(LogIngestedMessage::class, $messages[0]->getMessage());
    }

    public function testEmptyLogsReturns400(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/logs/ingest', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['logs' => []]));

        $this->assertResponseStatusCodeSame(400);

        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('error', $response['status']);
        $this->assertNotEmpty($response['errors']);
    }

    public function testMissingRequiredFieldsReturns400(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/logs/ingest', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['logs' => [['level' => 'info']]]));

        $this->assertResponseStatusCodeSame(400);

        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('error', $response['status']);
        $this->assertNotEmpty($response['errors']);
    }

    public function testInvalidTimestampReturns400(): void
    {
        $client = static::createClient();

        $payload = $this->validPayload([
            [
                'timestamp' => 'not-a-date',
                'level' => 'info',
                'service' => 'api',
                'message' => 'test',
            ],
        ]);

        $client->request('POST', '/api/logs/ingest', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], $payload);

        $this->assertResponseStatusCodeSame(400);
    }

    public function testBatchIdIsUnique(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/logs/ingest', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], $this->validPayload());
        $response1 = json_decode($client->getResponse()->getContent(), true);

        $client->request('POST', '/api/logs/ingest', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], $this->validPayload());
        $response2 = json_decode($client->getResponse()->getContent(), true);

        $this->assertNotSame($response1['batch_id'], $response2['batch_id']);
    }

    public function testGetMethodNotAllowed(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/logs/ingest');

        $this->assertResponseStatusCodeSame(405);
    }
}
