# Laravel 12 Integration Guide

Complete guide for integrating Keplog SDK with Laravel 12.

## Overview

Laravel 12 introduced a new application structure using `bootstrap/app.php` for configuration instead of the traditional `app/Exceptions/Handler.php`. This guide shows the recommended approach.

## Installation

### Step 1: Install via Composer

```bash
composer require keplog/php
```

### Step 2: Configure Environment Variables

Add to your `.env` file:

```env
KEPLOG_API_KEY=kep_your-api-key-here
KEPLOG_BASE_URL=http://localhost:8080
KEPLOG_ENABLED=true
KEPLOG_DEBUG=false
APP_VERSION=1.0.0
```

### Step 3: Register Service Provider

Add to `app/Providers/AppServiceProvider.php`:

```php
<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Keplog\KeplogClient;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('keplog', function ($app) {
            return new KeplogClient([
                'api_key' => env('KEPLOG_API_KEY'),
                'base_url' => env('KEPLOG_BASE_URL', 'http://localhost:8080'),
                'environment' => env('APP_ENV', 'production'),
                'release' => env('APP_VERSION'),
                'enabled' => env('KEPLOG_ENABLED', true),
                'debug' => env('KEPLOG_DEBUG', false),
            ]);
        });
    }

    public function boot(): void
    {
        //
    }
}
```

### Step 4: Configure Exception Handler

Update `bootstrap/app.php`:

```php
<?php

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

## Optional Features

### Track Failed Queue Jobs

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

### Track Database Queries (Optional)

To capture database queries that were executed before an error occurs:

#### Step 1: Create Query Tracking Service Provider

Create `app/Providers/KeplogServiceProvider.php`:

```php
<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\DB;

class KeplogServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Track queries in app container
        $this->app->singleton('keplog.queries', function () {
            return [];
        });

        DB::listen(function ($query) {
            $queries = app('keplog.queries');
            $queries[] = [
                'sql' => $query->sql,
                'bindings' => $query->bindings,
                'time' => $query->time,
            ];
            app()->instance('keplog.queries', $queries);
        });
    }
}
```

#### Step 2: Register the Service Provider

Add to `bootstrap/providers.php`:

```php
<?php

return [
    App\Providers\AppServiceProvider::class,
    App\Providers\KeplogServiceProvider::class,
];
```

#### Step 3: Pass Queries in Exception Handler

Update `bootstrap/app.php`:

```php
->withExceptions(function (Exceptions $exceptions): void {
    $exceptions->report(function (Throwable $e) {
        if (app()->bound('keplog')) {
            app('keplog')->captureException($e, [
                'request' => RequestContext::capture(request()),
                'user' => UserContext::capture(),
                'queries' => app('keplog.queries'),  // Add this line
            ]);
        }
    });
})
```

### Add Custom Context

You can add custom application-specific data:

```php
->withExceptions(function (Exceptions $exceptions): void {
    $exceptions->report(function (Throwable $e) {
        if (app()->bound('keplog')) {
            app('keplog')->captureException($e, [
                // SDK-managed context
                'request' => RequestContext::capture(request()),
                'user' => UserContext::capture(),
                'queries' => app('keplog.queries') ?? [],

                // Your custom context (goes to extra_context)
                'order_id' => session('order_id'),
                'cart_total' => session('cart_total'),
                'feature_flags' => config('features'),
                'tenant_id' => auth()->user()?->tenant_id,
            ]);
        }
    });
})
```

## Usage Examples

### Manual Exception Capture

```php
use Keplog\Context\RequestContext;
use Keplog\Context\UserContext;

// In your controller
try {
    $order = $this->processOrder($data);
} catch (Exception $e) {
    app('keplog')->captureException($e, [
        'request' => RequestContext::capture(request()),
        'user' => UserContext::capture(),
        'order_data' => $data,
        'step' => 'payment_processing',
    ]);

    throw $e;
}
```

### Capture Custom Messages

```php
// Log informational message
app('keplog')->captureMessage('Payment processed successfully', 'info', [
    'order_id' => $order->id,
    'amount' => $order->total,
]);

// Log warning
app('keplog')->captureMessage('High memory usage detected', 'warning', [
    'memory_usage' => memory_get_usage(true),
    'peak_memory' => memory_get_peak_usage(true),
]);
```

### Add Breadcrumbs

```php
// In your controller or middleware
app('keplog')->addBreadcrumb([
    'category' => 'navigation',
    'message' => 'User accessed checkout page',
    'level' => 'info',
    'data' => [
        'cart_items' => $cart->count(),
    ],
]);

app('keplog')->addBreadcrumb([
    'category' => 'user_action',
    'message' => 'User clicked payment button',
    'level' => 'info',
]);
```

### Set Global Context

```php
// In a middleware or service provider
app('keplog')->setContext('session_id', session()->getId());
app('keplog')->setTag('environment', config('app.env'));
app('keplog')->setTag('server', gethostname());
```

## Advanced Configuration

### Filter Events Before Sending

```php
// In AppServiceProvider::register()
$this->app->singleton('keplog', function ($app) {
    return new KeplogClient([
        'api_key' => env('KEPLOG_API_KEY'),
        'base_url' => env('KEPLOG_BASE_URL'),
        'environment' => env('APP_ENV'),
        'before_send' => function ($event) {
            // Don't send 404 errors
            if (str_contains($event['message'], 'NotFoundHttpException')) {
                return null;
            }

            // Filter sensitive data
            if (isset($event['extra_context']['password'])) {
                $event['extra_context']['password'] = '[FILTERED]';
            }

            return $event;
        },
    ]);
});
```

### Conditional Tracking

```php
// Only enable in production
'enabled' => env('APP_ENV') === 'production',

// Or use a dedicated flag
'enabled' => env('KEPLOG_ENABLED', true),
```

## What Gets Captured Automatically

When an exception occurs, the SDK automatically captures:

### 1. Enhanced Stack Frames
- Code snippets (3 lines before/after error)
- File paths and line numbers
- Function/class names
- Vendor vs. application code classification

### 2. Exception Metadata
- Exception class name
- Error message
- Full stack trace

### 3. Request Context (if provided)
- URL and HTTP method
- Headers and query parameters
- Request body (sanitized)
- User IP address

### 4. User Context (if provided)
- User ID
- Email
- Username
- Custom user data

### 5. Database Queries (if configured)
- SQL queries executed
- Query bindings
- Execution time

## Payload Structure

```json
{
  "message": "Class 'User' not found",
  "level": "error",
  "stack_trace": "Exception: Class 'User' not found...",
  "context": {
    "exception_class": "RuntimeException",
    "frames": [
      {
        "file": "/var/www/app/Http/Controllers/UserController.php",
        "line": 42,
        "function": "show",
        "class": "App\\Http\\Controllers\\UserController",
        "type": "->",
        "code_snippet": {
          "39": "    public function show($id)",
          "40": "    {",
          "41": "        // Find user",
          "42": "        $user = User::find($id);",
          "43": "        return view('user', compact('user'));",
          "44": "    }"
        },
        "is_vendor": false,
        "is_application": true
      }
    ],
    "queries": [
      {
        "sql": "select * from sessions where id = ?",
        "bindings": ["abc123"],
        "time": 3.28
      }
    ],
    "request": {
      "url": "https://example.com/users/123",
      "method": "GET"
    },
    "user": {
      "id": 456,
      "email": "user@example.com"
    }
  },
  "extra_context": {
    "order_id": 12345,
    "tenant_id": 1,
    "feature_flags": {
      "new_checkout": true
    }
  },
  "timestamp": "2025-12-19T06:00:00Z",
  "environment": "production",
  "server_name": "web-01"
}
```

## Troubleshooting

### SDK Not Capturing Exceptions

**Check if service is bound:**

```php
if (app()->bound('keplog')) {
    echo "Keplog is registered";
} else {
    echo "Keplog is NOT registered";
}
```

**Check configuration:**

```bash
php artisan tinker
>>> app('keplog')
>>> env('KEPLOG_API_KEY')
>>> env('KEPLOG_ENABLED')
```

### No Queries in Context

Make sure:
1. `KeplogServiceProvider` is registered in `bootstrap/providers.php`
2. Queries are being passed in `captureException()`
3. Queries are actually being executed before the error

### Context Too Large Error

If you see "Context too large" warnings:

```php
// Limit query count
$queries = collect(app('keplog.queries'))->take(10)->toArray();

app('keplog')->captureException($e, [
    'queries' => $queries,
]);
```

## Best Practices

1. **Always check if bound** before using:
   ```php
   if (app()->bound('keplog')) {
       app('keplog')->captureException($e);
   }
   ```

2. **Don't capture in local development** unless debugging:
   ```php
   'enabled' => env('APP_ENV') !== 'local',
   ```

3. **Filter sensitive data** in `before_send` hook

4. **Use breadcrumbs** for debugging complex flows

5. **Add custom context** for business-specific debugging

6. **Don't send too many queries** - limit to last 10-20

## Further Reading

- [Enhanced Stack Frames](../ENHANCED_STACK_FRAMES.md)
- [Reserved Context Keys](RESERVED_CONTEXT_KEYS.md)
- [Main README](../README.md)
