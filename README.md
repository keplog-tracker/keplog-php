# Keplog PHP SDK

[![Tests](https://img.shields.io/badge/tests-94%20passed-success)](https://github.com/keplog/php-sdk)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.1-blue)](https://www.php.net/)
[![License](https://img.shields.io/badge/license-MIT-green)](LICENSE)

Official PHP SDK for [Keplog](https://keplog.io) - Real-time error tracking and monitoring for PHP applications.

## Features

- ✅ **Automatic Error Capture** - Capture exceptions automatically
- ✅ **Enhanced Stack Frames** - Code snippets with vendor/app classification ([Read more](ENHANCED_STACK_FRAMES.md))
- ✅ **Context Separation** - Automatic separation of system vs user-defined context ([Read more](docs/RESERVED_CONTEXT_KEYS.md))
- ✅ **Laravel 11 & 12 Support** - First-class support with bootstrap/app.php ([Read more](docs/LARAVEL_12_INTEGRATION.md))
- ✅ **Request Context** - Automatically capture HTTP request details
- ✅ **User Tracking** - Track authenticated users with errors
- ✅ **Breadcrumbs** - Track user actions leading up to errors
- ✅ **Queue Job Tracking** - Monitor failed queue jobs
- ✅ **Custom Tags & Context** - Add metadata to errors
- ✅ **beforeSend Hooks** - Filter or modify events before sending
- ✅ **Zero Dependencies** - Only requires Guzzle HTTP client
- ✅ **Silent Failures** - SDK errors never crash your application
- ✅ **Full Test Coverage** - 94 comprehensive tests

## Requirements

- PHP 8.1 or higher
- Composer
- Guzzle HTTP Client 7.x

## Installation

Install via Composer:

```bash
composer require keplog/php
```

## Quick Start

### Standalone PHP

```php
<?php

require 'vendor/autoload.php';

use Keplog\KeplogClient;

$keplog = new KeplogClient([
    'api_key' => 'kep_your-api-key',
    'base_url' => 'http://localhost:8080',
    'environment' => 'production',
]);

try {
    riskyOperation();
} catch (Exception $e) {
    $keplog->captureException($e);
}
```

### Laravel Integration

> **📖 For complete Laravel 12 integration guide, see [Laravel 12 Integration Guide](docs/LARAVEL_12_INTEGRATION.md)**

#### Laravel 12+ (Recommended)

Laravel 12 uses `bootstrap/app.php` for configuration. This is the recommended approach:

##### 1. Register Service Provider

Add to `app/Providers/AppServiceProvider.php`:

```php
public function register(): void
{
    $this->app->singleton('keplog', function ($app) {
        return new \Keplog\KeplogClient([
            'api_key' => env('KEPLOG_API_KEY'),
            'base_url' => env('KEPLOG_BASE_URL', 'http://localhost:8080'),
            'environment' => env('APP_ENV', 'production'),
            'release' => env('APP_VERSION'),
        ]);
    });
}
```

##### 2. Configure Environment

Add to `.env`:

```env
KEPLOG_API_KEY=kep_your-api-key
KEPLOG_BASE_URL=http://localhost:8080
KEPLOG_ENABLED=true
```

##### 3. Set Up Exception Handler

Update `bootstrap/app.php`:

```php
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Keplog\Context\RequestContext;
use Keplog\Context\UserContext;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Keplog integration - automatically captures all exceptions
        $exceptions->report(function (Throwable $e) {
            if (app()->bound('keplog')) {
                app('keplog')->captureException($e, [
                    'request' => RequestContext::capture(request()),
                    'user' => UserContext::capture(),
                ]);
            }
        });
    })->create();
```

#### Laravel 11 and Below (Legacy)

For older Laravel versions, use `app/Exceptions/Handler.php`:

```php
use Keplog\Context\RequestContext;
use Keplog\Context\UserContext;

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
```

#### Optional: Track Failed Queue Jobs

Add to `app/Providers/AppServiceProvider.php`:

```php
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Queue;
use Keplog\Context\JobContext;

public function boot(): void
{
    Queue::failing(function (JobFailed $event) {
        if (app()->bound('keplog')) {
            app('keplog')->captureException(
                $event->exception,
                JobContext::captureFromFailedEvent($event)
            );
        }
    });
}
```

## Configuration

### Constructor Options

```php
$keplog = new KeplogClient([
    // Required
    'api_key' => 'kep_your-api-key',  // Your Keplog API key

    // Optional
    'base_url' => 'http://localhost:8080',  // Keplog API endpoint
    'environment' => 'production',           // Environment name (auto-detected from APP_ENV)
    'release' => 'v1.0.0',                  // Application version
    'server_name' => 'web-01',              // Server name (auto-detected from hostname)
    'max_breadcrumbs' => 100,               // Maximum breadcrumbs to keep
    'enabled' => true,                      // Enable/disable tracking
    'debug' => false,                       // Enable debug logging
    'timeout' => 5,                         // HTTP timeout in seconds
    'before_send' => function($event) {     // Hook to filter/modify events
        return $event;
    },
]);
```

### Environment Variables (Laravel)

```env
KEPLOG_API_KEY=kep_your-api-key-here
KEPLOG_BASE_URL=http://localhost:8080
KEPLOG_ENABLED=true
KEPLOG_DEBUG=false
APP_VERSION=1.0.0
```

## Usage

### Capturing Exceptions

```php
try {
    throw new RuntimeException('Something went wrong');
} catch (Exception $e) {
    $keplog->captureException($e);
}
```

### Adding Context

```php
$keplog->captureException($e, [
    'user_id' => 123,
    'action' => 'checkout',
    'cart_total' => 99.99,
]);
```

### Capturing Messages (Without Exceptions)

```php
// Info level
$keplog->captureMessage('User completed checkout', 'info');

// Warning level
$keplog->captureMessage('High memory usage', 'warning', [
    'memory_mb' => 512,
]);

// Error level
$keplog->captureMessage('API rate limit exceeded', 'error');
```

**Available Levels:** `critical`, `error`, `warning`, `info`, `debug`

### Setting Global Context

```php
// Set individual context values
$keplog->setContext('version', '2.1.0');
$keplog->setContext('datacenter', 'us-east-1');

// All subsequent errors will include this context
```

### Setting Tags

```php
// Single tag
$keplog->setTag('server', 'web-01');

// Multiple tags
$keplog->setTags([
    'environment' => 'production',
    'version' => '1.0.0',
    'region' => 'us-east-1',
]);
```

### Setting User Information

```php
$keplog->setUser([
    'id' => '123',
    'email' => 'user@example.com',
    'name' => 'John Doe',
]);
```

### Adding Breadcrumbs

Breadcrumbs are a trail of events that led up to an error:

```php
$keplog->addBreadcrumb([
    'message' => 'User logged in',
    'category' => 'auth',
    'level' => 'info',
]);

$keplog->addBreadcrumb([
    'message' => 'Clicked checkout button',
    'category' => 'ui',
    'level' => 'info',
    'data' => [
        'cart_items' => 3,
    ],
]);

// When an error occurs, all breadcrumbs are sent with it
```

### Clearing Scope

```php
// Clear all context, tags, user info, and breadcrumbs
$keplog->clearScope();
```

### Enabling/Disabling Tracking

```php
// Disable tracking
$keplog->setEnabled(false);

// Check status
if ($keplog->isEnabled()) {
    // Tracking is enabled
}

// Re-enable
$keplog->setEnabled(true);
```

### Using beforeSend Hook

Filter or modify events before they're sent:

```php
$keplog = new KeplogClient([
    'api_key' => 'kep_key',
    'before_send' => function ($event) {
        // Drop events in development
        if ($event['environment'] === 'development') {
            return null;  // Return null to drop the event
        }

        // Modify event
        $event['context']['hostname'] = gethostname();

        // Remove sensitive data
        if (isset($event['context']['password'])) {
            $event['context']['password'] = '[REDACTED]';
        }

        return $event;
    },
]);
```

## Laravel-Specific Features

### Request Context

Automatically capture HTTP request details:

```php
use Keplog\Context\RequestContext;

$context = RequestContext::capture(request());
// Returns:
// [
//     'url' => 'https://example.com/checkout',
//     'method' => 'POST',
//     'ip' => '192.168.1.1',
//     'user_agent' => 'Mozilla/5.0...',
//     'headers' => [...],  // Sanitized
//     'query' => [...],
//     'body' => [...],     // Sanitized
//     'route_name' => 'checkout.process',
// ]
```

**Note:** Sensitive headers and request data are automatically sanitized.

### User Context

Capture authenticated user information:

```php
use Keplog\Context\UserContext;

$userContext = UserContext::capture();
// Returns:
// [
//     'id' => '123',
//     'email' => 'user@example.com',
//     'name' => 'John Doe',
// ]
```

### Job Context

Capture queue job information:

```php
use Keplog\Context\JobContext;

// From failed job event
$context = JobContext::captureFromFailedEvent($event);

// From job instance
$context = JobContext::capture($job);
// Returns:
// [
//     'job_class' => 'App\Jobs\ProcessPayment',
//     'queue' => 'default',
//     'connection' => 'redis',
//     'attempts' => 3,
//     'max_tries' => 5,
// ]
```

### Controller Usage

```php
namespace App\Http\Controllers;

use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function store(Request $request)
    {
        $keplog = app('keplog');

        // Add breadcrumb
        $keplog->addBreadcrumb([
            'message' => 'Order creation started',
            'category' => 'order',
        ]);

        try {
            $order = Order::create($request->validated());

            $keplog->captureMessage('Order created', 'info', [
                'order_id' => $order->id,
            ]);

            return response()->json($order, 201);
        } catch (\Exception $e) {
            $keplog->captureException($e, [
                'action' => 'order_creation',
            ]);

            throw $e;
        }
    }
}
```

## API Reference

### `KeplogClient`

#### `captureException(Throwable $exception, array $context = []): ?string`

Capture an exception and send it to Keplog.

**Parameters:**
- `$exception` - The exception to capture
- `$context` - Additional context data (optional)

**Returns:** Event ID if successful, `null` if failed

---

#### `captureMessage(string $message, string $level = 'info', array $context = []): ?string`

Capture a message without an exception.

**Parameters:**
- `$message` - The message to capture
- `$level` - Severity level (critical|error|warning|info|debug)
- `$context` - Additional context data (optional)

**Returns:** Event ID if successful, `null` if failed

---

#### `addBreadcrumb(array $breadcrumb): void`

Add a breadcrumb to the trail.

**Parameters:**
- `$breadcrumb` - Breadcrumb data with keys: `message`, `category`, `level`, `data`

---

#### `setContext(string $key, mixed $value): void`

Set a global context value.

---

#### `setTag(string $key, string $value): void`

Set a single tag.

---

#### `setTags(array $tags): void`

Set multiple tags at once.

---

#### `setUser(array $user): void`

Set user information.

**Parameters:**
- `$user` - User data with keys: `id`, `email`, `name`

---

#### `clearScope(): void`

Clear all scope data (context, tags, user, breadcrumbs).

---

#### `setEnabled(bool $enabled): void`

Enable or disable error tracking.

---

#### `isEnabled(): bool`

Check if error tracking is enabled.

## Testing

Run the test suite:

```bash
composer test
```

Run tests with coverage:

```bash
composer test:coverage
```

**Test Results:**
- 81 tests
- 144 assertions
- 100% success rate

## Field Size Limits

The Keplog API enforces the following limits:

| Field | Maximum Size |
|-------|-------------|
| `message` | 10 KB |
| `stack_trace` | 500 KB |
| `context` (JSON) | 256 KB |

Fields exceeding these limits will be automatically truncated with a warning logged in debug mode.

## Error Handling

The SDK is designed to fail silently - SDK errors will never crash your application. All errors are caught and optionally logged when `debug` mode is enabled:

```php
$keplog = new KeplogClient([
    'api_key' => 'kep_key',
    'debug' => true,  // Enable error logging
]);
```

## Security

### Automatic Data Sanitization

The SDK automatically sanitizes sensitive data:

**Headers:**
- `authorization`
- `cookie`
- `set-cookie`
- `x-api-key`

**Request Body:**
- `password`
- `password_confirmation`
- `token`
- `api_key`
- `secret`
- `credit_card`
- `cvv`
- `ssn`

Sensitive fields are replaced with `[REDACTED]`.

### Manual Sanitization

Use the `beforeSend` hook for custom sanitization:

```php
'before_send' => function ($event) {
    if (isset($event['context']['custom_secret'])) {
        $event['context']['custom_secret'] = '[REDACTED]';
    }
    return $event;
}
```

## Performance

- **Async by default** - Errors are sent via HTTP in the background
- **Lightweight** - Minimal overhead on application performance
- **Configurable timeout** - Default 5 seconds, customizable
- **FIFO breadcrumbs** - Automatically maintains max limit (default: 100)

## Examples

See the `examples/` directory for complete examples:

- [`basic.php`](examples/basic.php) - Standalone PHP usage
- [`laravel-integration.php`](examples/laravel-integration.php) - Complete Laravel setup
- [`exception-handler.php`](examples/exception-handler.php) - Advanced exception handling

## Troubleshooting

### Errors not appearing in Keplog

1. Check API key is correct
2. Verify `KEPLOG_ENABLED=true` in `.env`
3. Enable debug mode to see SDK errors:
   ```php
   'debug' => true
   ```
4. Check network connectivity to Keplog API
5. Verify exception handler is registered

### Testing in Development

Disable Keplog in tests:

```env
# .env.testing
KEPLOG_ENABLED=false
```

Or mock it in tests:

```php
$this->app->singleton('keplog', function () {
    return Mockery::mock(KeplogClient::class);
});
```

## Documentation

### Guides

- **[Laravel 12 Integration](docs/LARAVEL_12_INTEGRATION.md)** - Complete Laravel 12 setup guide
- **[Enhanced Stack Frames](ENHANCED_STACK_FRAMES.md)** - Code snippets and frame classification
- **[Reserved Context Keys](docs/RESERVED_CONTEXT_KEYS.md)** - Understanding context separation

### Key Features

- **Automatic Exception Capture** - Catch all exceptions automatically
- **Enhanced Stack Traces** - Code snippets with vendor/app classification
- **Context Separation** - System context vs. user-defined extra context
- **Laravel Integration** - First-class support for Laravel 11 & 12
- **Database Query Tracking** - Capture queries before errors (optional)
- **Request & User Context** - Automatic HTTP and auth data capture
- **Queue Job Tracking** - Monitor failed background jobs
- **Breadcrumbs** - Track user actions leading to errors
- **Customizable** - Tags, filters, and hooks

## License

MIT License - see [LICENSE](LICENSE) file for details.

## Support

- **Documentation:** [https://docs.keplog.io](https://docs.keplog.io)
- **Issues:** [GitHub Issues](https://github.com/keplog/php-sdk/issues)
- **Email:** support@keplog.io

## Contributing

Contributions are welcome! Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details.

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for release history.

---

Made with ❤️ by the Keplog team
