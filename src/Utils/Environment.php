<?php

namespace Keplog\Utils;

/**
 * Environment detection utilities
 */
class Environment
{
    /**
     * Detect the current environment
     *
     * @return string
     */
    public static function detect(): string
    {
        return getenv('APP_ENV') ?: 'production';
    }

    /**
     * Detect the server name/hostname
     *
     * @return string
     */
    public static function detectServerName(): string
    {
        return gethostname() ?: 'unknown';
    }

    /**
     * Detect release version from environment
     *
     * @return string|null
     */
    public static function detectRelease(): ?string
    {
        return getenv('APP_VERSION') ?: null;
    }
}
