<?php

/**
 * Exception Handler Setup Examples
 *
 * Different approaches to integrate Keplog with Laravel's exception handling
 */

// =============================================================================
// APPROACH 1: Basic Integration (Recommended)
// =============================================================================

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Keplog\Context\RequestContext;
use Keplog\Context\UserContext;
use Throwable;

class Handler extends ExceptionHandler
{
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            if (app()->bound('keplog')) {
                app('keplog')->captureException($e, [
                    'request' => RequestContext::capture(request()),
                    'user' => UserContext::capture(),
                ]);
            }
        });
    }
}

// =============================================================================
// APPROACH 2: Selective Reporting
// =============================================================================

class SelectiveHandler extends ExceptionHandler
{
    /**
     * Exceptions that should be reported to Keplog
     */
    private $keplogReportable = [
        \RuntimeException::class,
        \LogicException::class,
        \PDOException::class,
    ];

    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            // Only report specific exception types
            foreach ($this->keplogReportable as $type) {
                if ($e instanceof $type) {
                    $this->reportToKeplog($e);
                    break;
                }
            }
        });
    }

    private function reportToKeplog(Throwable $e): void
    {
        if (!app()->bound('keplog')) {
            return;
        }

        $keplog = app('keplog');

        $context = [
            'request' => RequestContext::capture(request()),
            'user' => UserContext::capture(),
            'exception_type' => get_class($e),
        ];

        $keplog->captureException($e, $context);
    }
}

// =============================================================================
// APPROACH 3: With Severity Levels
// =============================================================================

class SeverityAwareHandler extends ExceptionHandler
{
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            if (!app()->bound('keplog')) {
                return;
            }

            $keplog = app('keplog');

            // Determine severity based on exception type
            $level = $this->getSeverityLevel($e);

            // Set tag for severity
            $keplog->setTag('severity', $level);

            $context = [
                'request' => RequestContext::capture(request()),
                'user' => UserContext::capture(),
                'level' => $level,
            ];

            $keplog->captureException($e, $context);
        });
    }

    private function getSeverityLevel(Throwable $e): string
    {
        // Critical errors
        if ($e instanceof \PDOException || $e instanceof \ErrorException) {
            return 'critical';
        }

        // Application errors
        if ($e instanceof \RuntimeException || $e instanceof \LogicException) {
            return 'error';
        }

        // Everything else
        return 'warning';
    }
}

// =============================================================================
// APPROACH 4: With Breadcrumbs Tracking
// =============================================================================

class BreadcrumbTrackingHandler extends ExceptionHandler
{
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            if (!app()->bound('keplog')) {
                return;
            }

            $keplog = app('keplog');

            // Add exception breadcrumb
            $keplog->addBreadcrumb([
                'message' => 'Exception occurred: ' . get_class($e),
                'category' => 'exception',
                'level' => 'error',
                'data' => [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ],
            ]);

            $context = [
                'request' => RequestContext::capture(request()),
                'user' => UserContext::capture(),
            ];

            // Add route breadcrumb if available
            if (request()->route()) {
                $keplog->addBreadcrumb([
                    'message' => 'Route: ' . request()->route()->getName(),
                    'category' => 'navigation',
                    'level' => 'info',
                ]);
            }

            $keplog->captureException($e, $context);
        });
    }
}

// =============================================================================
// APPROACH 5: With Environment-Based Filtering
// =============================================================================

class EnvironmentFilteredHandler extends ExceptionHandler
{
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            // Only report to Keplog in production and staging
            if (!in_array(app()->environment(), ['production', 'staging'])) {
                return;
            }

            if (!app()->bound('keplog')) {
                return;
            }

            $keplog = app('keplog');

            $context = [
                'request' => RequestContext::capture(request()),
                'user' => UserContext::capture(),
                'environment' => app()->environment(),
            ];

            $keplog->captureException($e, $context);
        });
    }
}

// =============================================================================
// APPROACH 6: With Rate Limiting (Prevent Spam)
// =============================================================================

use Illuminate\Support\Facades\Cache;

class RateLimitedHandler extends ExceptionHandler
{
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            if (!app()->bound('keplog')) {
                return;
            }

            // Create a unique key for this error
            $errorKey = 'keplog_error_' . md5(get_class($e) . $e->getFile() . $e->getLine());

            // Check if we've already reported this error recently (within 5 minutes)
            if (Cache::has($errorKey)) {
                return; // Skip reporting
            }

            // Mark this error as reported
            Cache::put($errorKey, true, now()->addMinutes(5));

            $keplog = app('keplog');

            $context = [
                'request' => RequestContext::capture(request()),
                'user' => UserContext::capture(),
            ];

            $keplog->captureException($e, $context);
        });
    }
}

// =============================================================================
// APPROACH 7: With Additional Context from Request
// =============================================================================

class EnrichedContextHandler extends ExceptionHandler
{
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            if (!app()->bound('keplog')) {
                return;
            }

            $keplog = app('keplog');

            // Build comprehensive context
            $context = [
                'request' => RequestContext::capture(request()),
                'user' => UserContext::capture(),
            ];

            // Add database query count if available
            if (config('database.log_queries')) {
                $context['db_queries'] = count(\DB::getQueryLog());
            }

            // Add memory usage
            $context['memory_usage'] = [
                'current' => memory_get_usage(true),
                'peak' => memory_get_peak_usage(true),
            ];

            // Add request timing
            if (defined('LARAVEL_START')) {
                $context['request_duration'] = microtime(true) - LARAVEL_START;
            }

            // Add session data (sanitized)
            if (session()->isStarted()) {
                $context['session'] = [
                    'id' => session()->getId(),
                    'started_at' => session()->get('_previous.created_at'),
                ];
            }

            $keplog->captureException($e, $context);
        });
    }
}

// =============================================================================
// APPROACH 8: With beforeSend Hook for Filtering
// =============================================================================

// In AppServiceProvider::register()

use Keplog\KeplogClient;

public function register(): void
{
    $this->app->singleton('keplog', function ($app) {
        return new KeplogClient([
            'ingest_key' => env('KEPLOG_INGEST_KEY'),
            'base_url' => env('KEPLOG_BASE_URL', 'http://localhost:8080'),
            'environment' => env('APP_ENV', 'production'),
            'before_send' => function ($event) {
                // Don't send 404 errors
                if (isset($event['context']['request']['status_code']) &&
                    $event['context']['request']['status_code'] === 404) {
                    return null;
                }

                // Don't send validation errors
                if (str_contains($event['message'], 'validation')) {
                    return null;
                }

                // Sanitize sensitive data
                if (isset($event['context']['request']['body']['password'])) {
                    $event['context']['request']['body']['password'] = '[REDACTED]';
                }

                return $event;
            },
        ]);
    });
}

// =============================================================================
// APPROACH 9: Multiple Reportable Handlers
// =============================================================================

class MultipleHandlersHandler extends ExceptionHandler
{
    public function register(): void
    {
        // Report HTTP client errors
        $this->reportable(function (\Illuminate\Http\Client\RequestException $e) {
            $this->reportHttpError($e);
        });

        // Report database errors
        $this->reportable(function (\Illuminate\Database\QueryException $e) {
            $this->reportDatabaseError($e);
        });

        // Report all other errors
        $this->reportable(function (Throwable $e) {
            $this->reportGenericError($e);
        });
    }

    private function reportHttpError(\Illuminate\Http\Client\RequestException $e): void
    {
        if (!app()->bound('keplog')) {
            return;
        }

        $keplog = app('keplog');
        $keplog->setTag('error_type', 'http_client');

        $context = [
            'http_status' => $e->response?->status(),
            'http_body' => $e->response?->body(),
        ];

        $keplog->captureException($e, $context);
    }

    private function reportDatabaseError(\Illuminate\Database\QueryException $e): void
    {
        if (!app()->bound('keplog')) {
            return;
        }

        $keplog = app('keplog');
        $keplog->setTag('error_type', 'database');

        $context = [
            'sql' => $e->getSql(),
            'bindings' => $e->getBindings(),
        ];

        $keplog->captureException($e, $context);
    }

    private function reportGenericError(Throwable $e): void
    {
        if (!app()->bound('keplog')) {
            return;
        }

        $keplog = app('keplog');
        $keplog->setTag('error_type', 'generic');

        $context = [
            'request' => RequestContext::capture(request()),
            'user' => UserContext::capture(),
        ];

        $keplog->captureException($e, $context);
    }
}
