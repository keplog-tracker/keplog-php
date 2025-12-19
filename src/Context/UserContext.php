<?php

namespace Keplog\Context;

/**
 * Captures Laravel authenticated user context
 */
class UserContext
{
    /**
     * Capture authenticated user context
     *
     * This method works with Laravel's auth() helper or any authentication guard
     *
     * @param mixed $auth Laravel auth instance or null to use global auth() helper
     * @return array|null User context or null if not authenticated
     */
    public static function capture($auth = null): ?array
    {
        $user = null;

        // Try to get user from provided auth instance
        if ($auth !== null && method_exists($auth, 'user')) {
            $user = $auth->user();
        }
        // Try to use global auth() helper if available
        elseif (function_exists('auth')) {
            try {
                $user = auth()->user();
            } catch (\Throwable $e) {
                // auth() might not be available in non-web contexts
                return null;
            }
        }

        if ($user === null) {
            return null;
        }

        $context = [];

        // Standard Laravel User attributes
        if (isset($user->id)) {
            $context['id'] = (string) $user->id;
        }

        if (isset($user->email)) {
            $context['email'] = $user->email;
        }

        if (isset($user->name)) {
            $context['name'] = $user->name;
        }

        // Optional: username field
        if (isset($user->username)) {
            $context['username'] = $user->username;
        }

        // Return null if we couldn't extract any useful information
        return !empty($context) ? $context : null;
    }

    /**
     * Capture user context with custom fields
     *
     * @param mixed $user User object
     * @param array<string> $fields Additional fields to capture
     * @return array|null
     */
    public static function captureWithFields($user, array $fields = []): ?array
    {
        if ($user === null) {
            return null;
        }

        $context = [];

        // Standard fields
        if (isset($user->id)) {
            $context['id'] = (string) $user->id;
        }

        if (isset($user->email)) {
            $context['email'] = $user->email;
        }

        if (isset($user->name)) {
            $context['name'] = $user->name;
        }

        // Custom fields
        foreach ($fields as $field) {
            if (isset($user->$field)) {
                $context[$field] = $user->$field;
            }
        }

        return !empty($context) ? $context : null;
    }
}
