<?php

namespace Keplog\Utils;

use Throwable;

/**
 * Stack trace formatting utilities for PHP exceptions
 */
class StackTrace
{
    /**
     * Extract and format stack trace from exception
     *
     * @param Throwable $exception
     * @return string|null
     */
    public static function extract(Throwable $exception): ?string
    {
        $trace = $exception->getTraceAsString();

        if (empty($trace)) {
            return null;
        }

        // Include exception message at the top
        $formatted = get_class($exception) . ': ' . $exception->getMessage();
        $formatted .= ' in ' . $exception->getFile() . ':' . $exception->getLine();
        $formatted .= "\n\nStack trace:\n" . $trace;

        return $formatted;
    }

    /**
     * Parse stack trace into structured format
     *
     * @param Throwable $exception
     * @return array
     */
    public static function parse(Throwable $exception): array
    {
        $frames = [];
        $trace = $exception->getTrace();

        foreach ($trace as $index => $frame) {
            $frames[] = [
                'file' => $frame['file'] ?? 'unknown',
                'line' => $frame['line'] ?? 0,
                'function' => $frame['function'] ?? null,
                'class' => $frame['class'] ?? null,
                'type' => $frame['type'] ?? null,
            ];
        }

        return $frames;
    }
}
