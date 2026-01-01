<?php

namespace Keplog;

use Throwable;
use Keplog\Utils\Environment;

/**
 * Main Keplog SDK client for error tracking
 *
 * @example
 * ```php
 * $keplog = new KeplogClient([
 *     'ingest_key' => 'kep_ingest_your-ingest-key',
 *     'environment' => 'production',
 * ]);
 *
 * try {
 *     riskyOperation();
 * } catch (Exception $e) {
 *     $keplog->captureException($e);
 * }
 * ```
 */
class KeplogClient
{
    private array $config;
    private Breadcrumbs $breadcrumbs;
    private Scope $scope;
    private Transport $transport;
    private bool $enabled;

    /**
     * ⚠️ Recursion guard to prevent infinite loops
     * If SDK throws error while capturing error, don't capture it again
     */
    private bool $isCapturing = false;

    public function __construct(array $config)
    {
        // Validate required config
        if (empty($config['ingest_key'])) {
            throw new \InvalidArgumentException('Keplog Ingest Key is required');
        }

        // Set config with defaults
        $this->config = [
            'ingest_key' => $config['ingest_key'],
            'base_url' => $config['base_url'] ?? 'http://localhost:8080',
            'environment' => $config['environment'] ?? Environment::detect(),
            'release' => $config['release'] ?? null,
            'server_name' => $config['server_name'] ?? Environment::detectServerName(),
            'max_breadcrumbs' => $config['max_breadcrumbs'] ?? 100,
            'enabled' => $config['enabled'] ?? true,
            'debug' => $config['debug'] ?? false,
            'timeout' => min($config['timeout'] ?? 5, 10), // Max 10 seconds
            'before_send' => $config['before_send'] ?? null,
        ];

        $this->enabled = $this->config['enabled'];

        // Initialize components
        $this->breadcrumbs = new Breadcrumbs($this->config['max_breadcrumbs']);
        $this->scope = new Scope();
        $this->transport = new Transport([
            'base_url' => $this->config['base_url'],
            'ingest_key' => $this->config['ingest_key'],
            'timeout' => $this->config['timeout'],
            'debug' => $this->config['debug'],
        ]);

        if ($this->config['debug']) {
            error_log('[Keplog] Client initialized: ' . json_encode([
                'environment' => $this->config['environment'],
                'server_name' => $this->config['server_name'],
                'release' => $this->config['release'],
            ]));
        }
    }

    /**
     * Capture an exception
     *
     * @param Throwable $exception
     * @param array $context
     * @return string|null Event ID if successful, null if failed
     */
    public function captureException(Throwable $exception, array $context = []): ?string
    {
        if (!$this->enabled) {
            return null;
        }

        // ⚠️ RECURSION GUARD: Prevent infinite loop
        // If SDK is already capturing an error, don't capture again
        if ($this->isCapturing) {
            if ($this->config['debug']) {
                error_log('[Keplog] Recursion detected: SDK error will not be captured to prevent infinite loop');
            }
            return null;
        }

        $this->isCapturing = true;

        try {
            // Serialize the exception
            $event = ErrorSerializer::serialize(
                $exception,
                'error',
                $this->scope,
                $this->breadcrumbs->getAll(),
                $context,
                $this->config['environment'],
                $this->config['server_name'],
                $this->config['release']
            );

            // Apply beforeSend hook if provided
            if (is_callable($this->config['before_send'])) {
                try {
                    $event = call_user_func($this->config['before_send'], $event);
                    if ($event === null) {
                        if ($this->config['debug']) {
                            error_log('[Keplog] Event dropped by beforeSend hook');
                        }
                        return null;
                    }
                } catch (\Throwable $e) {
                    // beforeSend callback threw error - log but don't capture
                    error_log('[Keplog] beforeSend callback threw error: ' . $e->getMessage());
                    return null;
                }
            }

            // Send the event
            $eventId = $this->transport->send($event);

            if ($this->config['debug'] && $eventId) {
                error_log('[Keplog] Exception captured successfully: ' . $eventId);
            }

            return $eventId;
        } catch (\Throwable $e) {
            // SDK internal error - log but don't try to capture
            if ($this->config['debug']) {
                error_log('[Keplog] Failed to capture exception: ' . $e->getMessage());
            }
            return null;
        } finally {
            // Always reset guard
            $this->isCapturing = false;
        }
    }

    /**
     * Capture a message (without stack trace)
     *
     * @param string $message
     * @param string $level
     * @param array $context
     * @return string|null Event ID if successful, null if failed
     */
    public function captureMessage(
        string $message,
        string $level = 'info',
        array $context = []
    ): ?string {
        if (!$this->enabled) {
            return null;
        }

        try {
            // Serialize the message
            $event = ErrorSerializer::serializeMessage(
                $message,
                $level,
                $this->scope,
                $this->breadcrumbs->getAll(),
                $context,
                $this->config['environment'],
                $this->config['server_name'],
                $this->config['release']
            );

            // Apply beforeSend hook if provided
            if (is_callable($this->config['before_send'])) {
                $event = call_user_func($this->config['before_send'], $event);
                if ($event === null) {
                    if ($this->config['debug']) {
                        error_log('[Keplog] Message dropped by beforeSend hook');
                    }
                    return null;
                }
            }

            return $this->transport->send($event);
        } catch (\Throwable $e) {
            if ($this->config['debug']) {
                error_log('[Keplog] Failed to capture message: ' . $e->getMessage());
            }
            return null;
        }
    }

    /**
     * Add a breadcrumb
     *
     * @param array $breadcrumb
     * @return void
     */
    public function addBreadcrumb(array $breadcrumb): void
    {
        if (!$this->enabled) {
            return;
        }

        $this->breadcrumbs->add($breadcrumb);

        if ($this->config['debug']) {
            error_log('[Keplog] Breadcrumb added: ' . json_encode($breadcrumb));
        }
    }

    /**
     * Set a context value
     *
     * @param string $key
     * @param mixed $value
     * @return void
     */
    public function setContext(string $key, mixed $value): void
    {
        $this->scope->setContext($key, $value);
    }

    /**
     * Set a tag
     *
     * @param string $key
     * @param string $value
     * @return void
     */
    public function setTag(string $key, string $value): void
    {
        $this->scope->setTag($key, $value);
    }

    /**
     * Set multiple tags
     *
     * @param array<string, string> $tags
     * @return void
     */
    public function setTags(array $tags): void
    {
        $this->scope->setTags($tags);
    }

    /**
     * Set user information
     *
     * @param array $user
     * @return void
     */
    public function setUser(array $user): void
    {
        $this->scope->setUser($user);
    }

    /**
     * Clear all scope data
     *
     * @return void
     */
    public function clearScope(): void
    {
        $this->scope->clear();
        $this->breadcrumbs->clear();

        if ($this->config['debug']) {
            error_log('[Keplog] Scope cleared');
        }
    }

    /**
     * Enable or disable error tracking
     *
     * @param bool $enabled
     * @return void
     */
    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;

        if ($this->config['debug']) {
            error_log('[Keplog] Tracking ' . ($enabled ? 'enabled' : 'disabled'));
        }
    }

    /**
     * Check if error tracking is enabled
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }
}
