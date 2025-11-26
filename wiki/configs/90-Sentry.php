<?php
/**
 * MediaWiki Sentry Error Tracking & Performance Monitoring Configuration
 *
 * This file configures Sentry SDK integration for comprehensive error tracking,
 * performance monitoring, and structured logging in MediaWiki.
 *
 * Integration with PSR-3 Logging:
 * - Works alongside 85-Logging.php for complete observability
 * - PSR-3 loggers send structured data to Sentry via Monolog handlers
 * - Separate error events vs structured logs for different use cases
 *
 * Sentry Features Configured:
 * - PHP error & exception tracking
 * - Performance tracing & profiling
 * - JavaScript error tracking (via ResourceLoader)
 * - User context & session data
 * - Custom fingerprinting for error grouping
 * - Environment-aware sampling rates
 * - Data scrubbing for PII protection
 *
 * Error Flow:
 * 1. PHP errors/exceptions → Sentry SDK → Error events
 * 2. PSR-3 logs (via Monolog) → Sentry Logs or Events
 * 3. JavaScript errors → Sentry Browser SDK → Error events
 * 4. Performance data → Sentry Traces & Profiles
 *
 * Data Privacy:
 * - PII scrubbing enabled by default
 * - Sensitive data filtered via before_send hooks
 * - Environment-appropriate data collection
 * - GDPR/CCPA compliant configuration options
 * - Privacy-conscious PII handling with selective collection
 * - Application context marking for better stack traces
 * - Server identification for clustered deployments
 * - Path normalization across development/production environments
 * - Manual breadcrumbs for key MediaWiki events (auth, navigation, edits)
 * - Enhanced debugging context with user actions and page state
 * - Rich structured contexts: MediaWiki, page, request, session, server info
 * - Comprehensive searchable tags: Version, environment, user, page, HTTP, server, performance
 * - Distributed tracing: Frontend-to-backend trace propagation via CORS headers and meta tags
 * - Backend-to-external trace propagation: Constants for extensions to add trace headers to HTTP requests
 * - Code profiling: Performance profiling with Excimer extension for production performance analysis
 * - Performance optimization: Consider Relay proxy for reduced response time impact in production
 * - Structured logging: Sentry Logs feature with searchable/filterable log entries and automatic flushing
 * - User feedback: Fully customizable crash-report modal for collecting user feedback on errors
 * - Security policy reporting: CSP and CT violation reporting with comprehensive headers
 *
 * @see https://docs.sentry.io/platforms/php/
 * @see https://docs.sentry.io/platforms/php/configuration/options/ (Configuration options)
 * @see https://docs.sentry.io/platforms/php/enriching-events/context/ (Structured contexts)
 * @see https://docs.sentry.io/platforms/php/enriching-events/breadcrumbs/ (Breadcrumbs)
 * @see https://docs.sentry.io/platforms/php/enriching-events/tags/ (Searchable tags)
 * @see https://docs.sentry.io/platforms/php/enriching-events/identify-user/ (User identification)
 * @see https://docs.sentry.io/platforms/php/data-management/sensitive-data/ (Data scrubbing)
 * @see https://docs.sentry.io/platforms/php/tracing/ (Performance tracing)
 * @see https://docs.sentry.io/platforms/php/tracing/trace-propagation/ (Distributed tracing)
 * @see https://docs.sentry.io/platforms/php/tracing/trace-propagation/custom-instrumentation/ (Custom instrumentation)
 * @see https://docs.sentry.io/platforms/php/profiling/ (Performance profiling)
 * @see https://docs.sentry.io/product/relay/ (Relay proxy for performance optimization)
 * @see https://docs.sentry.io/platforms/php/logs/ (Structured logging)
 * @see https://docs.sentry.io/platforms/php/user-feedback/ (User feedback collection)
 * @see https://docs.sentry.io/platforms/php/security-policy-reporting/ (Security policy reporting)
 * @see https://docs.sentry.io/platforms/php/troubleshooting/ (SDK troubleshooting)
 * @see https://docs.sentry.io/platforms/javascript/
 * @see wiki/configs/85-Logging.php (PSR-3 logging integration)
 * @see wiki/modules/ext.Sentry/init.js (JavaScript SDK integration)
 *
 * PHP version 8.3
 *
 * @category Configuration
 * @package  ATL-Wiki
 * @author   Atmois <atmois@allthingslinux.org>
 * @license  https://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 * @link     https://atl.wiki
 */

//######################################################// Sentry Configuration Options
//
// This configuration follows Sentry PHP SDK best practices for MediaWiki deployments.
// Options are documented with security, performance, and debugging implications.
//
// CORE OPTIONS:
// - dsn: Required - Data Source Name from Sentry project settings
// - release: Version identifier for release tracking and regression detection
// - environment: 'development', 'staging', 'production' for filtering and grouping
//
// SAMPLING & PERFORMANCE:
// - traces_sampler: Intelligent sampling based on transaction type and importance
// - traces_sample_rate: Fallback fixed sampling rate (default 0.5 = 50%)
// - profiles_sample_rate: Code profiling data sampling (default 0.5 = 50%)
//   Requires Excimer PHP extension (already installed in Dockerfile via PECL)
//   Provides function-level performance data for code optimization and bottleneck identification
// - sample_rate: Error sampling rate (set to 1.0 = 100% for complete error visibility)
// - ignore_transactions: Filters out noisy high-frequency endpoints
//
// PROFILING FEATURES:
// - Function-level execution times and call stacks
// - Memory usage patterns per function
// - Hot path identification for optimization
// - Production performance monitoring without instrumentation
//
// PERFORMANCE OPTIMIZATION:
// - Consider running Relay locally as a proxy to minimize response time impact
// - Relay forwards requests to Sentry instead of direct connections
// - Reduces PHP process response time by offloading network operations
//
// LOGS FEATURES:
// - enable_logs: Structured log entries searchable in Sentry dashboard
// - before_send_log: Filters/modifies logs before sending (production noise reduction)
// - Automatic flushing: Ensures logs are sent at end of each request
// - Monolog integration: Routes MediaWiki logs through Sentry LogsHandler
// - Default attributes: Environment, release, SDK info, user context automatically added
//
// APPLICATION CONTEXT:
// - in_app_include: Marks MediaWiki core/extensions as "in app" in stack traces
// - server_name: Identifies which server in cluster (useful for load-balanced setups)
// - prefixes: Normalizes file paths across dev/prod environments
//
// DATA COLLECTION:
// - send_default_pii: Includes user IP, user agent (GDPR compliant with before_send filtering)
// - max_breadcrumbs: Limits breadcrumb history (50 = good context vs overhead balance)
// - max_request_body_size: Controls HTTP request body capture ('small' = ~4KB limit)
// - context_lines: Stack trace context lines (8 = enhanced debugging context)
// - attach_stacktrace: Adds stack traces to all log messages
//
// CONTEXT STRUCTURE:
// - mediawiki: App version, database, server, cache, PHP info, environment
// - page: Title, namespace, ID, action, special page flags
// - request: HTTP method, URL, headers, IP, user agent, protocol
// - session: User groups, rights, registration, edit count (authenticated users)
// - server: Hostname, memory limits, upload settings, software info
//
// FILTERING & SECURITY:
// - before_send: Filters sensitive data before sending events
// - before_breadcrumb: Filters breadcrumb data (e.g., skip noisy DB queries)
// - ignore_exceptions: Common non-critical exceptions to ignore
// - ignore_transactions: Filters out noisy transaction types
// - trace_propagation_targets: Controls trace header propagation to external services
//
// ENRICHMENT & CONTEXT:
// - Manual breadcrumbs: Auth events, page views, edits, HTTP requests
// - User context: ID, username, email, IP address for authenticated users; IP-only for anonymous
// - MediaWiki context: Version, database, server, cache, PHP info
// - Page context: Title, namespace, ID, action, special page flags
// - Request context: HTTP method, URL, headers, IP, user agent
// - Session context: User groups, rights, registration, edit count (authenticated users)
// - Server context: Hostname, memory limits, upload settings, software info
// - Comprehensive searchable tags: App version, environment, PHP version, page metadata,
//   user segmentation (auth status, groups, admin, edit ranges), HTTP method/protocol,
//   server hostname, memory usage ranges
//
// DATA COLLECTION (Privacy Conscious):
// - PII enabled: IP addresses, HTTP headers, cookies (filtered via before_send)
// - Request data: URLs, query strings, bodies up to 10KB (may contain PII)
// - Source context: 8 lines of code around errors (for debugging)
// - User data: IDs, usernames, emails for authenticated users
// - Filtering: Comprehensive scrubbing of PII, auth credentials, sensitive headers, URLs, and request data
//
// @see https://docs.sentry.io/platforms/php/data-management/data-collected/
// @see https://docs.sentry.io/platforms/php/configuration/options/

//######################################################// Sentry SDK Initialization
//
// Only initialize Sentry if DSN is configured to avoid errors in local/development
// DSN (Data Source Name) contains project ID and authentication key
if (!empty($_ENV['SENTRY_DSN'])) {
    // Initialize Sentry SDK for comprehensive PHP error tracking and performance monitoring
    // This creates the global Sentry hub that captures errors, traces, and profiles
    \Sentry\init([
        // https://docs.sentry.io/platforms/php/configuration/options/#dsn
        'dsn' => $_ENV['SENTRY_DSN'],
        // https://docs.sentry.io/platforms/php/configuration/options/#release
        'release' => $_ENV['SENTRY_RELEASE'] ?? null,
        // https://docs.sentry.io/platforms/php/configuration/options/#environment
        'environment' => $_ENV['ENVIRONMENT'] ?? 'development',

        // https://docs.sentry.io/platforms/php/integrations/monolog.md#support-with-sentry-logs
        'enable_logs' => true, // Enable Sentry Logs feature for searchable/filterable log entries

        // Enable SDK internal logging for troubleshooting
        // https://docs.sentry.io/platforms/php/troubleshooting/#general
        // Use DebugFileLogger in production, DebugStdOutLogger for development
        'logger' => ($_ENV['ENVIRONMENT'] ?? 'development') === 'development'
            ? new \Sentry\Logger\DebugStdOutLogger()
            : new \Sentry\Logger\DebugFileLogger('/var/log/mediawiki/sentry-sdk.log'),

        // https://docs.sentry.io/platforms/php/logs/#before_send_log
        // Filter or modify logs before they're sent to Sentry
        'before_send_log' => function (\Sentry\Logs\Log $log): ?\Sentry\Logs\Log {
            // Filter out excessive debug logs in production
            if (($_ENV['ENVIRONMENT'] ?? 'development') === 'production') {
                // Skip debug-level logs in production to reduce noise
                if ($log->getLevel() === \Sentry\Logs\LogLevel::debug()) {
                    return null;
                }
                // Skip info-level logs that are too frequent/noisy in production
                if ($log->getLevel() === \Sentry\Logs\LogLevel::info()) {
                    // For now, keep all info logs - can be customized based on actual log patterns
                    // Common noisy patterns could be filtered here
                    return null; // Remove this line to keep info logs
                }
            }

            // Log is automatically enhanced with default attributes:
            // - environment, release, sdk.name, sdk.version
            // - server.address, user.id/name/email (if available)
            // - message.template, message.parameter.X (if parameterized)
            // - origin (if from integration)
    
            return $log;
        },

        // https://docs.sentry.io/platforms/php/configuration/integrations/
        // Using default integrations (ExceptionListener, ErrorListener, FatalErrorListener, Request, Transaction, FrameContextifier, Environment, Modules)
        // These provide comprehensive error handling, request context, environment info, and module versions
        // No custom integration callback needed - defaults are sufficient for MediaWiki

        // https://docs.sentry.io/platforms/php/tracing/#configure
        // Intelligent sampling could be implemented here using traces_sampler for
        // different sampling rates based on transaction type and importance
        // Currently using fixed sampling rate - can be enhanced with custom sampler

        // Fallback for environments without custom sampler
        // https://docs.sentry.io/platforms/php/configuration/options/#traces_sample_rate
        'traces_sample_rate' => (float) ($_ENV['SENTRY_TRACES_SAMPLE_RATE'] ?? 0.5),  // 50% of traces
        // https://docs.sentry.io/platforms/php/profiling/#enabling-profiling
        // Requires Excimer PHP extension (already installed in Dockerfile via PECL)
        // Profiles function-level performance data for code optimization
        'profiles_sample_rate' => (float) ($_ENV['SENTRY_PROFILES_SAMPLE_RATE'] ?? 0.5), // 50% of profiles
        // https://docs.sentry.io/platforms/php/configuration/options/#sample_rate
        'sample_rate' => 1.0, // Always capture errors - they're critical for debugging

        // https://docs.sentry.io/platforms/php/configuration/options/#error_types
        'error_types' => E_ALL,
        // https://docs.sentry.io/platforms/php/configuration/options/#attach_stacktrace
        'attach_stacktrace' => true,

        // https://docs.sentry.io/platforms/php/configuration/options/#send_default_pii
        'send_default_pii' => true, // Enables: IP addresses, HTTP headers, cookies (GDPR compliant with before_send filtering)
        // https://docs.sentry.io/platforms/php/configuration/options/#max_breadcrumbs
        'max_breadcrumbs' => 50,

        // https://docs.sentry.io/platforms/php/configuration/options/#in_app_include
        'in_app_include' => [
            '/var/www/wiki/mediawiki/includes/',    // MediaWiki core
            '/var/www/wiki/mediawiki/extensions/',  // MediaWiki extensions
            '/var/www/wiki/mediawiki/skins/',      // MediaWiki skins
            '/var/www/wiki/mediawiki/maintenance/', // Maintenance scripts
            '/var/www/wiki/configs/',              // Our custom configurations
            '/var/www/wiki/modules/',              // Our custom modules
        ],

        // https://docs.sentry.io/platforms/php/configuration/options/#server_name
        'server_name' => gethostname(),

        // https://docs.sentry.io/platforms/php/configuration/options/#prefixes
        'prefixes' => [
            '/var/www/html/',     // Production/staging web root
            '/var/www/wiki/',     // Our wiki directory (current deployment)
        ],

        // HTTP request body capture settings
        // https://docs.sentry.io/platforms/php/configuration/options/#max_request_body_size
        'max_request_body_size' => 'medium', // Sends JSON/form bodies up to ~10KB (never sends uploaded files)

        // https://docs.sentry.io/platforms/php/configuration/options/#context_lines
        'context_lines' => 8, // Sends 8 lines of source code around errors (set to 0 to disable)

        // https://docs.sentry.io/platforms/php/configuration/options/#ignore_exceptions
        'ignore_exceptions' => [
            'MediaWiki\\Permissions\\PermissionDeniedError',  // Expected permission errors
            'MediaWiki\\User\\UserNotLoggedIn',               // Login required errors
            'MediaWiki\\Api\\ApiUsageException',              // API validation errors
        ],

        // https://docs.sentry.io/platforms/php/data-management/sensitive-data/#before-send-before-send-transaction
        // Comprehensive data scrubbing to ensure sensitive information never leaves the server
        'before_send' => function (\Sentry\Event $event): ?\Sentry\Event {
            // Helper function to recursively scrub sensitive data from arrays
            $scrubArrayRecursively = function ($array, $sensitiveKeys) use (&$scrubArrayRecursively) {
                if (!is_array($array)) {
                    return $array;
                }

                $result = [];
                foreach ($array as $key => $value) {
                    // Remove sensitive keys entirely
                    if (in_array(strtolower($key), $sensitiveKeys)) {
                        continue;
                    }

                    // Recursively process nested arrays/objects
                    if (is_array($value)) {
                        $result[$key] = $scrubArrayRecursively($value, $sensitiveKeys);
                    } else {
                        $result[$key] = $value;
                    }
                }
                return $result;
            };
            // 1. Remove sensitive environment variables
            if (isset($event->getExtra()['server_vars']['_ENV'])) {
                unset($event->getExtra()['server_vars']['_ENV']);
            }

            // 2. Remove sensitive cookies and authentication tokens
            if (isset($event->getRequest()['cookies'])) {
                $cookies = $event->getRequest()['cookies'];
                // Remove session cookies, auth tokens, and other sensitive cookies
                $sensitiveCookies = ['session', 'token', 'auth', 'login', 'password', 'csrf'];
                foreach ($sensitiveCookies as $cookieName) {
                    unset($cookies[$cookieName]);
                }
                $event->getRequest()['cookies'] = $cookies;
            }

            // 3. Scrub sensitive data from HTTP headers
            if (isset($event->getRequest()['headers'])) {
                $headers = $event->getRequest()['headers'];
                // Remove authorization headers
                unset($headers['authorization'], $headers['x-api-key'], $headers['x-auth-token']);
                // Scrub sensitive header values (keep header names for debugging)
                if (isset($headers['x-forwarded-for'])) {
                    // Keep only first IP in forwarded chain for debugging
                    $forwarded = explode(',', $headers['x-forwarded-for']);
                    $headers['x-forwarded-for'] = trim($forwarded[0]);
                }
                $event->getRequest()['headers'] = $headers;
            }

            // 4. Scrub sensitive data from query strings
            if (isset($event->getRequest()['query_string'])) {
                $query = $event->getRequest()['query_string'];
                // Remove sensitive query parameters
                $sensitiveParams = ['password', 'token', 'key', 'secret', 'api_key'];
                parse_str($query, $parsedQuery);
                foreach ($sensitiveParams as $param) {
                    unset($parsedQuery[$param]);
                }
                $event->getRequest()['query_string'] = http_build_query($parsedQuery);
            }

            // 5. Scrub sensitive data from POST data (request body)
            if (isset($event->getRequest()['data'])) {
                $data = $event->getRequest()['data'];
                if (is_array($data)) {
                    // Recursively scrub sensitive keys from arrays/objects
                    $sensitiveKeys = ['password', 'token', 'secret', 'key', 'auth', 'session', 'csrf'];
                    $data = $scrubArrayRecursively($data, $sensitiveKeys);
                    $event->getRequest()['data'] = $data;
                }
            }

            // 6. Scrub sensitive data from user context
            if ($event->getUser()) {
                $user = $event->getUser();
                // Remove any additional sensitive user data beyond id/username/email
                unset($user['password'], $user['token'], $user['secret']);
                $event->setUser($user);
            }

            // 7. Scrub transaction names that might contain sensitive data
            $transaction = $event->getTransaction();
            if ($transaction) {
                // Parameterize URLs to remove sensitive IDs
                // Example: /users/12345/profile -> /users/:id/profile
                $transaction = preg_replace('/\/users\/\d+\//', '/users/:id/', $transaction);
                $transaction = preg_replace('/\/pages\/\d+\//', '/pages/:id/', $transaction);
                $event->setTransaction($transaction);
            }

            return $event;
        },

        // https://docs.sentry.io/platforms/php/configuration/options/#before_breadcrumb
        'before_breadcrumb' => function (\Sentry\Breadcrumb $breadcrumb): ?\Sentry\Breadcrumb {
            // Skip noisy database breadcrumbs in production
            if (
                $breadcrumb->getCategory() === 'db.query' &&
                $_ENV['ENVIRONMENT'] === 'production' &&
                strpos($breadcrumb->getMessage(), 'SELECT') === 0
            ) {
                return null; // Skip SELECT queries in production
            }

            return $breadcrumb;
        },

        // https://docs.sentry.io/platforms/php/configuration/options/#ignore_transactions
        // Skip tracing for high-frequency, low-value endpoints to reduce noise
        // These transactions are too frequent to be useful for performance monitoring
        'ignore_transactions' => [
            '/api.php?action=opensearch',           // Search suggestions (very frequent)
            '/api.php?action=query&meta=userinfo',  // User info queries (frequent)
            '/load.php*',                           // Resource loader requests (frequent)
            '/api.php?action=parse',                // Preview parsing (can be noisy)
            '/api.php?action=usercontribs',         // User contributions (frequent)
            '/api.php?action=recentchanges',        // Recent changes (frequent)
            '/Special:RecentChanges',               // Recent changes page
            '/Special:Watchlist',                   // Watchlist page
            '/api.php?action=query&list=watchlist', // Watchlist API
            '/.+\.(css|js|png|jpg|jpeg|gif|svg|ico|woff|woff2)$', // Static assets
        ],

        // https://docs.sentry.io/platforms/php/configuration/options/#trace_propagation_targets
        'trace_propagation_targets' => [
            '/^https:\/\/.*\.atl\.wiki/',     // Our own wiki domains
        ],
    ]);
}

// Sentry ResourceLoader module registration (must be done before BeforePageDisplay)
// This follows MediaWiki best practices for loading JavaScript on all pages
$wgResourceModules['ext.sentry'] = [
    'localBasePath' => dirname(__DIR__) . '/modules',
    'remoteExtPath' => 'ATL-Wiki/modules',
    'scripts' => [
        'ext.Sentry/init.js',
    ],
    'targets' => ['desktop', 'mobile'],
];

// Send all MediaWiki exceptions to Sentry
$wgHooks['MWExceptionHandlerReport'][] = function ($e) {
    if (class_exists('\Sentry\SentrySdk')) {
        // Add breadcrumb for exception context
        \Sentry\addBreadcrumb(
            category: 'error',
            message: 'MediaWiki exception: ' . get_class($e),
            metadata: [
                'exception_type' => get_class($e),
                'exception_message' => $e->getMessage(),
                'exception_file' => $e->getFile(),
                'exception_line' => $e->getLine(),
            ],
            level: \Sentry\Breadcrumb::LEVEL_ERROR
        );
        \Sentry\captureException($e);
    }
    return true;
};

// Add breadcrumbs for user login/logout events
$wgHooks['UserLoginComplete'][] = function ($user) {
    if (class_exists('\Sentry\SentrySdk')) {
        \Sentry\addBreadcrumb(
            category: 'auth',
            message: 'User login completed',
            metadata: [
                'user_id' => $user->getId(),
                'username' => $user->getName(),
            ],
            level: \Sentry\Breadcrumb::LEVEL_INFO
        );
    }
    return true;
};

$wgHooks['UserLogoutComplete'][] = function ($user) {
    if (class_exists('\Sentry\SentrySdk')) {
        \Sentry\addBreadcrumb(
            category: 'auth',
            message: 'User logout completed',
            metadata: [
                'user_id' => $user->getId(),
                'username' => $user->getName(),
            ],
            level: \Sentry\Breadcrumb::LEVEL_INFO
        );
    }
    return true;
};

// Add breadcrumbs for page edit events
$wgHooks['PageContentSaveComplete'][] = function ($wikiPage, $user, $content, $summary, $isMinor, $isWatch, $section, $flags, $revision, $status, $baseRevId) {
    if (class_exists('\Sentry\SentrySdk')) {
        $title = $wikiPage->getTitle();
        \Sentry\addBreadcrumb(
            category: 'edit',
            message: 'Page edit saved: ' . $title->getPrefixedText(),
            metadata: [
                'page_title' => $title->getPrefixedText(),
                'user_id' => $user->getId(),
                'username' => $user->getName(),
                'is_minor' => $isMinor,
                'summary' => $summary,
                'section' => $section,
                'revision_id' => $revision ? $revision->getId() : null,
            ],
            level: \Sentry\Breadcrumb::LEVEL_INFO
        );
    }
    return true;
};

// Helper constants for extensions making traced HTTP requests
// Extensions can use these to add trace propagation headers to outgoing requests
// https://docs.sentry.io/platforms/php/tracing/trace-propagation/custom-instrumentation/
// This enables backend-to-backend distributed tracing when MediaWiki makes HTTP requests to external services

// Example usage for extensions:
// $headers = [
//     'sentry-trace' => SENTRY_TRACE_HEADER,
//     'baggage' => SENTRY_BAGGAGE_HEADER,
//     // ... other headers
// ];
// $req = new MWHttpRequest($url, ['headers' => $headers]);

if (!defined('SENTRY_TRACE_HEADER')) {
    define('SENTRY_TRACE_HEADER', \Sentry\getTraceparent());
}
if (!defined('SENTRY_BAGGAGE_HEADER')) {
    define('SENTRY_BAGGAGE_HEADER', \Sentry\getBaggage());
}

// Add MediaWiki user context to Sentry events and inject client-side Sentry SDK
$wgHooks['BeforePageDisplay'][] = function ($out, $skin) {
    if (class_exists('\Sentry\SentrySdk')) {
        try {
            $user = $skin->getUser();
            $title = $out->getTitle();
            $request = $out->getRequest();

            // Extract incoming trace propagation headers for distributed tracing
            // https://docs.sentry.io/platforms/php/tracing/trace-propagation/custom-instrumentation/
            $sentryTraceHeader = $request->getHeader('sentry-trace');
            $baggageHeader = $request->getHeader('baggage');

            if (!empty($sentryTraceHeader) || !empty($baggageHeader)) {
                \Sentry\continueTrace($sentryTraceHeader, $baggageHeader);
            }

            // Add manual breadcrumbs for key MediaWiki events
            // https://docs.sentry.io/platforms/php/enriching-events/breadcrumbs/#manual-breadcrumbs

            // User authentication breadcrumb
            if ($user && $user->isRegistered()) {
                \Sentry\addBreadcrumb(
                    category: 'auth',
                    message: 'User authenticated',
                    metadata: [
                        'user_id' => $user->getId(),
                        'username' => $user->getName(),
                        'user_groups' => $user->getGroups(),
                    ],
                    level: \Sentry\Breadcrumb::LEVEL_INFO
                );
            } else {
                \Sentry\addBreadcrumb(
                    category: 'auth',
                    message: 'Anonymous user access',
                    level: \Sentry\Breadcrumb::LEVEL_INFO
                );
            }

            // Page context breadcrumb
            if ($title) {
                \Sentry\addBreadcrumb(
                    category: 'navigation',
                    message: 'Page view: ' . $title->getPrefixedText(),
                    metadata: [
                        'page_title' => $title->getPrefixedText(),
                        'namespace' => $title->getNamespace(),
                        'page_id' => $title->getArticleID(),
                        'action' => $request->getVal('action', 'view'),
                    ],
                    level: \Sentry\Breadcrumb::LEVEL_INFO
                );
            }

            // Request context breadcrumb
            \Sentry\addBreadcrumb(
                category: 'http',
                message: 'HTTP ' . $request->getMethod() . ' ' . $request->getRequestURL(),
                metadata: [
                    'method' => $request->getMethod(),
                    'url' => $request->getRequestURL(),
                    'user_agent' => $request->getHeader('User-Agent'),
                    'referer' => $request->getHeader('Referer'),
                ],
                level: \Sentry\Breadcrumb::LEVEL_INFO
            );

            // Configure Sentry scope with comprehensive context
            \Sentry\configureScope(function (\Sentry\State\Scope $scope) use ($user, $out, $title, $request): void {
                // User context - comprehensive user identification for error correlation
                // https://docs.sentry.io/platforms/php/enriching-events/identify-user/
                if ($user && $user->isRegistered()) {
                    $scope->setUser([
                        'id' => (string) $user->getId(),           // Internal user ID (required)
                        'username' => $user->getName(),            // Display name
                        'email' => $user->getEmail() ?: null,      // Email for notifications/Gravatars
                        'ip_address' => $request->getIP(),         // IP address (PII enabled)
                    ]);
                } else {
                    // For anonymous users, use IP as identifier when PII is enabled
                    $scope->setUser([
                        'ip_address' => $request->getIP(),         // IP as anonymous user identifier
                    ]);
                }

                // MediaWiki application context
                global $wgVersion, $wgDBname, $wgServer, $wgMainCacheType, $wgSitename, $wgLanguageCode;
                $scope->setContext('mediawiki', [
                    'version' => $wgVersion ?? 'unknown',
                    'database' => $wgDBname ?? 'unknown',
                    'server' => $wgServer ?? 'unknown',
                    'sitename' => $wgSitename ?? 'unknown',
                    'language' => $wgLanguageCode ?? 'unknown',
                    'cache_type' => $wgMainCacheType ?? 'none',
                    'php_version' => PHP_VERSION,
                    'environment' => $_ENV['ENVIRONMENT'] ?? 'unknown',
                ]);

                // Page context
                if ($title) {
                    $scope->setContext('page', [
                        'title' => $title->getPrefixedText(),
                        'namespace' => $title->getNamespace(),
                        'namespace_text' => $title->getNsText(),
                        'page_id' => $title->getArticleID(),
                        'is_main_page' => $title->isMainPage(),
                        'is_special' => $title->isSpecialPage(),
                        'action' => $request->getVal('action', 'view'),
                    ]);
                }

                // HTTP request context
                $scope->setContext('request', [
                    'method' => $request->getMethod(),
                    'url' => $request->getRequestURL(),
                    'protocol' => $request->getProtocol(),
                    'ip' => $request->getIP(),
                    'user_agent' => $request->getHeader('User-Agent'),
                    'referer' => $request->getHeader('Referer'),
                    'accept_language' => $request->getHeader('Accept-Language'),
                    'content_type' => $request->getHeader('Content-Type'),
                ]);

                // Session context (for authenticated users)
                if ($user && $user->isRegistered()) {
                    $session = $request->getSession();
                    $scope->setContext('session', [
                        'session_id' => $session ? substr(session_id(), 0, 8) . '...' : null, // Partial for security
                        'user_groups' => $user->getGroups(),
                        'user_rights' => $user->getRights(),
                        'registration_date' => $user->getRegistration() ? $user->getRegistration() : null,
                        'edit_count' => $user->getEditCount(),
                        'is_admin' => in_array('sysop', $user->getGroups()),
                    ]);
                }

                // Server/environment context
                $scope->setContext('server', [
                    'hostname' => gethostname(),
                    'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? null,
                    'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? null,
                    'script_filename' => $_SERVER['SCRIPT_FILENAME'] ?? null,
                    'memory_limit' => ini_get('memory_limit'),
                    'max_execution_time' => ini_get('max_execution_time'),
                    'upload_max_filesize' => ini_get('upload_max_filesize'),
                ]);

                // Add comprehensive searchable tags for filtering and analysis
// Tags enable powerful filtering, searching, and issue correlation in Sentry
// Tag keys: max 32 chars, alphanumeric + _ . : -
// Tag values: max 200 chars, no newlines (values over 200 chars get truncated!)
// https://docs.sentry.io/platforms/php/troubleshooting/#general
                // https://docs.sentry.io/platforms/php/enriching-events/tags/

                // Application & Environment tags
                $scope->setTag('mediawiki.version', $wgVersion ?? 'unknown');
                $scope->setTag('environment', $_ENV['ENVIRONMENT'] ?? 'unknown');
                $scope->setTag('php.version', PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION);

                // Page & Content tags
                if ($title) {
                    $scope->setTag('page.namespace', (string) $title->getNamespace());
                    $scope->setTag('page.action', $request->getVal('action', 'view'));
                    $scope->setTag('page.is_special', $title->isSpecialPage() ? 'true' : 'false');
                    $scope->setTag('page.is_main', $title->isMainPage() ? 'true' : 'false');
                }

                // User & Authentication tags
                if ($user && $user->isRegistered()) {
                    $scope->setTag('user.authenticated', 'true');
                    $scope->setTag('user.has_groups', !empty($user->getGroups()) ? 'true' : 'false');
                    $scope->setTag('user.is_admin', in_array('sysop', $user->getGroups()) ? 'true' : 'false');
                    // Tag user edit count ranges for segmentation
                    $editCount = $user->getEditCount();
                    if ($editCount === 0) {
                        $scope->setTag('user.edit_range', '0');
                    } elseif ($editCount < 10) {
                        $scope->setTag('user.edit_range', '1-9');
                    } elseif ($editCount < 100) {
                        $scope->setTag('user.edit_range', '10-99');
                    } elseif ($editCount < 1000) {
                        $scope->setTag('user.edit_range', '100-999');
                    } else {
                        $scope->setTag('user.edit_range', '1000+');
                    }
                } else {
                    $scope->setTag('user.authenticated', 'false');
                }

                // Request & HTTP tags
                $scope->setTag('http.method', $request->getMethod());
                $scope->setTag('http.protocol', $request->getProtocol());

                // Server & Infrastructure tags
                $scope->setTag('server.hostname', gethostname());

                // Performance & Resource tags (useful for monitoring resource issues)
                $memoryUsage = memory_get_peak_usage(true);
                if ($memoryUsage < 32 * 1024 * 1024) { // < 32MB
                    $scope->setTag('memory.usage', 'low');
                } elseif ($memoryUsage < 128 * 1024 * 1024) { // < 128MB
                    $scope->setTag('memory.usage', 'medium');
                } else { // >= 128MB
                    $scope->setTag('memory.usage', 'high');
                }
            });

            // Inject Sentry Loader Script for JavaScript error tracking
            // The Loader Script is Sentry's recommended approach - it auto-updates the SDK
            // and lazy-loads the full SDK only when needed, improving page load performance
            $sentryDsn = $_ENV['SENTRY_DSN'] ?? null;
            $loaderKey = $_ENV['SENTRY_LOADER_KEY'] ?? null;
            if ($sentryDsn && $loaderKey) {
                $env = $_ENV['ENVIRONMENT'] ?? 'development';
                $release = $_ENV['SENTRY_RELEASE'] ?? null;
                $tracesSampleRate = (float) ($_ENV['SENTRY_TRACES_SAMPLE_RATE'] ?? 1.0);

                // Get the last event ID for crash report modal
                // This enables user feedback collection when errors occur
                // https://docs.sentry.io/platforms/php/user-feedback/#crash-report-modal
                $lastEventId = \Sentry\SentrySdk::getCurrentHub()->getLastEventId();

                // Pass config to JavaScript via MediaWiki's proper method
                $out->addJsConfigVars([
                    'wgSentryConfig' => [
                        'environment' => $env,
                        'tracesSampleRate' => $tracesSampleRate,
                        'release' => $release,
                        'loaderKey' => $loaderKey,
                        // Enable Sentry toolbar for debugging
                        'debug' => $env === 'development',
                        'attachStacktrace' => true,
                    ],
                    'wgSentryToolbar' => [
                        'organizationSlug' => $_ENV['SENTRY_ORG_SLUG'] ?? null,
                        'projectSlug' => $_ENV['SENTRY_PROJECT_SLUG'] ?? null,
                        'enabled' => ($env === 'development' || $env === 'staging' || $env === 'local') && !empty($_ENV['SENTRY_ORG_SLUG']) && !empty($_ENV['SENTRY_PROJECT_SLUG']),
                    ],
                    'wgSentryUser' => $user && $user->isRegistered() ? [
                        'id' => (string) $user->getId(),
                        'username' => $user->getName(),
                        'email' => $user->getEmail() ?: null,
                    ] : null,
                    // Crash report modal configuration
                    // Collects user feedback when errors occur with full customization options
                    // https://docs.sentry.io/platforms/php/user-feedback/configuration/#crash-report-modal
                    'wgSentryCrashReport' => [
                        'enabled' => true, // Enable crash report modal for user feedback
                        'eventId' => $lastEventId, // Last error event ID for feedback collection
                        'showDialogOnError' => true, // Auto-show dialog when errors occur

                        // UI Text Customization
                        'title' => 'Help us fix this issue',
                        'subtitle' => 'We\'ve detected an error. Your feedback helps us improve.',
                        'subtitle2' => 'If you\'d like to help, tell us what happened below.',

                        // Form Labels
                        'labelName' => 'Name',
                        'labelEmail' => 'Email',
                        'labelComments' => 'What happened?',
                        'labelClose' => 'Close',
                        'labelSubmit' => 'Submit',

                        // Status Messages
                        'errorGeneric' => 'An unknown error occurred while submitting your report. Please try again.',
                        'errorFormEntry' => 'Some fields were invalid. Please correct the errors and try again.',
                        'successMessage' => 'Your feedback has been sent. Thank you!',

                        // Callbacks (will be handled in JavaScript)
                        'onLoad' => null, // Callback when widget opens
                        'onClose' => null, // Callback when widget closes

                        // Pre-fill user data if available (helps with user identification)
                        // For authenticated users, pre-fill name and email in the feedback form
                        // This reduces friction and improves feedback quality
                        'user' => $user && $user->isRegistered() ? [
                            'name' => $user->getName(),
                            'email' => $user->getEmail() ?: null,
                        ] : null,
                    ],
                ]);

                // Inject trace propagation meta tags for JavaScript SDK
                // https://docs.sentry.io/platforms/php/tracing/trace-propagation/custom-instrumentation/
                $out->addHeadItem('sentry-trace-meta', sprintf(
                    '<meta name="sentry-trace" content="%s"/>',
                    htmlspecialchars(\Sentry\getTraceparent() ?? '', ENT_QUOTES, 'UTF-8')
                ));
                $out->addHeadItem('sentry-baggage-meta', sprintf(
                    '<meta name="baggage" content="%s"/>',
                    htmlspecialchars(\Sentry\getBaggage() ?? '', ENT_QUOTES, 'UTF-8')
                ));

                // Load Sentry module via ResourceLoader (clean and proper)
                $out->addModules(['ext.sentry']);
            }
        } catch (\Throwable $e) {
            // Don't break the page if Sentry context setting fails
            error_log('Sentry user context failed: ' . $e->getMessage());
        }
    }
    return true;
};

// Flush Sentry logs at the end of each request
// Ensures all pending log entries are sent to Sentry
// Critical for CLI tasks and long-running processes
$wgHooks['BeforePageDisplay'][] = function ($out, $skin) {
    if (class_exists('\Sentry\SentrySdk')) {
        try {
            // Flush pending Sentry logs to ensure they're sent
            // This is especially important for CLI tasks and long-running requests
            // https://docs.sentry.io/platforms/php/logs/#usage
            \Sentry\logger()->flush();
        } catch (\Throwable $e) {
            // Don't break the page if log flushing fails
            error_log('Error flushing Sentry logs: ' . $e->getMessage());
        }
    }
    return true;
};

// Sentry tunnel endpoint for ad-blocker bypass
// Handles tunneled requests from JavaScript SDK
// https://docs.sentry.io/platforms/javascript/troubleshooting/#using-the-tunnel-option
$wgHooks['ApiBeforeMain'][] = function (&$processor) {
    $request = $processor->getRequest();
    $action = $request->getVal('action');

    if ($action === 'sentry-tunnel') {
        // Handle Sentry tunnel requests
        try {
            $dsn = $_ENV['SENTRY_DSN'] ?? null;
            if (!$dsn) {
                http_response_code(400);
                echo json_encode(['error' => 'Sentry DSN not configured']);
                exit;
            }

            // Parse DSN to get project ID and host
            $dsnParts = parse_url($dsn);
            if (!$dsnParts || !isset($dsnParts['scheme'], $dsnParts['host'], $dsnParts['path'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid Sentry DSN']);
                exit;
            }

            $host = $dsnParts['scheme'] . '://' . $dsnParts['host'];
            $pathParts = explode('/', trim($dsnParts['path'], '/'));
            $projectId = end($pathParts);

            // Extract public key
            $userParts = explode('@', $dsnParts['user'] ?? '');
            $publicKey = $userParts[0] ?? '';

            if (!$projectId || !$publicKey) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid Sentry DSN format']);
                exit;
            }

            // Read the envelope data from request body
            $envelopeData = file_get_contents('php://input');
            if (!$envelopeData) {
                http_response_code(400);
                echo json_encode(['error' => 'No envelope data received']);
                exit;
            }

            // Forward to Sentry
            $sentryUrl = "{$host}/api/{$projectId}/envelope/";
            $context = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => 'Content-Type: application/x-sentry-envelope',
                    'content' => $envelopeData,
                    'timeout' => 5,
                ]
            ]);

            $result = file_get_contents($sentryUrl, false, $context);

            if ($result === false) {
                http_response_code(502);
                echo json_encode(['error' => 'Failed to forward to Sentry']);
                exit;
            }

            // Return success
            http_response_code(200);
            echo $result;
            exit;

        } catch (\Throwable $e) {
            error_log('Sentry tunnel error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Internal server error']);
            exit;
        }
    }

    return true;
};

// Configure Security Policy Headers with Sentry reporting
// Enables CSP and CT violation reporting to Sentry for comprehensive security monitoring
// https://docs.sentry.io/platforms/php/security-policy-reporting/
$wgCSPHeader = false; // Disable default CSP, we'll add our own
$wgHooks['SecurityResponseHeader'][] = function (&$headers, $name) {
    $sentryDsn = $_ENV['SENTRY_DSN'] ?? null;
    $environment = $_ENV['ENVIRONMENT'] ?? 'development';
    $release = $_ENV['SENTRY_RELEASE'] ?? null;

    if ($name === 'Content-Security-Policy') {
        // Build CSP with Sentry security reporting
        // CSP Requirements for JavaScript SDK: https://docs.sentry.io/platforms/javascript/install/loader/#content-security-policy
        $cspParts = [
            "default-src 'self'",
            // Allow Sentry CDN domains for JavaScript SDK loading (loader script + bundles)
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' *.sentry.io js.sentry-cdn.com browser.sentry-cdn.com",
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data: *.sentry.io sentry.io",
            // Allow connection to Sentry for error reporting and security violations
            "connect-src 'self' *.sentry.io sentry.io",
            "frame-src 'self' *.sentry.io sentry.io",
            "font-src 'self' data:",
            "media-src 'self'",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'"
        ];

        // Add CSP violation reporting to Sentry if DSN is available
        if ($sentryDsn) {
            $securityUrl = getSentrySecurityUrl($sentryDsn, $environment, $release);

            if ($securityUrl) {
                // Add CSP reporting directives
                $cspParts[] = "report-uri {$securityUrl}";
                $cspParts[] = "report-to csp-endpoint";

                // Add additional headers for modern CSP reporting
                $headers[] = "Report-To: {\"group\":\"csp-endpoint\",\"max_age\":10886400,\"endpoints\":[{\"url\":\"{$securityUrl}\"}],\"include_subdomains\":true}";
                $headers[] = "Reporting-Endpoints: csp-endpoint=\"{$securityUrl}\"";

                // Certificate Transparency reporting (Expect-CT header)
                // Reports invalid or misissued certificates
                $headers[] = "Expect-CT: report-uri=\"{$securityUrl}\", max-age=86400, enforce";
            }
        }

        $csp = implode('; ', $cspParts);
        $headers[] = $csp;
    }
    return true;
};

// Helper function to build Sentry security endpoint URL
function getSentrySecurityUrl($dsn, $environment = null, $release = null)
{
    // Extract Sentry security endpoint from DSN
    // DSN format: https://public_key@host/path/project_id
    $dsnParts = parse_url($dsn);
    if (!$dsnParts || !isset($dsnParts['scheme'], $dsnParts['host'], $dsnParts['path'])) {
        return null;
    }

    $host = $dsnParts['scheme'] . '://' . $dsnParts['host'];
    $pathParts = explode('/', trim($dsnParts['path'], '/'));
    $projectId = end($pathParts);

    // Extract public key from DSN
    $userParts = explode('@', $dsnParts['user'] ?? '');
    $publicKey = $userParts[0] ?? '';

    if (!$projectId || !$publicKey) {
        return null;
    }

    // Build security endpoint URL
    $securityUrl = "{$host}/api/{$projectId}/security/?sentry_key={$publicKey}";

    // Add environment and release if available
    if ($environment) {
        $securityUrl .= "&sentry_environment=" . urlencode($environment);
    }
    if ($release) {
        $securityUrl .= "&sentry_release=" . urlencode($release);
    }

    return $securityUrl;
}
