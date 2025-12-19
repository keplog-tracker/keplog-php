<?php

namespace Keplog\Context;

/**
 * Captures Laravel HTTP request context
 */
class RequestContext
{
    /**
     * Capture request context from Laravel request object
     *
     * @param mixed $request Laravel request object (or any object with similar interface)
     * @return array<string, mixed>
     */
    public static function capture($request): array
    {
        if ($request === null) {
            return [];
        }

        $context = [];

        // URL and method
        if (method_exists($request, 'fullUrl')) {
            $context['url'] = $request->fullUrl();
        } elseif (method_exists($request, 'url')) {
            $context['url'] = $request->url();
        }

        if (method_exists($request, 'method')) {
            $context['method'] = $request->method();
        }

        // Client information
        if (method_exists($request, 'ip')) {
            $context['ip'] = $request->ip();
        }

        if (method_exists($request, 'userAgent')) {
            $context['user_agent'] = $request->userAgent();
        }

        // Headers (sanitized to remove sensitive data)
        if (method_exists($request, 'header')) {
            $headers = [];
            $sensitiveHeaders = ['authorization', 'cookie', 'set-cookie', 'x-api-key'];

            // Get all headers
            if (property_exists($request, 'headers') && method_exists($request->headers, 'all')) {
                $allHeaders = $request->headers->all();
            } else {
                $allHeaders = [];
            }

            foreach ($allHeaders as $key => $value) {
                $lowerKey = strtolower($key);
                if (!in_array($lowerKey, $sensitiveHeaders)) {
                    $headers[$key] = is_array($value) ? $value[0] : $value;
                }
            }

            if (!empty($headers)) {
                $context['headers'] = $headers;
            }
        }

        // Query parameters
        if (method_exists($request, 'query')) {
            $query = $request->query();
            if (!empty($query)) {
                $context['query'] = $query;
            }
        }

        // Request body (sanitized)
        if (method_exists($request, 'all')) {
            $body = $request->all();
            $sanitized = self::sanitizeData($body);
            if (!empty($sanitized)) {
                $context['body'] = $sanitized;
            }
        }

        // Route information
        if (method_exists($request, 'route')) {
            $route = $request->route();
            if ($route !== null) {
                if (method_exists($route, 'getName')) {
                    $context['route_name'] = $route->getName();
                }
                if (method_exists($route, 'getActionName')) {
                    $context['route_action'] = $route->getActionName();
                }
            }
        }

        return $context;
    }

    /**
     * Sanitize request data by removing sensitive fields
     *
     * @param array $data
     * @return array
     */
    private static function sanitizeData(array $data): array
    {
        $sensitiveKeys = [
            'password',
            'password_confirmation',
            'token',
            'api_key',
            'secret',
            'credit_card',
            'card_number',
            'cvv',
            'ssn',
        ];

        $sanitized = [];

        foreach ($data as $key => $value) {
            $lowerKey = strtolower($key);
            $isSensitive = false;

            foreach ($sensitiveKeys as $sensitiveKey) {
                if (str_contains($lowerKey, $sensitiveKey)) {
                    $isSensitive = true;
                    break;
                }
            }

            if ($isSensitive) {
                $sanitized[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $sanitized[$key] = self::sanitizeData($value);
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }
}
