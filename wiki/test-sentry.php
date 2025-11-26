<?php
/**
 * Sentry Integration Test Script
 * 
 * This script tests various Sentry features to validate the integration.
 * Run this from the command line or via browser to verify everything works.
 * 
 * Usage:
 *   CLI: php test-sentry.php
 *   Browser: http://your-wiki/wiki/test-sentry.php
 */

// Load Sentry SDK
require_once __DIR__ . '/vendor/autoload.php';

// Load environment variables (same way MediaWiki does)
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->safeLoad();
}

// Initialize Sentry manually (simulating what 00-Core.php does)
if (!empty($_ENV['SENTRY_DSN'])) {
    try {
        $isProduction = ($_ENV['ENVIRONMENT'] ?? 'development') === 'production';

        \Sentry\init([
            'dsn' => $_ENV['SENTRY_DSN'],
            'environment' => $_ENV['ENVIRONMENT'] ?? 'development',
            'release' => $_ENV['SENTRY_RELEASE'] ?? null,
            'server_name' => $_ENV['SERVER_NAME'] ?? gethostname(),
            'traces_sample_rate' => $isProduction ? 0.1 : 1.0,
            'profiles_sample_rate' => $isProduction ? 0.1 : 1.0,
            'sample_rate' => 1.0,
            'error_types' => E_ALL,
            'attach_stacktrace' => true,
            'context_lines' => 7,
            'max_breadcrumbs' => 50,
        ]);
    } catch (\Throwable $e) {
        echo "ERROR: Sentry initialization failed: " . $e->getMessage() . "\n";
        exit(1);
    }
} else {
    echo "ERROR: SENTRY_DSN environment variable not set!\n";
    echo "Please set SENTRY_DSN in your .env file.\n";
    exit(1);
}

echo "=== Sentry Integration Test Suite ===\n\n";

// Test 1: Check if Sentry SDK is loaded
echo "Test 1: Sentry SDK Availability\n";
if (class_exists('\Sentry\SentrySdk')) {
    echo "  ✓ Sentry SDK is loaded\n";
} else {
    echo "  ✗ Sentry SDK is NOT loaded\n";
    exit(1);
}

// Test 2: Check if Sentry is initialized
echo "\nTest 2: Sentry Initialization\n";
try {
    $client = \Sentry\SentrySdk::getCurrentHub()->getClient();
    if ($client !== null) {
        $options = $client->getOptions();
        echo "  ✓ Sentry is initialized\n";
        echo "    - DSN: " . (strpos($options->getDsn(), '@') !== false ? substr($options->getDsn(), 0, 20) . '...' : 'Not set') . "\n";
        echo "    - Environment: " . ($options->getEnvironment() ?? 'not set') . "\n";
        echo "    - Release: " . ($options->getRelease() ?? 'not set') . "\n";
        echo "    - Traces Sample Rate: " . ($options->getTracesSampleRate() ?? 'not set') . "\n";
        echo "    - Profiles Sample Rate: " . ($options->getProfilesSampleRate() ?? 'not set') . "\n";
    } else {
        echo "  ✗ Sentry client is null (not initialized)\n";
        exit(1);
    }
} catch (\Throwable $e) {
    echo "  ✗ Error checking Sentry: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 2.5: Check Excimer extension for profiling
echo "\nTest 2.5: Profiling Support (Excimer)\n";
if (extension_loaded('excimer')) {
    echo "  ✓ Excimer extension is loaded\n";
    echo "    - Profiling is available\n";
} else {
    echo "  ⚠ Excimer extension is NOT loaded\n";
    echo "    - Profiling will not work\n";
    echo "    - Install with: pecl install excimer\n";
}

// Test 3: Send a test message
echo "\nTest 3: Send Test Message\n";
try {
    \Sentry\captureMessage('Sentry Integration Test - ' . date('Y-m-d H:i:s'), \Sentry\Severity::info());
    echo "  ✓ Test message sent\n";
    echo "    - Check your Sentry dashboard for this message\n";
} catch (\Throwable $e) {
    echo "  ✗ Failed to send test message: " . $e->getMessage() . "\n";
}

// Test 4: Send a test exception
echo "\nTest 4: Send Test Exception\n";
try {
    throw new \Exception('Sentry Integration Test Exception - ' . date('Y-m-d H:i:s'));
} catch (\Exception $e) {
    \Sentry\captureException($e);
    echo "  ✓ Test exception sent\n";
    echo "    - Check your Sentry dashboard for this exception\n";
}

// Test 5: Test user context
echo "\nTest 5: User Context\n";
try {
    \Sentry\configureScope(function (\Sentry\State\Scope $scope): void {
        $scope->setUser([
            'id' => 'test-user-123',
            'username' => 'test-user',
            'email' => 'test@example.com',
        ]);
    });
    \Sentry\captureMessage('Sentry Test - User Context', \Sentry\Severity::info());
    echo "  ✓ User context set and test message sent\n";
    echo "    - Check Sentry dashboard to verify user context is attached\n";
} catch (\Throwable $e) {
    echo "  ✗ Failed to set user context: " . $e->getMessage() . "\n";
}

// Test 6: Test tags
echo "\nTest 6: Tags\n";
try {
    \Sentry\configureScope(function (\Sentry\State\Scope $scope): void {
        $scope->setTag('test.tag', 'test-value');
        $scope->setTag('mediawiki.version', 'test');
    });
    \Sentry\captureMessage('Sentry Test - Tags', \Sentry\Severity::info());
    echo "  ✓ Tags set and test message sent\n";
    echo "    - Check Sentry dashboard to verify tags are attached\n";
} catch (\Throwable $e) {
    echo "  ✗ Failed to set tags: " . $e->getMessage() . "\n";
}

// Test 7: Test custom context
echo "\nTest 7: Custom Context\n";
try {
    \Sentry\configureScope(function (\Sentry\State\Scope $scope): void {
        $scope->setContext('test', [
            'database' => 'test-db',
            'server' => 'test-server',
            'cache_type' => 'test-cache',
        ]);
    });
    \Sentry\captureMessage('Sentry Test - Custom Context', \Sentry\Severity::info());
    echo "  ✓ Custom context set and test message sent\n";
    echo "    - Check Sentry dashboard to verify context is attached\n";
} catch (\Throwable $e) {
    echo "  ✗ Failed to set custom context: " . $e->getMessage() . "\n";
}

// Test 8: Test structured logging
echo "\nTest 8: Structured Logging\n";
try {
    if (method_exists(\Sentry\logger(), 'info')) {
        \Sentry\logger()->info('Sentry Test - Structured Log Message');
        \Sentry\logger()->flush();
        echo "  ✓ Structured log sent\n";
        echo "    - Check your Sentry Logs section for this message\n";
    } else {
        echo "  ⚠ Structured logging not available (requires Sentry SDK 4.0+)\n";
    }
} catch (\Throwable $e) {
    echo "  ✗ Failed to send structured log: " . $e->getMessage() . "\n";
}

// Test 9: Test breadcrumbs
echo "\nTest 9: Breadcrumbs\n";
try {
    \Sentry\addBreadcrumb(
        category: 'test',
        message: 'Sentry Test Breadcrumb',
        level: \Sentry\Breadcrumb::LEVEL_INFO
    );
    \Sentry\captureMessage('Sentry Test - With Breadcrumb', \Sentry\Severity::info());
    echo "  ✓ Breadcrumb added and test message sent\n";
    echo "    - Check Sentry dashboard to verify breadcrumb is attached\n";
} catch (\Throwable $e) {
    echo "  ✗ Failed to add breadcrumb: " . $e->getMessage() . "\n";
}

// Test 10: Flush logs
echo "\nTest 10: Log Flush\n";
try {
    \Sentry\logger()->flush();
    echo "  ✓ Logs flushed\n";
} catch (\Throwable $e) {
    echo "  ✗ Failed to flush logs: " . $e->getMessage() . "\n";
}

// Test 11: Test Profiling (create a transaction with profiling)
echo "\nTest 11: Profiling Test\n";
try {
    if (extension_loaded('excimer')) {
        // Start a transaction to test profiling
        $transaction = \Sentry\startTransaction([
            'name' => 'Sentry Profiling Test',
            'op' => 'test',
        ]);
        \Sentry\SentrySdk::getCurrentHub()->setSpan($transaction);

        // Do some work to profile
        usleep(10000); // 10ms of work

        $transaction->finish();
        echo "  ✓ Test transaction created (profiling should be attached if sampled)\n";
        echo "    - Check Sentry Performance tab for this transaction\n";
        echo "    - If sampled, profile data will be available\n";
    } else {
        echo "  ⚠ Profiling test skipped (Excimer not loaded)\n";
    }
} catch (\Throwable $e) {
    echo "  ✗ Failed to create test transaction: " . $e->getMessage() . "\n";
}

// Test 12: Check debug logger (development only)
echo "\nTest 12: Debug Logger (Development Only)\n";
$options = \Sentry\SentrySdk::getCurrentHub()->getClient()->getOptions();
$logger = $options->getLogger();
if ($logger !== null) {
    echo "  ✓ Debug logger is configured\n";
    echo "    - Check /var/log/mediawiki/sentry-debug.log for debug output\n";
} else {
    echo "  ℹ Debug logger is not configured (normal in production)\n";
}

// Summary
echo "\n=== Test Summary ===\n";
echo "✓ All tests completed!\n";
echo "\nNext Steps:\n";
echo "1. Go to your Sentry dashboard: https://sentry.io/\n";
echo "2. Check for the test messages and exceptions\n";
echo "3. Verify user context, tags, and custom context are attached\n";
echo "4. Check the Logs section for structured log messages\n";
echo "5. Review breadcrumbs on the test events\n";
echo "\nIf you see errors above, check:\n";
echo "- SENTRY_DSN is set in your .env file\n";
echo "- Sentry SDK is installed: composer install\n";
echo "- Debug log: /var/log/mediawiki/sentry-debug.log\n";
echo "\n";

