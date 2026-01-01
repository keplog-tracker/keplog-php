<?php

namespace Keplog;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Transport layer for sending error events to the Keplog API
 */
class Transport
{
    private string $baseUrl;
    private string $ingestKey;
    private int $timeout;
    private bool $debug;
    private Client $client;

    public function __construct(array $config)
    {
        $this->baseUrl = $config['base_url'];
        $this->ingestKey = $config['ingest_key'];
        $this->timeout = $config['timeout'];
        $this->debug = $config['debug'];

        $this->client = new Client([
            'timeout' => $this->timeout,
        ]);
    }

    /**
     * Send an error event to the Keplog API
     *
     * This method fails silently to prevent SDK errors from affecting your app.
     * Network errors, timeouts, and server issues are logged but don't throw.
     *
     * @param array $event
     * @return string|null Event ID if successful, null if failed
     */
    public function send(array $event): ?string
    {
        try {
            // Validate event
            $this->validateEvent($event);

            // Normalize event to ensure empty arrays are objects in JSON
            $event = $this->normalizeEvent($event);

            $url = $this->baseUrl . '/api/ingest/v1/events';

            if ($this->debug) {
                error_log('[Keplog] Sending event (timeout: ' . $this->timeout . 's): ' . json_encode($event));
            }

            $response = $this->client->post($url, [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'X-Ingest-Key' => $this->ingestKey,
                ],
                'json' => $event,
                'timeout' => $this->timeout,
                'connect_timeout' => min($this->timeout, 5), // Connect timeout max 5s
            ]);

            if ($response->getStatusCode() === 202) {
                $data = json_decode($response->getBody()->getContents(), true);
                if ($this->debug) {
                    error_log('[Keplog] Event queued successfully: ' . json_encode($data));
                }
                return 'evt_' . time() . '_' . bin2hex(random_bytes(4));
            }

            return null;
        } catch (GuzzleException $e) {
            // Handle timeout errors specifically
            if ($e instanceof \GuzzleHttp\Exception\ConnectException ||
                $e instanceof \GuzzleHttp\Exception\RequestException) {
                if (strpos($e->getMessage(), 'timed out') !== false ||
                    strpos($e->getMessage(), 'timeout') !== false) {
                    error_log(
                        '[Keplog] Timeout: Request to Keplog API exceeded ' . $this->timeout . 's. ' .
                        'Check your network connection and server status.'
                    );
                    if ($this->debug) {
                        error_log('[Keplog] Timeout details: ' . $e->getMessage());
                    }
                    return null;
                }
            }

            // Handle specific HTTP errors
            if ($e->hasResponse()) {
                $statusCode = $e->getResponse()->getStatusCode();
                $body = $e->getResponse()->getBody()->getContents();

                if ($statusCode === 401) {
                    error_log('[Keplog] Invalid Ingest Key - please check your configuration');
                } elseif ($statusCode === 400) {
                    $errorData = json_decode($body, true);
                    error_log('[Keplog] Validation error: ' . ($errorData['error'] ?? 'Unknown error'));
                } else {
                    if ($this->debug) {
                        error_log('[Keplog] Unexpected response status: ' . $statusCode);
                    }
                }
            } else {
                if ($this->debug) {
                    error_log('[Keplog] Failed to send event: ' . $e->getMessage());
                }
            }

            return null;
        } catch (\Throwable $e) {
            // Silent failure - SDK errors should never crash the app
            if ($this->debug) {
                error_log('[Keplog] Error: ' . $e->getMessage());
            }

            return null;
        }
    }

    /**
     * Validate and truncate event fields
     *
     * @param array &$event
     * @return void
     * @throws \InvalidArgumentException
     */
    private function validateEvent(array &$event): void
    {
        // Message validation (max 10KB)
        if (empty($event['message'])) {
            throw new \InvalidArgumentException('Event message is required');
        }

        if (strlen($event['message']) > 10000) {
            $event['message'] = substr($event['message'], 0, 10000) . '...[truncated]';
            if ($this->debug) {
                error_log('[Keplog] Message truncated to 10KB');
            }
        }

        // Stack trace validation (max 500KB)
        if (isset($event['stack_trace']) && strlen($event['stack_trace']) > 500000) {
            $event['stack_trace'] = substr($event['stack_trace'], 0, 500000) . "\n...[truncated]";
            if ($this->debug) {
                error_log('[Keplog] Stack trace truncated to 500KB');
            }
        }

        // Context validation (max 256KB when serialized)
        if (isset($event['context'])) {
            $contextSize = strlen(json_encode($event['context']));
            if ($contextSize > 256000) {
                $event['context'] = [
                    '_error' => 'Context too large and was truncated',
                    '_original_size' => $contextSize,
                    '_max_size' => 256000,
                ];
                if ($this->debug) {
                    error_log('[Keplog] Context truncated due to size limit (256KB)');
                }
            }
        }

        // Level validation
        $validLevels = ['critical', 'error', 'warning', 'info', 'debug'];
        if (!in_array($event['level'], $validLevels)) {
            throw new \InvalidArgumentException(
                'Invalid level: ' . $event['level'] . '. Must be one of: ' . implode(', ', $validLevels)
            );
        }
    }

    /**
     * Normalize event data to ensure empty arrays become objects in JSON
     *
     * This fixes the issue where empty context [] becomes JSON array instead of object {}
     *
     * @param array $event
     * @return array
     */
    private function normalizeEvent(array $event): array
    {
        // Convert empty context array to object
        if (isset($event['context']) && is_array($event['context']) && empty($event['context'])) {
            $event['context'] = new \stdClass();
        }

        return $event;
    }
}
