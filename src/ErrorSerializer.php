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
     * Reserved context keys (SDK-managed)
     */
    private const RESERVED_CONTEXT_KEYS = [
        'exception_class',
        'frames',
        'queries',
        'request',
        'user',
        'breadcrumbs',
    ];

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

        // Merge context from scope and local
        $mergedContext = $scope->merge($localContext);

        // Separate system context and user-defined extra context
        $systemContext = [];
        $extraContext = [];

        foreach ($mergedContext as $key => $value) {
            if (in_array($key, self::RESERVED_CONTEXT_KEYS)) {
                $systemContext[$key] = $value;
            } else {
                $extraContext[$key] = $value;
            }
        }

        // Add SDK-generated context
        $systemContext['exception_class'] = get_class($exception);
        $systemContext['frames'] = StackTrace::parse($exception);

        // Ensure queries field exists (if not provided by framework)
        if (!isset($systemContext['queries'])) {
            $systemContext['queries'] = [];
        }

        // Add breadcrumbs if any
        if (!empty($breadcrumbs)) {
            $systemContext['breadcrumbs'] = $breadcrumbs;
        }

        // Create event
        $event = [
            'message' => $message,
            'level' => $level,
            'stack_trace' => $stackTrace,
            'context' => $systemContext,
            'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
        ];

        // Add extra_context if there are user-defined fields
        if (!empty($extraContext)) {
            $event['extra_context'] = $extraContext;
        }

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
        // Merge context from scope and local
        $mergedContext = $scope->merge($localContext);

        // Separate system context and user-defined extra context
        $systemContext = [];
        $extraContext = [];

        foreach ($mergedContext as $key => $value) {
            if (in_array($key, self::RESERVED_CONTEXT_KEYS)) {
                $systemContext[$key] = $value;
            } else {
                $extraContext[$key] = $value;
            }
        }

        // Add breadcrumbs if any
        if (!empty($breadcrumbs)) {
            $systemContext['breadcrumbs'] = $breadcrumbs;
        }

        // Create event (no stack trace for messages)
        $event = [
            'message' => $message,
            'level' => $level,
            'context' => $systemContext,
            'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
        ];

        // Add extra_context if there are user-defined fields
        if (!empty($extraContext)) {
            $event['extra_context'] = $extraContext;
        }

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
