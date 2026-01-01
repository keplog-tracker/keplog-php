<?php

namespace Keplog\Tests;

use PHPUnit\Framework\TestCase;
use Keplog\Transport;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use Mockery;

class TransportTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function createTransport(Client $client = null, bool $debug = false): Transport
    {
        $transport = new Transport([
            'base_url' => 'http://localhost:8080',
            'ingest_key' => 'test_ingest_key',
            'timeout' => 5,
            'debug' => $debug,
        ]);

        if ($client) {
            $reflection = new \ReflectionClass($transport);
            $property = $reflection->getProperty('client');
            $property->setAccessible(true);
            $property->setValue($transport, $client);
        }

        return $transport;
    }

    public function testSendSuccessfulEvent(): void
    {
        $mockClient = Mockery::mock(Client::class);
        $mockClient->shouldReceive('post')
            ->once()
            ->with(
                'http://localhost:8080/api/ingest/v1/events',
                Mockery::on(function ($options) {
                    return $options['headers']['Content-Type'] === 'application/json'
                        && $options['headers']['X-Ingest-Key'] === 'test_ingest_key'
                        && isset($options['json']);
                })
            )
            ->andReturn(new Response(202, [], json_encode(['status' => 'queued'])));

        $transport = $this->createTransport($mockClient);

        $event = [
            'message' => 'Test error',
            'level' => 'error',
        ];

        $eventId = $transport->send($event);

        $this->assertNotNull($eventId);
        $this->assertStringStartsWith('evt_', $eventId);
    }

    public function testSendReturnsNullOn401(): void
    {
        $mockClient = Mockery::mock(Client::class);
        $mockClient->shouldReceive('post')
            ->once()
            ->andThrow(new RequestException(
                'Unauthorized',
                new Request('POST', 'test'),
                new Response(401, [], json_encode(['error' => 'Invalid API key']))
            ));

        $transport = $this->createTransport($mockClient);

        $event = [
            'message' => 'Test error',
            'level' => 'error',
        ];

        $eventId = $transport->send($event);

        $this->assertNull($eventId);
    }

    public function testSendReturnsNullOn400(): void
    {
        $mockClient = Mockery::mock(Client::class);
        $mockClient->shouldReceive('post')
            ->once()
            ->andThrow(new RequestException(
                'Bad Request',
                new Request('POST', 'test'),
                new Response(400, [], json_encode(['error' => 'Validation error']))
            ));

        $transport = $this->createTransport($mockClient);

        $event = [
            'message' => 'Test error',
            'level' => 'error',
        ];

        $eventId = $transport->send($event);

        $this->assertNull($eventId);
    }

    public function testSendTruncatesLargeMessage(): void
    {
        $mockClient = Mockery::mock(Client::class);
        $mockClient->shouldReceive('post')
            ->once()
            ->with(
                'http://localhost:8080/api/ingest/v1/events',
                Mockery::on(function ($options) {
                    $message = $options['json']['message'];
                    return strlen($message) <= 10016 // 10000 + "...[truncated]"
                        && str_ends_with($message, '...[truncated]');
                })
            )
            ->andReturn(new Response(202, [], json_encode(['status' => 'queued'])));

        $transport = $this->createTransport($mockClient, true);

        $event = [
            'message' => str_repeat('a', 15000),
            'level' => 'error',
        ];

        $eventId = $transport->send($event);

        $this->assertNotNull($eventId);
    }

    public function testSendTruncatesLargeStackTrace(): void
    {
        $mockClient = Mockery::mock(Client::class);
        $mockClient->shouldReceive('post')
            ->once()
            ->with(
                'http://localhost:8080/api/ingest/v1/events',
                Mockery::on(function ($options) {
                    $stackTrace = $options['json']['stack_trace'];
                    return strlen($stackTrace) <= 500015 // 500000 + "\n...[truncated]"
                        && str_ends_with($stackTrace, "\n...[truncated]");
                })
            )
            ->andReturn(new Response(202, [], json_encode(['status' => 'queued'])));

        $transport = $this->createTransport($mockClient, true);

        $event = [
            'message' => 'Error',
            'level' => 'error',
            'stack_trace' => str_repeat('x', 600000),
        ];

        $eventId = $transport->send($event);

        $this->assertNotNull($eventId);
    }

    public function testSendTruncatesLargeContext(): void
    {
        $mockClient = Mockery::mock(Client::class);
        $mockClient->shouldReceive('post')
            ->once()
            ->with(
                'http://localhost:8080/api/ingest/v1/events',
                Mockery::on(function ($options) {
                    $context = $options['json']['context'];
                    return isset($context['_error'])
                        && $context['_error'] === 'Context too large and was truncated';
                })
            )
            ->andReturn(new Response(202, [], json_encode(['status' => 'queued'])));

        $transport = $this->createTransport($mockClient, true);

        $largeArray = array_fill(0, 10000, str_repeat('x', 100));

        $event = [
            'message' => 'Error',
            'level' => 'error',
            'context' => ['data' => $largeArray],
        ];

        $eventId = $transport->send($event);

        $this->assertNotNull($eventId);
    }

    public function testSendRejectsEmptyMessage(): void
    {
        $transport = $this->createTransport();

        $event = [
            'message' => '',
            'level' => 'error',
        ];

        $eventId = $transport->send($event);

        $this->assertNull($eventId);
    }

    public function testSendRejectsInvalidLevel(): void
    {
        $transport = $this->createTransport();

        $event = [
            'message' => 'Test error',
            'level' => 'invalid_level',
        ];

        $eventId = $transport->send($event);

        $this->assertNull($eventId);
    }

    public function testSendAcceptsAllValidLevels(): void
    {
        $validLevels = ['critical', 'error', 'warning', 'info', 'debug'];

        foreach ($validLevels as $level) {
            $mockClient = Mockery::mock(Client::class);
            $mockClient->shouldReceive('post')
                ->once()
                ->andReturn(new Response(202, [], json_encode(['status' => 'queued'])));

            $transport = $this->createTransport($mockClient);

            $event = [
                'message' => 'Test',
                'level' => $level,
            ];

            $eventId = $transport->send($event);

            $this->assertNotNull($eventId, "Level $level should be accepted");
        }
    }

    public function testSendHandlesNetworkError(): void
    {
        $mockClient = Mockery::mock(Client::class);
        $mockClient->shouldReceive('post')
            ->once()
            ->andThrow(new RequestException(
                'Network error',
                new Request('POST', 'test')
            ));

        $transport = $this->createTransport($mockClient, true);

        $event = [
            'message' => 'Test error',
            'level' => 'error',
        ];

        $eventId = $transport->send($event);

        $this->assertNull($eventId);
    }

    public function testSendHandlesUnexpectedStatusCode(): void
    {
        $mockClient = Mockery::mock(Client::class);
        $mockClient->shouldReceive('post')
            ->once()
            ->andThrow(new RequestException(
                'Server error',
                new Request('POST', 'test'),
                new Response(500, [], 'Internal server error')
            ));

        $transport = $this->createTransport($mockClient, true);

        $event = [
            'message' => 'Test error',
            'level' => 'error',
        ];

        $eventId = $transport->send($event);

        $this->assertNull($eventId);
    }

    public function testSendHandlesGenericException(): void
    {
        $mockClient = Mockery::mock(Client::class);
        $mockClient->shouldReceive('post')
            ->once()
            ->andThrow(new \RuntimeException('Something went wrong'));

        $transport = $this->createTransport($mockClient, true);

        $event = [
            'message' => 'Test error',
            'level' => 'error',
        ];

        $eventId = $transport->send($event);

        $this->assertNull($eventId);
    }
}
