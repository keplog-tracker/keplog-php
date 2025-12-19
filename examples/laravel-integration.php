<?php

/**
 * Laravel Integration Example
 *
 * This example shows how to integrate Keplog SDK with Laravel
 *
 * Installation:
 * 1. composer require keplog/php
 * 2. Add the service provider binding
 * 3. Configure the exception handler
 * 4. Optional: Set up queue job tracking
 */

// =============================================================================
// STEP 1: Service Provider Registration
// =============================================================================

// File: app/Providers/AppServiceProvider.php (or create a KeplogServiceProvider)

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Keplog\KeplogClient;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Register Keplog as a singleton
        $this->app->singleton('keplog', function ($app) {
            return new KeplogClient([
                'api_key' => env('KEPLOG_API_KEY'),
                'base_url' => env('KEPLOG_BASE_URL', 'http://localhost:8080'),
                'environment' => env('APP_ENV', 'production'),
                'release' => env('APP_VERSION'),
                'server_name' => gethostname(),
                'enabled' => env('KEPLOG_ENABLED', true),
                'debug' => env('KEPLOG_DEBUG', false),
            ]);
        });

        // Optional: Register as an alias
        $this->app->alias('keplog', KeplogClient::class);
    }

    public function boot(): void
    {
        // Optional: Set global context that applies to all errors
        if ($this->app->bound('keplog')) {
            $keplog = $this->app->make('keplog');

            // Set global tags
            $keplog->setTags([
                'php_version' => PHP_VERSION,
                'laravel_version' => $this->app->version(),
            ]);
        }
    }
}

// =============================================================================
// STEP 2: Environment Configuration
// =============================================================================

// File: .env

/*
KEPLOG_API_KEY=kep_your-api-key-here
KEPLOG_BASE_URL=http://localhost:8080
KEPLOG_ENABLED=true
KEPLOG_DEBUG=false
APP_VERSION=1.0.0
*/

// =============================================================================
// STEP 3: Exception Handler Integration
// =============================================================================

// File: app/Exceptions/Handler.php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Keplog\Context\RequestContext;
use Keplog\Context\UserContext;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of exception types with their corresponding custom log levels.
     *
     * @var array<class-string<\Throwable>, \Psr\Log\LogLevel::*>
     */
    protected $levels = [
        //
    ];

    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<\Throwable>>
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            // Only report to Keplog if it's configured
            if (!app()->bound('keplog')) {
                return;
            }

            $keplog = app('keplog');

            // Build context with Laravel-specific data
            $context = [];

            // Add request context if available
            if (request() !== null) {
                $context['request'] = RequestContext::capture(request());
            }

            // Add authenticated user context
            $userContext = UserContext::capture();
            if ($userContext !== null) {
                $context['user'] = $userContext;
            }

            // Add session ID if available
            if (session()->has('_token')) {
                $context['session_id'] = session()->getId();
            }

            // Capture the exception
            $keplog->captureException($e, $context);
        });
    }
}

// =============================================================================
// STEP 4: Queue Job Tracking
// =============================================================================

// File: app/Providers/AppServiceProvider.php (add to boot method)

use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Queue;
use Keplog\Context\JobContext;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Track failed queue jobs
        Queue::failing(function (JobFailed $event) {
            if (!app()->bound('keplog')) {
                return;
            }

            $keplog = app('keplog');

            // Capture job context
            $context = JobContext::captureFromFailedEvent($event);

            // Report the failure
            $keplog->captureException($event->exception, $context);
        });
    }
}

// =============================================================================
// STEP 5: Usage in Controllers
// =============================================================================

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Keplog\KeplogClient;

class UserController extends Controller
{
    public function store(Request $request)
    {
        // Get Keplog instance
        $keplog = app('keplog');

        // Add breadcrumb for user action
        $keplog->addBreadcrumb([
            'message' => 'User creation started',
            'category' => 'user',
            'level' => 'info',
            'data' => [
                'email' => $request->input('email'),
            ],
        ]);

        try {
            // Create user logic
            $user = User::create($request->validated());

            // Log successful creation
            $keplog->captureMessage('User created successfully', 'info', [
                'user_id' => $user->id,
                'email' => $user->email,
            ]);

            return response()->json($user, 201);
        } catch (\Exception $e) {
            // Exception will be automatically reported via Handler
            // But you can add additional context here
            $keplog->captureException($e, [
                'action' => 'user_creation',
                'input_data' => $request->all(),
            ]);

            throw $e;
        }
    }

    public function show($id)
    {
        $keplog = app('keplog');

        // Set user context for this request
        $keplog->setContext('viewing_user_id', $id);

        $user = User::findOrFail($id);

        return response()->json($user);
    }
}

// =============================================================================
// STEP 6: Middleware (Optional)
// =============================================================================

// File: app/Http/Middleware/KeplogMiddleware.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Keplog\Context\RequestContext;
use Keplog\Context\UserContext;

class KeplogMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if (app()->bound('keplog')) {
            $keplog = app('keplog');

            // Add breadcrumb for each request
            $keplog->addBreadcrumb([
                'message' => 'HTTP Request',
                'category' => 'request',
                'level' => 'info',
                'data' => [
                    'method' => $request->method(),
                    'url' => $request->fullUrl(),
                ],
            ]);

            // Set user context if authenticated
            if (auth()->check()) {
                $keplog->setUser([
                    'id' => (string) auth()->id(),
                    'email' => auth()->user()->email,
                    'name' => auth()->user()->name ?? null,
                ]);
            }
        }

        return $next($request);
    }
}

// Register in app/Http/Kernel.php:
// protected $middleware = [
//     \App\Http\Middleware\KeplogMiddleware::class,
// ];

// =============================================================================
// STEP 7: Artisan Commands
// =============================================================================

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ProcessDataCommand extends Command
{
    protected $signature = 'data:process';
    protected $description = 'Process data with Keplog tracking';

    public function handle()
    {
        $keplog = app('keplog');

        // Set context for command execution
        $keplog->setContext('command', $this->signature);
        $keplog->setTag('execution_context', 'cli');

        try {
            $this->info('Processing data...');

            // Your command logic here
            // Any exceptions will be automatically reported

            $keplog->captureMessage('Data processing completed', 'info', [
                'records_processed' => 1000,
            ]);

            $this->info('Done!');
        } catch (\Exception $e) {
            $keplog->captureException($e, [
                'command' => $this->signature,
                'arguments' => $this->arguments(),
                'options' => $this->options(),
            ]);

            $this->error('Processing failed!');
            throw $e;
        }
    }
}

// =============================================================================
// STEP 8: Testing
// =============================================================================

// In your tests, you might want to disable Keplog or mock it

namespace Tests\Feature;

use Tests\TestCase;
use Keplog\KeplogClient;
use Mockery;

class UserTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Mock Keplog in tests
        $this->app->singleton('keplog', function () {
            return Mockery::mock(KeplogClient::class);
        });
    }

    public function test_user_creation()
    {
        // Your test logic
    }
}

// Or disable Keplog in tests via .env.testing:
// KEPLOG_ENABLED=false
