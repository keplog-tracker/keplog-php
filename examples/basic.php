<?php

/**
 * Basic usage example of Keplog PHP SDK
 *
 * This example shows how to use Keplog SDK in standalone PHP (without Laravel)
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Keplog\KeplogClient;

// Initialize the Keplog client
$keplog = new KeplogClient([
    'ingest_key' => 'kep_ingest_your-ingest-key',  // Replace with your actual API key
    'base_url' => 'http://localhost:8080',  // Your Keplog API endpoint
    'environment' => 'production',
    'release' => 'v1.0.0',
    'server_name' => 'web-server-01',
    'debug' => true,  // Enable debug mode for development
]);

// Example 1: Capture an exception
echo "Example 1: Capturing an exception\n";
try {
    // Simulate an error
    throw new RuntimeException('Something went wrong in the application');
} catch (Exception $e) {
    $eventId = $keplog->captureException($e);
    echo "Exception captured with event ID: " . ($eventId ?? 'null') . "\n\n";
}

// Example 2: Capture exception with additional context
echo "Example 2: Capturing exception with context\n";
try {
    $userId = 123;
    $action = 'process_payment';

    // Simulate a payment processing error
    throw new Exception('Payment processing failed');
} catch (Exception $e) {
    $eventId = $keplog->captureException($e, [
        'user_id' => $userId,
        'action' => $action,
        'payment_amount' => 99.99,
        'currency' => 'USD',
    ]);
    echo "Exception with context captured: " . ($eventId ?? 'null') . "\n\n";
}

// Example 3: Set global context
echo "Example 3: Setting global context\n";
$keplog->setContext('app_version', '2.1.0');
$keplog->setContext('environment_type', 'cloud');

// Example 4: Set tags
echo "Example 4: Setting tags\n";
$keplog->setTag('server', 'web-01');
$keplog->setTag('datacenter', 'us-east-1');

// Or set multiple tags at once
$keplog->setTags([
    'os' => 'linux',
    'php_version' => PHP_VERSION,
]);

// Example 5: Set user information
echo "Example 5: Setting user information\n";
$keplog->setUser([
    'id' => '123',
    'email' => 'user@example.com',
    'name' => 'John Doe',
]);

// Example 6: Add breadcrumbs
echo "Example 6: Adding breadcrumbs\n";
$keplog->addBreadcrumb([
    'message' => 'User logged in',
    'category' => 'auth',
    'level' => 'info',
]);

$keplog->addBreadcrumb([
    'message' => 'Navigated to dashboard',
    'category' => 'navigation',
    'level' => 'info',
]);

$keplog->addBreadcrumb([
    'message' => 'Clicked checkout button',
    'category' => 'ui',
    'level' => 'info',
]);

// Now when we capture an exception, all the context, tags, user info, and breadcrumbs will be included
try {
    throw new Exception('Error during checkout process');
} catch (Exception $e) {
    $eventId = $keplog->captureException($e);
    echo "Exception with full context captured: " . ($eventId ?? 'null') . "\n\n";
}

// Example 7: Capture messages (without exceptions)
echo "Example 7: Capturing messages\n";
$keplog->captureMessage('User completed checkout', 'info');
$keplog->captureMessage('High memory usage detected', 'warning');
$keplog->captureMessage('Database connection slow', 'warning', [
    'query_time' => 5.2,
    'table' => 'users',
]);

// Example 8: Using beforeSend hook
echo "\nExample 8: Using beforeSend hook\n";
$keplogWithHook = new KeplogClient([
    'ingest_key' => 'kep_ingest_your-ingest-key',
    'base_url' => 'http://localhost:8080',
    'before_send' => function ($event) {
        // Filter out errors in development
        if ($event['environment'] === 'development') {
            echo "Event dropped by beforeSend hook\n";
            return null;  // Drop the event
        }

        // Add additional context
        $event['context']['hostname'] = gethostname();

        // You can modify the event before it's sent
        return $event;
    },
]);

try {
    throw new Exception('This error will be modified by beforeSend');
} catch (Exception $e) {
    $eventId = $keplogWithHook->captureException($e);
    echo "Event ID: " . ($eventId ?? 'null') . "\n";
}

// Example 9: Disable/Enable tracking
echo "\nExample 9: Disable/Enable tracking\n";
$keplog->setEnabled(false);
echo "Tracking disabled: " . ($keplog->isEnabled() ? 'Yes' : 'No') . "\n";

try {
    throw new Exception('This error will NOT be captured');
} catch (Exception $e) {
    $eventId = $keplog->captureException($e);
    echo "Event ID (should be null): " . ($eventId ?? 'null') . "\n";
}

$keplog->setEnabled(true);
echo "Tracking enabled: " . ($keplog->isEnabled() ? 'Yes' : 'No') . "\n\n";

// Example 10: Clear scope
echo "Example 10: Clearing scope\n";
$keplog->clearScope();
echo "Scope cleared - all context, tags, user info, and breadcrumbs removed\n";

echo "\nAll examples completed!\n";
