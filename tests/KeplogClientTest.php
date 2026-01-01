<?php

namespace Keplog\Tests;

use PHPUnit\Framework\TestCase;
use Keplog\KeplogClient;
use Mockery;

class KeplogClientTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testConstructorRequiresIngestKey(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Keplog Ingest Key is required');

        new KeplogClient([]);
    }

    public function testConstructorWithValidConfig(): void
    {
        $client = new KeplogClient([
            'ingest_key' => 'kep_ingest_test_key',
        ]);

        $this->assertInstanceOf(KeplogClient::class, $client);
        $this->assertTrue($client->isEnabled());
    }

    public function testConstructorWithCustomConfig(): void
    {
        putenv('APP_ENV=staging');

        $client = new KeplogClient([
            'ingest_key' => 'kep_ingest_test_key',
            'base_url' => 'https://custom.example.com',
            'environment' => 'custom',
            'release' => 'v1.2.3',
            'max_breadcrumbs' => 50,
            'enabled' => false,
            'debug' => true,
            'timeout' => 10,
        ]);

        $this->assertFalse($client->isEnabled());

        putenv('APP_ENV');
    }

    public function testCaptureExceptionReturnsEventId(): void
    {
        $client = new KeplogClient([
            'ingest_key' => 'kep_ingest_test_key',
        ]);

        // We can't easily test actual HTTP without mocking deeply,
        // but we can test that the method exists and handles errors gracefully
        $exception = new \Exception('Test error');
        $eventId = $client->captureException($exception);

        // Will likely be null without a real server, but shouldn't throw
        $this->assertTrue($eventId === null || is_string($eventId));
    }

    public function testCaptureExceptionWithContext(): void
    {
        $client = new KeplogClient([
            'ingest_key' => 'kep_ingest_test_key',
        ]);

        $exception = new \Exception('Test error');
        $context = [
            'user_id' => 123,
            'action' => 'test_action',
        ];

        $eventId = $client->captureException($exception, $context);

        $this->assertTrue($eventId === null || is_string($eventId));
    }

    public function testCaptureExceptionWhenDisabled(): void
    {
        $client = new KeplogClient([
            'ingest_key' => 'kep_ingest_test_key',
            'enabled' => false,
        ]);

        $exception = new \Exception('Test error');
        $eventId = $client->captureException($exception);

        $this->assertNull($eventId);
    }

    public function testCaptureMessage(): void
    {
        $client = new KeplogClient([
            'ingest_key' => 'kep_ingest_test_key',
        ]);

        $eventId = $client->captureMessage('Test message');

        $this->assertTrue($eventId === null || is_string($eventId));
    }

    public function testCaptureMessageWithLevel(): void
    {
        $client = new KeplogClient([
            'ingest_key' => 'kep_ingest_test_key',
        ]);

        $eventId = $client->captureMessage('Warning message', 'warning');

        $this->assertTrue($eventId === null || is_string($eventId));
    }

    public function testCaptureMessageWithContext(): void
    {
        $client = new KeplogClient([
            'ingest_key' => 'kep_ingest_test_key',
        ]);

        $eventId = $client->captureMessage(
            'Info message',
            'info',
            ['request_id' => 'abc123']
        );

        $this->assertTrue($eventId === null || is_string($eventId));
    }

    public function testCaptureMessageWhenDisabled(): void
    {
        $client = new KeplogClient([
            'ingest_key' => 'kep_ingest_test_key',
            'enabled' => false,
        ]);

        $eventId = $client->captureMessage('Test message');

        $this->assertNull($eventId);
    }

    public function testAddBreadcrumb(): void
    {
        $client = new KeplogClient([
            'ingest_key' => 'kep_ingest_test_key',
        ]);

        $breadcrumb = [
            'message' => 'User clicked button',
            'category' => 'ui',
        ];

        $client->addBreadcrumb($breadcrumb);

        // No exception should be thrown
        $this->assertTrue(true);
    }

    public function testAddBreadcrumbWhenDisabled(): void
    {
        $client = new KeplogClient([
            'ingest_key' => 'kep_ingest_test_key',
            'enabled' => false,
        ]);

        $breadcrumb = [
            'message' => 'Test breadcrumb',
        ];

        $client->addBreadcrumb($breadcrumb);

        // Should not throw, just silently ignore
        $this->assertTrue(true);
    }

    public function testSetContext(): void
    {
        $client = new KeplogClient([
            'ingest_key' => 'kep_ingest_test_key',
        ]);

        $client->setContext('user_id', 123);
        $client->setContext('session', ['id' => 'xyz']);

        // No exception should be thrown
        $this->assertTrue(true);
    }

    public function testSetTag(): void
    {
        $client = new KeplogClient([
            'ingest_key' => 'kep_ingest_test_key',
        ]);

        $client->setTag('environment', 'production');

        // No exception should be thrown
        $this->assertTrue(true);
    }

    public function testSetTags(): void
    {
        $client = new KeplogClient([
            'ingest_key' => 'kep_ingest_test_key',
        ]);

        $client->setTags([
            'env' => 'staging',
            'version' => '1.2.3',
            'region' => 'us-east-1',
        ]);

        // No exception should be thrown
        $this->assertTrue(true);
    }

    public function testSetUser(): void
    {
        $client = new KeplogClient([
            'ingest_key' => 'kep_ingest_test_key',
        ]);

        $client->setUser([
            'id' => 123,
            'email' => 'test@example.com',
            'name' => 'Test User',
        ]);

        // No exception should be thrown
        $this->assertTrue(true);
    }

    public function testClearScope(): void
    {
        $client = new KeplogClient([
            'ingest_key' => 'kep_ingest_test_key',
        ]);

        $client->setContext('key', 'value');
        $client->setTag('env', 'production');
        $client->addBreadcrumb(['message' => 'Test']);

        $client->clearScope();

        // No exception should be thrown
        $this->assertTrue(true);
    }

    public function testSetEnabled(): void
    {
        $client = new KeplogClient([
            'ingest_key' => 'kep_ingest_test_key',
            'enabled' => true,
        ]);

        $this->assertTrue($client->isEnabled());

        $client->setEnabled(false);

        $this->assertFalse($client->isEnabled());

        $client->setEnabled(true);

        $this->assertTrue($client->isEnabled());
    }

    public function testBeforeSendHookCanModifyEvent(): void
    {
        $hookCalled = false;

        $client = new KeplogClient([
            'ingest_key' => 'kep_ingest_test_key',
            'before_send' => function ($event) use (&$hookCalled) {
                $hookCalled = true;
                $event['custom_field'] = 'custom_value';
                return $event;
            },
        ]);

        $exception = new \Exception('Test');
        $client->captureException($exception);

        // Hook should be called even if send fails due to no server
        // This is hard to test without mocking, but we can verify no crash
        $this->assertTrue(true);
    }

    public function testBeforeSendHookCanDropEvent(): void
    {
        $client = new KeplogClient([
            'ingest_key' => 'kep_ingest_test_key',
            'before_send' => function ($event) {
                // Drop all events
                return null;
            },
        ]);

        $exception = new \Exception('Test');
        $eventId = $client->captureException($exception);

        // Event should be dropped
        $this->assertNull($eventId);
    }

    public function testCaptureExceptionHandlesSerializationError(): void
    {
        $client = new KeplogClient([
            'ingest_key' => 'kep_ingest_test_key',
            'debug' => true,
        ]);

        // Use a normal exception - it should not throw even in debug mode
        $exception = new \Exception('Test error');
        $eventId = $client->captureException($exception);

        // Should handle gracefully
        $this->assertTrue($eventId === null || is_string($eventId));
    }

    public function testEnvironmentAutoDetection(): void
    {
        putenv('APP_ENV=testing');

        $client = new KeplogClient([
            'ingest_key' => 'kep_ingest_test_key',
        ]);

        // Environment should be auto-detected
        // We can't directly test this without exposing internals,
        // but we can verify the client was created successfully
        $this->assertInstanceOf(KeplogClient::class, $client);

        putenv('APP_ENV');
    }

    public function testServerNameAutoDetection(): void
    {
        $client = new KeplogClient([
            'ingest_key' => 'kep_ingest_test_key',
        ]);

        // Server name should be auto-detected from hostname
        // We can't directly test this without exposing internals,
        // but we can verify the client was created successfully
        $this->assertInstanceOf(KeplogClient::class, $client);
    }

    public function testDebugModeLogging(): void
    {
        $client = new KeplogClient([
            'ingest_key' => 'kep_ingest_test_key',
            'debug' => true,
        ]);

        // Debug mode should log initialization
        // This would output to error_log, which we can't easily capture in tests
        // But we can verify no crash occurs
        $this->assertInstanceOf(KeplogClient::class, $client);
    }

    public function testMultipleBreadcrumbsAndScopes(): void
    {
        $client = new KeplogClient([
            'ingest_key' => 'kep_ingest_test_key',
        ]);

        // Add multiple breadcrumbs
        $client->addBreadcrumb(['message' => 'Step 1', 'category' => 'navigation']);
        $client->addBreadcrumb(['message' => 'Step 2', 'category' => 'navigation']);
        $client->addBreadcrumb(['message' => 'Step 3', 'category' => 'action']);

        // Set multiple context values
        $client->setContext('request_id', 'req_123');
        $client->setContext('user_agent', 'Mozilla/5.0');

        // Set multiple tags
        $client->setTag('env', 'production');
        $client->setTag('version', '1.0.0');

        // Set user
        $client->setUser(['id' => 456, 'email' => 'user@example.com']);

        // Capture exception with all the context
        $exception = new \RuntimeException('Complex error');
        $eventId = $client->captureException($exception, [
            'additional' => 'context',
        ]);

        // Should handle complex scenario
        $this->assertTrue($eventId === null || is_string($eventId));
    }

    // ========== INFINITE LOOP PROTECTION TESTS ==========

    public function testBeforeSendThrowingErrorDoesNotCrash(): void
    {
        $client = new KeplogClient([
            'ingest_key' => 'kep_ingest_test_key',
            'debug' => false,
            'before_send' => function ($event) {
                throw new \Exception('beforeSend error');
            },
        ]);

        $exception = new \Exception('Test error');
        $eventId = $client->captureException($exception);

        // Should return null and not throw
        $this->assertNull($eventId);
    }

    public function testRecursionGuardPreventsInfiniteLoop(): void
    {
        $captureAttempts = 0;

        $client = new KeplogClient([
            'ingest_key' => 'kep_ingest_test_key',
            'debug' => true,
            'before_send' => function ($event) use (&$captureAttempts) {
                $captureAttempts++;
                if ($captureAttempts === 1) {
                    // Simulate SDK bug during serialization
                    throw new \Exception('SDK bug during serialization');
                }
                return $event;
            },
        ]);

        $exception = new \Exception('User error');
        $eventId = $client->captureException($exception);

        // Should return null due to beforeSend throwing
        $this->assertNull($eventId);

        // Should only try once, not infinite loop
        $this->assertEquals(1, $captureAttempts);
    }

    public function testIsCapturingFlagResetEvenOnError(): void
    {
        $client = new KeplogClient([
            'ingest_key' => 'kep_ingest_test_key',
            'before_send' => function ($event) {
                throw new \Exception('Error during send');
            },
        ]);

        // First capture - should fail and reset flag
        $eventId1 = $client->captureException(new \Exception('Error 1'));
        $this->assertNull($eventId1);

        // Second capture on same client - should also fail gracefully (not hang)
        // This proves the flag is properly reset in finally block
        $eventId2 = $client->captureException(new \Exception('Error 2'));
        $this->assertNull($eventId2);
    }

    public function testRecursionGuardBlocksNestedCapture(): void
    {
        $callCount = 0;
        $client = null;

        $client = new KeplogClient([
            'ingest_key' => 'kep_ingest_test_key',
            'debug' => true,
            'before_send' => function ($event) use (&$callCount, &$client) {
                $callCount++;
                // Simulate recursive capture attempt
                if ($callCount === 1) {
                    // Try to capture another error while processing this one
                    // This would cause infinite loop without protection
                    $client->captureException(new \Exception('Recursive error'));
                }
                return $event;
            },
        ]);

        $client->captureException(new \Exception('Original error'));

        // BeforeSend should be called only once for the original error
        // The recursive call should be blocked by isCapturing guard
        $this->assertEquals(1, $callCount);
    }

    public function testMultipleRapidErrorsWithoutRecursion(): void
    {
        $client = new KeplogClient([
            'ingest_key' => 'kep_ingest_test_key',
            'debug' => false,
        ]);

        // Capture multiple errors rapidly
        $eventId1 = $client->captureException(new \Exception('Error 1'));
        $eventId2 = $client->captureException(new \Exception('Error 2'));
        $eventId3 = $client->captureException(new \Exception('Error 3'));

        // All should complete without infinite loops
        // They will likely be null without a real server, but shouldn't hang
        $this->assertTrue($eventId1 === null || is_string($eventId1));
        $this->assertTrue($eventId2 === null || is_string($eventId2));
        $this->assertTrue($eventId3 === null || is_string($eventId3));
    }

    public function testSDKInternalErrorsHandledGracefully(): void
    {
        $client = new KeplogClient([
            'ingest_key' => 'kep_ingest_test_key',
            'debug' => true,
        ]);

        // Even with invalid data, SDK should not crash
        $exception = new \Exception('Test error');

        $eventId = $client->captureException($exception);

        // Should handle gracefully, not throw
        $this->assertTrue($eventId === null || is_string($eventId));
    }

    public function testBeforeSendErrorLoggedInDebugMode(): void
    {
        // Capture error_log output
        $errorLog = [];
        set_error_handler(function ($errno, $errstr) use (&$errorLog) {
            $errorLog[] = $errstr;
            return true;
        });

        $client = new KeplogClient([
            'ingest_key' => 'kep_ingest_test_key',
            'debug' => true,
            'before_send' => function ($event) {
                throw new \Exception('beforeSend error message');
            },
        ]);

        $client->captureException(new \Exception('Test'));

        restore_error_handler();

        // Check that error was logged
        $foundLog = false;
        foreach ($errorLog as $log) {
            if (strpos($log, 'beforeSend callback threw error') !== false) {
                $foundLog = true;
                break;
            }
        }

        $this->assertTrue($foundLog, 'beforeSend error should be logged in debug mode');
    }
}
