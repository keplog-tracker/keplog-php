<?php

namespace Keplog;

use Throwable;
use Keplog\Utils\StackTrace;

/**
 * Serializes PHP exceptions and messages into ErrorEvent format
 */
class ErrorSerializer
{
    /**
     * Serialize an exception into an ErrorEvent
     *
     * @param Throwable $exception
     * @param string $level
     * @param Scope $scope
     * @param array $breadcrumbs
     * @param array $localContext
     * @param string|null $environment
     * @param string|null $serverName
     * @param string|null $release
     * @return array
     */
    public static function serialize(
        Throwable $exception,
        string $level,
        Scope $scope,
        array $breadcrumbs,
        array $localContext = [],
        ?string $environment = null,
        ?string $serverName = null,
        ?string $release = null
    ): array {
        $message = $exception->getMessage() ?: 'Unknown error';
        $stackTrace = StackTrace::extract($exception);

        // Merge context
        $mergedContext = $scope->merge($localContext);

        // Add exception metadata to context
        $mergedContext['exception_class'] = get_class($exception);

        // Add enhanced stack frames with code snippets
        $mergedContext['frames'] = StackTrace::parse($exception);

        // Support queries field (if provided by framework)
        if (!isset($mergedContext['queries'])) {
            $mergedContext['queries'] = [];
        }

        // Add breadcrumbs if any
        if (!empty($breadcrumbs)) {
            $mergedContext['breadcrumbs'] = $breadcrumbs;
        }

        // Create event
        $event = [
            'message' => $message,
            'level' => $level,
            'stack_trace' => $stackTrace,
            'context' => $mergedContext,
            'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
        ];

        // Add optional fields
        if ($environment !== null) {
            $event['environment'] = $environment;
        }

        if ($serverName !== null) {
            $event['server_name'] = $serverName;
        }

        if ($release !== null) {
            $event['release'] = $release;
        }

        return $event;
    }

    /**
     * Serialize a message (not an exception) into an ErrorEvent
     *
     * @param string $message
     * @param string $level
     * @param Scope $scope
     * @param array $breadcrumbs
     * @param array $localContext
     * @param string|null $environment
     * @param string|null $serverName
     * @param string|null $release
     * @return array
     */
    public static function serializeMessage(
        string $message,
        string $level,
        Scope $scope,
        array $breadcrumbs,
        array $localContext = [],
        ?string $environment = null,
        ?string $serverName = null,
        ?string $release = null
    ): array {
        // Merge context
        $mergedContext = $scope->merge($localContext);

        // Add breadcrumbs if any
        if (!empty($breadcrumbs)) {
            $mergedContext['breadcrumbs'] = $breadcrumbs;
        }

        // Create event (no stack trace for messages)
        $event = [
            'message' => $message,
            'level' => $level,
            'context' => $mergedContext,
            'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
        ];

        // Add optional fields
        if ($environment !== null) {
            $event['environment'] = $environment;
        }

        if ($serverName !== null) {
            $event['server_name'] = $serverName;
        }

        if ($release !== null) {
            $event['release'] = $release;
        }

        return $event;
    }
}
