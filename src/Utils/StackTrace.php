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
     * Parse stack trace into structured format with code snippets
     *
     * @param Throwable $exception
     * @param int $contextLines Number of lines to show before/after error line
     * @return array
     */
    public static function parse(Throwable $exception, int $contextLines = 3): array
    {
        $frames = [];
        $trace = $exception->getTrace();

        // Add the exception origin as first frame
        $originFrame = [
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'function' => null,
            'class' => null,
            'type' => null,
        ];

        array_unshift($trace, $originFrame);

        foreach ($trace as $index => $frame) {
            $file = $frame['file'] ?? null;
            $line = $frame['line'] ?? 0;

            $parsedFrame = [
                'file' => $file ?? 'unknown',
                'line' => $line,
                'function' => $frame['function'] ?? null,
                'class' => $frame['class'] ?? null,
                'type' => $frame['type'] ?? null,
            ];

            // Add code snippet if file exists and is readable
            if ($file && is_readable($file)) {
                $parsedFrame['code_snippet'] = self::extractCodeSnippet($file, $line, $contextLines);
            }

            // Classify frame as vendor or application
            $parsedFrame['is_vendor'] = self::isVendorFrame($file);
            $parsedFrame['is_application'] = !$parsedFrame['is_vendor'];

            $frames[] = $parsedFrame;
        }

        return $frames;
    }

    /**
     * Extract code snippet around a specific line
     *
     * @param string $file
     * @param int $line
     * @param int $contextLines
     * @return array
     */
    protected static function extractCodeSnippet(string $file, int $line, int $contextLines = 3): array
    {
        if (!is_readable($file)) {
            return [];
        }

        try {
            $content = file($file, FILE_IGNORE_NEW_LINES);
            if ($content === false) {
                return [];
            }

            $startLine = max(0, $line - $contextLines - 1);
            $endLine = min(count($content), $line + $contextLines);

            $snippet = [];
            for ($i = $startLine; $i < $endLine; $i++) {
                $lineNumber = $i + 1;
                $snippet[$lineNumber] = $content[$i];
            }

            return $snippet;
        } catch (\Throwable $e) {
            // If we can't read the file, return empty array
            return [];
        }
    }

    /**
     * Determine if a frame is from vendor directory
     *
     * @param string|null $file
     * @return bool
     */
    protected static function isVendorFrame(?string $file): bool
    {
        if ($file === null) {
            return false;
        }

        // Normalize path separators
        $normalizedPath = str_replace('\\', '/', $file);

        // Check if path contains /vendor/
        return strpos($normalizedPath, '/vendor/') !== false;
    }
}
