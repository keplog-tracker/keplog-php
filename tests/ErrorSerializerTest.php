<?php

namespace Keplog\Tests;

use PHPUnit\Framework\TestCase;
use Keplog\ErrorSerializer;
use Keplog\Scope;
use Keplog\Breadcrumbs;

class ErrorSerializerTest extends TestCase
{
    public function testSerializeBasicException(): void
    {
        $exception = new \Exception('Test error');
        $scope = new Scope();
        $breadcrumbs = new Breadcrumbs();

        $event = ErrorSerializer::serialize(
            $exception,
            'error',
            $scope,
            $breadcrumbs->getAll()
        );

        $this->assertEquals('Test error', $event['message']);
        $this->assertEquals('error', $event['level']);
        $this->assertArrayHasKey('stack_trace', $event);
        $this->assertArrayHasKey('context', $event);
        $this->assertArrayHasKey('timestamp', $event);
    }

    public function testSerializeWithEmptyMessage(): void
    {
        $exception = new \Exception('');
        $scope = new Scope();
        $breadcrumbs = new Breadcrumbs();

        $event = ErrorSerializer::serialize(
            $exception,
            'error',
            $scope,
            $breadcrumbs->getAll()
        );

        $this->assertEquals('Unknown error', $event['message']);
    }

    public function testSerializeIncludesStackTrace(): void
    {
        $exception = new \RuntimeException('Runtime error');
        $scope = new Scope();
        $breadcrumbs = new Breadcrumbs();

        $event = ErrorSerializer::serialize(
            $exception,
            'error',
            $scope,
            $breadcrumbs->getAll()
        );

        $this->assertIsString($event['stack_trace']);
        $this->assertStringContainsString('RuntimeException', $event['stack_trace']);
        $this->assertStringContainsString('Runtime error', $event['stack_trace']);
    }

    public function testSerializeWithScopeContext(): void
    {
        $exception = new \Exception('Test');
        $scope = new Scope();
        $scope->setContext('user_id', 123);
        $scope->setTag('env', 'production');

        $breadcrumbs = new Breadcrumbs();

        $event = ErrorSerializer::serialize(
            $exception,
            'error',
            $scope,
            $breadcrumbs->getAll()
        );

        $this->assertEquals(123, $event['context']['user_id']);
        $this->assertEquals('production', $event['context']['tags']['env']);
    }

    public function testSerializeWithLocalContext(): void
    {
        $exception = new \Exception('Test');
        $scope = new Scope();
        $breadcrumbs = new Breadcrumbs();

        $localContext = [
            'request_id' => 'abc123',
            'ip' => '192.168.1.1',
        ];

        $event = ErrorSerializer::serialize(
            $exception,
            'error',
            $scope,
            $breadcrumbs->getAll(),
            $localContext
        );

        $this->assertEquals('abc123', $event['context']['request_id']);
        $this->assertEquals('192.168.1.1', $event['context']['ip']);
    }

    public function testSerializeLocalContextOverridesScope(): void
    {
        $exception = new \Exception('Test');
        $scope = new Scope();
        $scope->setContext('key', 'global_value');

        $breadcrumbs = new Breadcrumbs();

        $localContext = [
            'key' => 'local_value',
        ];

        $event = ErrorSerializer::serialize(
            $exception,
            'error',
            $scope,
            $breadcrumbs->getAll(),
            $localContext
        );

        $this->assertEquals('local_value', $event['context']['key']);
    }

    public function testSerializeWithBreadcrumbs(): void
    {
        $exception = new \Exception('Test');
        $scope = new Scope();
        $breadcrumbs = new Breadcrumbs();

        $breadcrumbs->add(['message' => 'User clicked button', 'category' => 'ui']);
        $breadcrumbs->add(['message' => 'API call made', 'category' => 'http']);

        $event = ErrorSerializer::serialize(
            $exception,
            'error',
            $scope,
            $breadcrumbs->getAll()
        );

        $this->assertArrayHasKey('breadcrumbs', $event['context']);
        $this->assertCount(2, $event['context']['breadcrumbs']);
        $this->assertEquals('User clicked button', $event['context']['breadcrumbs'][0]['message']);
        $this->assertEquals('API call made', $event['context']['breadcrumbs'][1]['message']);
    }

    public function testSerializeWithEnvironment(): void
    {
        $exception = new \Exception('Test');
        $scope = new Scope();
        $breadcrumbs = new Breadcrumbs();

        $event = ErrorSerializer::serialize(
            $exception,
            'error',
            $scope,
            $breadcrumbs->getAll(),
            [],
            'production'
        );

        $this->assertEquals('production', $event['environment']);
    }

    public function testSerializeWithServerName(): void
    {
        $exception = new \Exception('Test');
        $scope = new Scope();
        $breadcrumbs = new Breadcrumbs();

        $event = ErrorSerializer::serialize(
            $exception,
            'error',
            $scope,
            $breadcrumbs->getAll(),
            [],
            null,
            'web-server-01'
        );

        $this->assertEquals('web-server-01', $event['server_name']);
    }

    public function testSerializeWithRelease(): void
    {
        $exception = new \Exception('Test');
        $scope = new Scope();
        $breadcrumbs = new Breadcrumbs();

        $event = ErrorSerializer::serialize(
            $exception,
            'error',
            $scope,
            $breadcrumbs->getAll(),
            [],
            null,
            null,
            'v1.2.3'
        );

        $this->assertEquals('v1.2.3', $event['release']);
    }

    public function testSerializeTimestampFormat(): void
    {
        $exception = new \Exception('Test');
        $scope = new Scope();
        $breadcrumbs = new Breadcrumbs();

        $event = ErrorSerializer::serialize(
            $exception,
            'error',
            $scope,
            $breadcrumbs->getAll()
        );

        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/',
            $event['timestamp']
        );
    }

    public function testSerializeMessage(): void
    {
        $scope = new Scope();
        $breadcrumbs = new Breadcrumbs();

        $event = ErrorSerializer::serializeMessage(
            'Custom log message',
            'info',
            $scope,
            $breadcrumbs->getAll()
        );

        $this->assertEquals('Custom log message', $event['message']);
        $this->assertEquals('info', $event['level']);
        $this->assertArrayNotHasKey('stack_trace', $event);
        $this->assertArrayHasKey('context', $event);
        $this->assertArrayHasKey('timestamp', $event);
    }

    public function testSerializeMessageWithContext(): void
    {
        $scope = new Scope();
        $scope->setContext('app', 'test-app');

        $breadcrumbs = new Breadcrumbs();

        $event = ErrorSerializer::serializeMessage(
            'Info message',
            'info',
            $scope,
            $breadcrumbs->getAll(),
            ['request_id' => 'xyz789']
        );

        $this->assertEquals('test-app', $event['context']['app']);
        $this->assertEquals('xyz789', $event['context']['request_id']);
    }

    public function testSerializeMessageWithBreadcrumbs(): void
    {
        $scope = new Scope();
        $breadcrumbs = new Breadcrumbs();
        $breadcrumbs->add(['message' => 'Step 1']);
        $breadcrumbs->add(['message' => 'Step 2']);

        $event = ErrorSerializer::serializeMessage(
            'Process completed',
            'info',
            $scope,
            $breadcrumbs->getAll()
        );

        $this->assertArrayHasKey('breadcrumbs', $event['context']);
        $this->assertCount(2, $event['context']['breadcrumbs']);
    }

    public function testSerializeMessageWithAllOptionalFields(): void
    {
        $scope = new Scope();
        $breadcrumbs = new Breadcrumbs();

        $event = ErrorSerializer::serializeMessage(
            'Test message',
            'warning',
            $scope,
            $breadcrumbs->getAll(),
            ['key' => 'value'],
            'staging',
            'api-server-02',
            'v2.0.0'
        );

        $this->assertEquals('Test message', $event['message']);
        $this->assertEquals('warning', $event['level']);
        $this->assertEquals('staging', $event['environment']);
        $this->assertEquals('api-server-02', $event['server_name']);
        $this->assertEquals('v2.0.0', $event['release']);
        $this->assertEquals('value', $event['context']['key']);
    }
}
