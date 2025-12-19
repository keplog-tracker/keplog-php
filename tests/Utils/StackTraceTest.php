<?php

namespace Keplog\Tests\Utils;

use PHPUnit\Framework\TestCase;
use Keplog\Utils\StackTrace;

class StackTraceTest extends TestCase
{
    public function testExtractReturnsFormattedStackTrace(): void
    {
        $exception = new \Exception('Test error', 123);

        $stackTrace = StackTrace::extract($exception);

        $this->assertIsString($stackTrace);
        $this->assertStringContainsString('Exception: Test error', $stackTrace);
        $this->assertStringContainsString('Stack trace:', $stackTrace);
        $this->assertStringContainsString(__FILE__, $stackTrace);
    }

    public function testExtractIncludesFileAndLine(): void
    {
        $exception = new \RuntimeException('Runtime error');

        $stackTrace = StackTrace::extract($exception);

        $this->assertStringContainsString('in ' . __FILE__, $stackTrace);
        $this->assertMatchesRegularExpression('/:\d+/', $stackTrace);
    }

    public function testExtractWithDifferentExceptionTypes(): void
    {
        $exceptionTypes = [
            \RuntimeException::class,
            \InvalidArgumentException::class,
            \LogicException::class,
        ];

        foreach ($exceptionTypes as $exceptionType) {
            $exception = new $exceptionType('Test message');
            $stackTrace = StackTrace::extract($exception);

            $this->assertStringContainsString($exceptionType, $stackTrace);
            $this->assertStringContainsString('Test message', $stackTrace);
        }
    }

    public function testExtractWithNestedExceptions(): void
    {
        $previous = new \RuntimeException('Previous error');
        $exception = new \Exception('Current error', 0, $previous);

        $stackTrace = StackTrace::extract($exception);

        $this->assertStringContainsString('Exception: Current error', $stackTrace);
        $this->assertStringContainsString('Stack trace:', $stackTrace);
    }
}
