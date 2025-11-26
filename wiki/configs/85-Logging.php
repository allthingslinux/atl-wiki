<?php
/**
 * MediaWiki Logging Configuration
 *
 * This file centralizes all logging-related configuration for MediaWiki,
 * implementing PSR-3 compliant structured logging with Monolog and Sentry integration.
 *
 * PSR-3 Compliance & Monolog Integration:
 * - Implements LoggerInterface with 8 RFC 5424 log levels (debug, info, notice, warning, error, critical, alert, emergency)
 * - Supports structured context arrays for rich debugging data
 * - Uses placeholder interpolation ({key} replacement from context)
 * - Handles exceptions in 'exception' context key
 * - Leverages Monolog channels for component-specific logging
 * - Uses handlers stack with proper bubbling configuration
 * - Implements processors for automatic context enrichment
 *
 * LogRecord Structure (Monolog\LogRecord):
 * - message: Log text with PSR-3 placeholder interpolation ({key} → context value)
 * - level: RFC 5424 severity level (DEBUG=100, INFO=200, etc.)
 * - context: User-provided data array (3rd parameter to logger methods)
 * - channel: Logger channel name ('exception', 'dberror', etc.)
 * - datetime: Timestamp when logged (JsonSerializableDateTimeImmutable)
 * - extra: Processor-added data (internal, processors write here to avoid context conflicts)
 *
 * Features:
 * - PSR-3 compliant structured logging via LoggerFactory
 * - Monolog integration with comprehensive processors
 * - Environment-aware filtering (dev vs production)
 * - Multiple log channels with separate handlers
 * - Sentry integration for error tracking and alerting
 * - Context data preservation (unlike MediaWiki's default logger)
 * - MediaWiki debug features: toolbar, exception details, SQL logging
 * - Security-conscious configuration (disabled in production)
 * - Sentry Logs integration: searchable/filterable log entries
 * - Environment variable configuration for all settings
 *
 * LogRecord Flow & Data Separation:
 * 1. User calls: $logger->info('User {name} logged in', ['name' => 'John', 'ip' => '1.2.3.4'])
 * 2. Processors add system data to LogRecord.extra (memory, PID, etc.)
 * 3. PsrLogMessageProcessor interpolates: 'User John logged in'
 * 4. Handlers format and send to destinations (files, Sentry)
 *
 * Context vs Extra:
 * - context: User-provided data (preserved, used for interpolation, read-only)
 * - extra: System/processor-added data (internal enrichment, processors write here)
 *
 * Example LogRecord after processing:
 * message: "User John logged in"
 * level: INFO (200)
 * context: ['name' => 'John', 'ip' => '1.2.3.4']  // User data
 * channel: 'security'
 * datetime: DateTime object
 * extra: ['memory_peak' => 12345678, 'uid' => 'abc123...']  // System data
 *
 * BC Compatibility: LogRecord implements ArrayAccess for Monolog 1/2 compatibility
 * - $record['message'], $record['level'], $record['context'] (but not 'level_name')
 * - Use $record->level->getName() for level name, or $record['level_name'] for BC
 *
 * Log Levels (RFC 5424):
 * - EMERGENCY (0): System unusable - requires immediate attention
 * - ALERT (1): Action must be taken immediately (SMS alerts)
 * - CRITICAL (2): Critical conditions - component unavailable
 * - ERROR (3): Runtime errors - recoverable failures
 * - WARNING (4): Exceptional occurrences - not necessarily errors
 * - NOTICE (5): Normal but significant events
 * - INFO (6): Interesting events - user logins, SQL operations
 * - DEBUG (7): Detailed debug information - development only
 *
 * Context Data Standards:
 * - Use 'exception' key for Exception objects (PSR-3 requirement)
 * - Placeholder interpolation: "User {username} logged in" with ['username' => 'john']
 * - Avoid reserved keys: message, channel, host, level, type, @timestamp, @version
 * - Include debugging context: user, title, request_id, etc.
 *
 * Security Notes:
 * - Debug logs can contain sensitive data (cookies, session IDs, form data)
 * - Log files should never be publicly accessible
 * - Always sanitize logs before sharing for debugging
 * - Use environment-appropriate filtering to reduce sensitive data exposure
 *
 * @see https://www.mediawiki.org/wiki/Manual:Structured_logging
 * @see https://www.mediawiki.org/wiki/Manual:How_to_debug (Complete debugging guide)
 * @see https://www.mediawiki.org/wiki/Manual:How_to_debug#Logging
 * @see https://www.mediawiki.org/wiki/ResourceLoader/Developing_with_ResourceLoader#Debugging
 * @see https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-3-logger-interface.md
 * @see https://docs.sentry.io/platforms/php/guides/monolog/
 * @see https://github.com/Seldaek/monolog/wiki/Third-Party-Packages
 * @see https://github.com/Seldaek/monolog/blob/main/doc/message-structure.md (LogRecord properties)
 *
 * Environment Variables:
 * - MW_DEBUG_EXCEPTION_DETAILS: Enable detailed exception information (default: development only)
 * - MW_DEBUG_TOOLBAR: Enable debug toolbar (default: development only)
 * - MW_DEBUG_SHOW_LOGS: Show debug logs in HTML output (default: development only)
 * - MW_DEBUG_DEVELOPMENT_WARNINGS: Show deprecation warnings (default: development only)
 * - MW_DEBUG_DUMP_SQL: Log all SQL queries (default: development only)
 * - MW_DEBUG_RESOURCE_LOADER: Enable ResourceLoader debug mode (default: development only)
 * - MW_DEBUG_LOG_FILE: Main debug log file path (default: /var/log/mediawiki/debug.log)
 * - MW_DEBUG_DB_ERROR_LOG_FILE: Database error log file path (default: /var/log/mediawiki/dberror.log)
 * - MW_EXCEPTION_LOG_FILE: Exception log file path (default: /var/log/mediawiki/exception.log)
 * - MW_DBERROR_LOG_FILE: DB error log file path (default: /var/log/mediawiki/dberror.log)
 * - MW_RESOURCELOADER_LOG_FILE: ResourceLoader log file path (default: /var/log/mediawiki/resourceloader.log)
 * - MW_RATELIMIT_LOG_FILE: Rate limit log file path (default: /var/log/mediawiki/ratelimit.log)
 * - MW_SENTRY_LOGGING_ENABLED: Enable Sentry logging integration (default: true)
 * - MW_SENTRY_LOGS_LEVEL: Sentry LogsHandler level (TRACE, DEBUG, INFO, WARN, ERROR, FATAL)
 * - MW_SENTRY_EVENTS_LEVEL: Sentry EventsHandler level (DEBUG, INFO, WARNING, ERROR, CRITICAL, ALERT, EMERGENCY)
 * - MW_SENTRY_BREADCRUMBS_LEVEL: Sentry BreadcrumbHandler level (DEBUG, INFO, WARNING, ERROR, CRITICAL, ALERT, EMERGENCY)
 *
 * PHP version 8.3
 *
 * @category Configuration
 * @package  ATL-Wiki
 * @author   Atmois <atmois@allthingslinux.org>
 * @license  https://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 * @link     https://atl.wiki
 */

//######################################################// Error Reporting Configuration
//
// PHP Error Reporting Settings
// These control what PHP errors are reported and how they're displayed.
//
// @see https://www.php.net/manual/en/errorfunc.configuration.php
// @see https://www.mediawiki.org/wiki/Manual:How_to_debug#PHP_errors

// Report all PHP errors (-1 = E_ALL)
// This ensures all errors are logged, even if not displayed
error_reporting(-1);

// Display errors in output (disabled for security)
// NEVER enable in production - could expose sensitive information
// Errors are logged to files instead for security
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);

// Ensure full stack trace arguments are preserved
// PHP 7.4+ defaults to ignoring args in exceptions for security
// This is required for complete Sentry stack traces
// https://docs.sentry.io/platforms/php/troubleshooting/#general
if (version_compare(PHP_VERSION, '7.4.0', '>=')) {
    ini_set('zend.exception_ignore_args', 0); // Ensure args are included in stack traces
}

//######################################################// MediaWiki Debug Configuration
//
// MediaWiki-specific debugging features
// These enhance error visibility and debugging capabilities
//
// @see https://www.mediawiki.org/wiki/Manual:How_to_debug

// Show detailed exception information on fatal error pages
// Includes stack traces - useful for development but SECURITY SENSITIVE
$wgShowExceptionDetails = ($_ENV['MW_DEBUG_EXCEPTION_DETAILS'] ?? $_ENV['ENVIRONMENT'] === 'development') === 'true' ||
    $_ENV['MW_DEBUG_EXCEPTION_DETAILS'] === '1';

// Show debug toolbar on pages with profiling and log info
// Adds performance and debugging toolbar to page output
$wgDebugToolbar = ($_ENV['MW_DEBUG_TOOLBAR'] ?? $_ENV['ENVIRONMENT'] === 'development') === 'true' ||
    $_ENV['MW_DEBUG_TOOLBAR'] === '1';

// Add raw log messages to bottom of pages
// Shows debug logs directly in HTML output - development only
$wgShowDebug = ($_ENV['MW_DEBUG_SHOW_LOGS'] ?? $_ENV['ENVIRONMENT'] === 'development') === 'true' ||
    $_ENV['MW_DEBUG_SHOW_LOGS'] === '1';

// Show warnings for deprecated functions and possible errors
// Helps catch issues early in development
$wgDevelopmentWarnings = ($_ENV['MW_DEBUG_DEVELOPMENT_WARNINGS'] ?? $_ENV['ENVIRONMENT'] === 'development') === 'true' ||
    $_ENV['MW_DEBUG_DEVELOPMENT_WARNINGS'] === '1';

// Log all SQL queries (not just failed ones)
// Useful for query optimization and debugging DB issues
$wgDebugDumpSql = ($_ENV['MW_DEBUG_DUMP_SQL'] ?? $_ENV['ENVIRONMENT'] === 'development') === 'true' ||
    $_ENV['MW_DEBUG_DUMP_SQL'] === '1';

// Send debug data as HTML comments in page output
// More secure than debug logs but lost on redirects/fatal errors
// NOT recommended for production - reveals debug info in HTML
$wgDebugComments = false; // Disabled for security - never enable in production

//######################################################// ResourceLoader Debug Configuration
//
// ResourceLoader handles JavaScript/CSS loading and execution.
// These settings control debugging and error logging for frontend assets.
//
// @see https://www.mediawiki.org/wiki/ResourceLoader/Developing_with_ResourceLoader#Debugging

// ResourceLoader debug mode (loads modules individually for easier debugging)
// Only enable in development - significantly impacts performance
$wgResourceLoaderDebug = ($_ENV['MW_DEBUG_RESOURCE_LOADER'] ?? $_ENV['ENVIRONMENT'] === 'development') === 'true' ||
    $_ENV['MW_DEBUG_RESOURCE_LOADER'] === '1';

// JavaScript error logging is handled by ResourceLoader's mediawiki.errorLogger
// Errors are automatically logged to the 'resourceloader' channel above
// Client-side JavaScript errors are captured and logged server-side
//
// Debugging ResourceLoader in Browser Console:
// - mw.loader.getState('module.name') - Check module loading state
//   Returns: null (unknown), 'registered', 'loading', 'ready', 'error'
// - mw.loader.load('module.name') - Force load a module
// - ?debug=true URL parameter - Enable debug mode for easier debugging
//
// Module States:
// - null: Module not registered
// - 'registered': Known but not loaded
// - 'loading': Currently loading
// - 'ready': Successfully loaded
// - 'error': Failed to load (check 'resourceloader' log channel)

//######################################################// File-based Logging Configuration
//
// MediaWiki File Logging Settings
// These configure where different types of logs are written to disk.
//
// Security Warning: These log files can contain sensitive data including:
// - User session data, cookies, and authentication tokens
// - Database connection details and queries
// - Form data and user inputs
// - NEVER make these files publicly accessible!
//
// @see https://www.mediawiki.org/wiki/Manual:$wgDebugLogFile
// @see https://www.mediawiki.org/wiki/Manual:$wgDBerrorLog
// @see https://www.mediawiki.org/wiki/Manual:$wgDebugLogGroups

// Main debug log file (MediaWiki core debug output)
// Captures general debug information from MediaWiki core
// Use wfDebugLog() or PSR-3 loggers to write here
$wgDebugLogFile = $_ENV['MW_DEBUG_LOG_FILE'] ?? '/var/log/mediawiki/debug.log';

// Database error log (separate from dberror channel for legacy compatibility)
// Logs database-related errors and warnings
// Used by wfLogDBError() and legacy database error handling
$wgDBerrorLog = $_ENV['MW_DEBUG_DB_ERROR_LOG_FILE'] ?? '/var/log/mediawiki/dberror.log';

// Custom log groups - routes specific log channels to files
// Each channel can be directed to a separate file for organization
// These channels are automatically captured by MonologSpi and sent to Sentry
//
// Available channels:
// - 'exception': PHP exceptions and fatal errors
// - 'dberror': Database errors and query failures
// - 'resourceloader': ResourceLoader (JS/CSS) errors and warnings
// - 'ratelimit': Rate limiting events and blocks
$wgDebugLogGroups = [
    'exception' => $_ENV['MW_EXCEPTION_LOG_FILE'] ?? '/var/log/mediawiki/exception.log',
    'dberror' => $_ENV['MW_DBERROR_LOG_FILE'] ?? '/var/log/mediawiki/dberror.log',
    'resourceloader' => $_ENV['MW_RESOURCELOADER_LOG_FILE'] ?? '/var/log/mediawiki/resourceloader.log',
    'ratelimit' => $_ENV['MW_RATELIMIT_LOG_FILE'] ?? '/var/log/mediawiki/ratelimit.log',
];

//######################################################// Monolog Integration for Sentry
//
// Structured Logging with Monolog
// MediaWiki's default logging drops context data - Monolog preserves it!
//
// Why Monolog?
// - PSR-3 compliant structured logging
// - Preserves context data (unlike MediaWiki's default logger)
// - Multiple handlers (file, Sentry, etc.)
// - Environment-aware filtering
// - Rich context processors for debugging
//
// This configuration:
// - Routes MediaWiki logs to both local files AND Sentry
// - Provides structured context data for debugging
// - Filters logs appropriately by environment
//
// @see https://www.mediawiki.org/wiki/Manual:MonologSpi
// @see https://docs.sentry.io/platforms/php/guides/monolog/

// Only configure Monolog if Sentry is properly initialized
// This prevents errors if Sentry SDK is not available or misconfigured
// Environment variable to enable/disable Sentry logging integration
// Set to 'false' or '0' to disable Sentry handlers (logs will still go to files)
$sentryLoggingEnabled = ($_ENV['MW_SENTRY_LOGGING_ENABLED'] ?? 'true') === 'true' ||
    $_ENV['MW_SENTRY_LOGGING_ENABLED'] === '1';

// Helper function to get Sentry log level from environment variables
// Supports: TRACE, DEBUG, INFO, WARN, ERROR, FATAL
function getSentryLogLevel($envVar, $isProduction = false)
{
    $level = strtoupper($_ENV[$envVar] ?? '');

    switch ($level) {
        case 'TRACE':
            return \Sentry\Logs\LogLevel::trace();
        case 'DEBUG':
            return \Sentry\Logs\LogLevel::debug();
        case 'INFO':
            return \Sentry\Logs\LogLevel::info();
        case 'WARN':
        case 'WARNING':
            return \Sentry\Logs\LogLevel::warn();
        case 'ERROR':
            return \Sentry\Logs\LogLevel::error();
        case 'FATAL':
            return \Sentry\Logs\LogLevel::fatal();
        default:
            // Default behavior: Production = WARN, Development = INFO
            return $isProduction ? \Sentry\Logs\LogLevel::warn() : \Sentry\Logs\LogLevel::info();
    }
}

// Helper function to get Monolog log level from environment variables
// Supports: DEBUG, INFO, NOTICE, WARNING, ERROR, CRITICAL, ALERT, EMERGENCY
function getMonologLogLevel($envVar, $defaultLevel = 200)
{
    $level = strtoupper($_ENV[$envVar] ?? '');

    if (defined('\Monolog\Logger::' . $level)) {
        return constant('\Monolog\Logger::' . $level);
    }

    // RFC 5424 numeric levels as fallback
    switch ($level) {
        case 'DEBUG':
            return defined('\Monolog\Logger::DEBUG') ? \Monolog\Logger::DEBUG : 100;
        case 'INFO':
            return defined('\Monolog\Logger::INFO') ? \Monolog\Logger::INFO : 200;
        case 'NOTICE':
            return defined('\Monolog\Logger::NOTICE') ? \Monolog\Logger::NOTICE : 250;
        case 'WARNING':
        case 'WARN':
            return defined('\Monolog\Logger::WARNING') ? \Monolog\Logger::WARNING : 300;
        case 'ERROR':
            return defined('\Monolog\Logger::ERROR') ? \Monolog\Logger::ERROR : 400;
        case 'CRITICAL':
            return defined('\Monolog\Logger::CRITICAL') ? \Monolog\Logger::CRITICAL : 500;
        case 'ALERT':
            return defined('\Monolog\Logger::ALERT') ? \Monolog\Logger::ALERT : 550;
        case 'EMERGENCY':
            return defined('\Monolog\Logger::EMERGENCY') ? \Monolog\Logger::EMERGENCY : 600;
        default:
            return $defaultLevel;
    }
}

if (
    $sentryLoggingEnabled &&
    class_exists('\Sentry\SentrySdk') &&
    \Sentry\SentrySdk::getCurrentHub()->getClient() !== null &&
    class_exists('\Sentry\Monolog\LogsHandler') &&
    class_exists('\Sentry\Logs\LogLevel')
) {
    // Determine environment for appropriate log filtering
    $isProduction = ($_ENV['ENVIRONMENT'] ?? 'development') === 'production';

    // Configure MediaWiki's MonologSpi to use Sentry handlers
    // This routes MediaWiki's internal logging (wfDebugLog, exceptions, etc.) to Sentry
    //
    // MonologSpi Configuration Structure:
    // - loggers: Define channels and their handler routing
    // - processors: Add context data to all log records
    // - handlers: Define where logs go (files, Sentry, etc.)
    // - formatters: Control log message formatting
    $wgMWLoggerDefaultSpi = [
        'class' => \MediaWiki\Logger\MonologSpi::class,
        'args' => [
            [
                // Logger Channels: Route different types of logs
                // Each channel can have different handlers and processors
                'loggers' => [
                    // Default channel: General MediaWiki logging
                    // Used by wfDebugLog() and most PSR-3 logging calls
                    '@default' => [
                        // Processors add contextual information to log events
                        // wiki: MediaWiki-specific context (user, title, etc.)
                        // psr: PSR-3 message processing (placeholders)
                        // context: Additional context data
                        // web: HTTP request data (URL, headers)
                        // memory: Memory usage tracking
                        // process: Process ID and hostname
                        // uid: Unique request ID for correlation
                        'processors' => ['wiki', 'psr', 'context', 'web', 'memory', 'process', 'uid'],
                        // Handlers determine where logs go (both local file and Sentry)
                        // sentry-logs is conditionally added if LogsHandler is available
                        'handlers' => ['stream', 'sentry-logs', 'sentry-breadcrumbs'],
                    ],

                    // Exception channel: PHP exceptions and fatal errors
                    // These are sent as Sentry ERROR events (highest priority)
                    'exception' => [
                        'processors' => ['wiki', 'psr', 'context', 'web', 'memory', 'process', 'uid'],
                        // Separate file + Sentry events (not logs)
                        'handlers' => ['stream-exception', 'sentry-events'],
                    ],

                    // Database error channel: DB connection/query failures
                    // Critical for monitoring database health
                    'dberror' => [
                        'processors' => ['wiki', 'psr', 'context', 'web', 'memory', 'process', 'uid'],
                        // Separate file + Sentry events for high visibility
                        'handlers' => ['stream-dberror', 'sentry-events'],
                    ],

                    // ResourceLoader channel: JavaScript/CSS loading errors
                    // Important for frontend debugging and performance
                    'resourceloader' => [
                        'processors' => ['wiki', 'psr', 'context', 'web', 'memory', 'process'],
                        // General file + Sentry logs (not events)
                        'handlers' => ['stream', 'sentry-logs', 'sentry-breadcrumbs'],
                    ],

                    // Rate limit channel: Rate limiting events and blocks
                    // Security monitoring - when users hit limits
                    'ratelimit' => [
                        'processors' => ['wiki', 'psr', 'context', 'web', 'memory', 'process'],
                        // General file + Sentry logs for analysis
                        'handlers' => ['stream', 'sentry-logs', 'sentry-breadcrumbs'],
                    ],
                ],

                // Context Processors: Add metadata to LogRecord.extra (not context!)
                // Processors are callables that receive & modify LogRecord objects
                // Signature: function(LogRecord $record): LogRecord - MUST return the modified record
                // CRITICAL: Processors write to 'extra', not 'context' to avoid overriding user data
                // Processors run in order: wiki → psr → context → web → memory → process → uid
                // See: https://github.com/Seldaek/monolog/blob/main/doc/02-handlers-formatters-processors.md#processors
                'processors' => [
                    // MediaWiki WikiProcessor: Adds user, title, request ID to extra
                    // Essential for MediaWiki-specific debugging context
                    'wiki' => [
                        'class' => \MediaWiki\Logger\Monolog\WikiProcessor::class,
                    ],
                    // PSR-3 PsrLogMessageProcessor: Handles {placeholder} interpolation from context
                    // Replaces {key} with context['key'] values in message string
                    'psr' => [
                        'class' => \Monolog\Processor\PsrLogMessageProcessor::class,
                    ],
                    // MediaWiki ContextProcessor: Additional MW context to extra
                    // Complements WikiProcessor with more MW-specific data
                    'context' => [
                        'class' => \MediaWiki\Logger\Monolog\ContextProcessor::class,
                    ],

                    // Monolog Built-in Processors: Add system context to extra
                    // WebProcessor: URL, headers, method, IP address → extra
                    'web' => [
                        'class' => \Monolog\Processor\WebProcessor::class,
                    ],
                    // MemoryProcessor: Current/peak memory usage → extra
                    'memory' => [
                        'class' => \Monolog\Processor\MemoryUsageProcessor::class,
                    ],
                    // ProcessIdProcessor: PID and hostname → extra
                    'process' => [
                        'class' => \Monolog\Processor\ProcessIdProcessor::class,
                    ],
                    // UidProcessor: Unique request ID for correlation → extra
                    'uid' => [
                        'class' => \Monolog\Processor\UidProcessor::class,
                    ],
                ],

                // Log Handlers: Define where logs are sent (Monolog Handler Stack)
                // Each handler processes records, handlers are called in order until one doesn't bubble
                // Handler Types Used: StreamHandler (files), Sentry handlers (Monolog integration)
                // See: https://github.com/Seldaek/monolog/blob/main/doc/02-handlers-formatters-processors.md#handlers
                'handlers' => [
                    // StreamHandler: Logs to files (core Monolog handler)
                    // General purpose logging to disk for all log levels
                    // Keeps complete audit trail for compliance and debugging
                    'stream' => [
                        'class' => \Monolog\Handler\StreamHandler::class,
                        'args' => ['/var/log/mediawiki/monolog.log'],
                        'formatter' => 'line', // Uses LineFormatter for readable text logs
                    ],

                    // Exception-specific StreamHandler: Isolated exception logging
                    // Separate file for PHP exceptions and fatal errors
                    // Easier monitoring and alerting on application crashes
                    'stream-exception' => [
                        'class' => \Monolog\Handler\StreamHandler::class,
                        'args' => ['/var/log/mediawiki/exception.log'],
                        'formatter' => 'line',
                    ],

                    // Database error StreamHandler: Isolated DB error logging
                    // Critical for database performance monitoring and troubleshooting
                    // Separate from general exceptions for focused analysis
                    'stream-dberror' => [
                        'class' => \Monolog\Handler\StreamHandler::class,
                        'args' => ['/var/log/mediawiki/dberror.log'],
                        'formatter' => 'line',
                    ],

                    // Sentry\Monolog\LogsHandler: Structured logs to Sentry Logs
                    // Sends log entries to Sentry's Logs feature (SDK 4.12.0+)
                    // Requires 'enable_logs => true' in Sentry init (now enabled)
                    // Configurable log level via environment variables
                    // Searchable/filterable logs in Sentry dashboard
                    'sentry-logs' => [
                        'class' => \Sentry\Monolog\LogsHandler::class,
                        'args' => [
                            // Configurable log level - defaults to environment-aware behavior
                            $this->getSentryLogLevel('MW_SENTRY_LOGS_LEVEL', $isProduction),
                        ],
                    ],

                    // Sentry\Monolog\Handler: Error events to Sentry Issues
                    // Creates actual error events in Sentry for alerting/monitoring
                    // Used for high-priority errors: exceptions, DB errors, crashes
                    // bubble=true allows other handlers to also process these records
                    'sentry-events' => [
                        'class' => \Sentry\Monolog\Handler::class,
                        'args' => [
                            \Sentry\SentrySdk::getCurrentHub(),
                            $this->getMonologLogLevel('MW_SENTRY_EVENTS_LEVEL', defined('\Monolog\Logger::ERROR') ? \Monolog\Logger::ERROR : 400),
                            true, // bubble: Allow other handlers to process (no stopping propagation)
                            true, // fillExtraContext: Include Monolog context in Sentry events
                        ],
                    ],

                    // Sentry\Monolog\BreadcrumbHandler: Context for future errors
                    // Records recent logs as breadcrumbs in Sentry error reports
                    // Provides debugging context leading up to errors/crashes
                    // Configurable level to control breadcrumb verbosity
                    'sentry-breadcrumbs' => [
                        'class' => \Sentry\Monolog\BreadcrumbHandler::class,
                        'args' => [
                            \Sentry\SentrySdk::getCurrentHub(),
                            $this->getMonologLogLevel('MW_SENTRY_BREADCRUMBS_LEVEL', defined('\Monolog\Logger::INFO') ? \Monolog\Logger::INFO : 200),
                        ],
                    ],
                ],

                // Formatters: Control how log records are formatted for output
                // Each handler uses a formatter to convert log records to strings
                // LineFormatter: Standard human-readable single-line format
                // Supports placeholder interpolation (%datetime%, %level_name%, etc.)
                // See: https://github.com/Seldaek/monolog/blob/main/doc/02-handlers-formatters-processors.md#formatters
                'formatters' => [
                    'line' => [
                        'class' => \Monolog\Formatter\LineFormatter::class,
                        // Default format: "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n"
                        // Can be customized with format string and date format parameters
                    ],
                ],
            ]
        ],
    ];
}

//######################################################// PSR-3 Logging Usage Examples
//
// PSR-3 Logger Usage:
// Get a logger instance for your component
// $logger = \MediaWiki\Logger\LoggerFactory::getInstance('MyExtension');
//
// Log with different severity levels
// $logger->emergency('System is down', ['component' => 'database']);
// $logger->alert('Immediate action required', ['user' => 'admin']);
// $logger->critical('Critical system component failed');
// $logger->error('Operation failed', ['exception' => $e]);
// $logger->warning('Deprecated API usage', ['api' => 'old_method']);
// $logger->notice('Significant system event');
// $logger->info('User logged in', ['user_id' => 123, 'ip' => '192.168.1.1']);
// $logger->debug('Processing step completed', ['step' => 'validation']);
//
// Context Array Best Practices:
// - Include relevant IDs: user_id, page_id, request_id
// - Add timing data: duration, timestamp
// - Include request context: url, method, user_agent
// - Attach exceptions: ['exception' => $e]
// - Use placeholders: $logger->info('User {user} updated {count} records', ['user' => 'john', 'count' => 5]);
//
// Legacy MediaWiki Logging (still supported):
// wfDebugLog('mychannel', 'Log message');                    // Maps to PSR-3 info level
// wfDebugLog('mychannel', 'Log message', 'private', $context); // Includes context array

//######################################################// Security Considerations
//
// DEBUG FEATURES SECURITY WARNING:
// The debug features below are ENABLED only in development environments.
// NEVER enable them in production as they can expose sensitive information:
//
// - $wgShowExceptionDetails: Reveals stack traces with file paths
// - $wgDebugToolbar: Shows internal performance and debug data
// - $wgShowDebug: Adds raw log messages to HTML output
// - $wgDebugComments: Embeds debug data in HTML comments
// - $wgDebugDumpSql: Logs all SQL queries (may include sensitive data)
// - $wgResourceLoaderDebug: Enables ResourceLoader debug mode
//
// Debug log files themselves contain private data:
// - Session cookies and authentication tokens
// - User input and form data
// - Database connection details
// - JavaScript errors and client-side data
// - ResourceLoader module loading information
// - NEVER make log files publicly accessible!
//
// @see https://www.mediawiki.org/wiki/Manual:How_to_debug#Setting_up_a_debug_log_file

//######################################################// Memory Management & Long-Running Processes
//
// MediaWiki may run background jobs, maintenance scripts, or long-running processes.
// Monolog handlers can accumulate memory over time - proper cleanup is essential.
//
// Memory Management Best Practices:
// - Call $logger->reset() between background jobs to clear buffers
// - Use FingersCrossedHandler with caution in long-running processes
// - BufferHandler should be flushed regularly
// - Sentry logs are automatically flushed via BeforePageDisplay hook in 90-Sentry.php
// - Monitor memory usage in production environments
//
// See: https://github.com/Seldaek/monolog/blob/main/doc/01-usage.md#long-running-processes-and-avoiding-memory-leaks

// Helper function to log to Sentry via Monolog
// Provides a simple interface for basic logging needs
// Usage: sentry_log('error', 'Something went wrong', ['context' => 'value']);
if (!function_exists('sentry_log')) {
    /**
     * Simple logging helper function using PSR-3 LoggerInterface
     *
     * This function provides a convenient way to log messages with proper
     * PSR-3 compliance and Monolog integration.
     *
     * @param string $level PSR-3 log level (emergency, alert, critical, error, warning, notice, info, debug)
     * @param string $message Log message (may contain {placeholders})
     * @param array $context Additional context data for structured logging
     * @return void
     *
     * @example
     * sentry_log('error', 'Database connection failed', [
     *     'host' => 'db.example.com',
     *     'user' => 'webuser',
     *     'exception' => $e
     * ]);
     */
    function sentry_log(string $level, string $message, array $context = []): void
    {
        global $wgSentryMonologLogger;
        if (isset($wgSentryMonologLogger) && $wgSentryMonologLogger instanceof \Monolog\Logger) {
            try {
                // Convert level name to Monolog level (works with both 2.x and 3.x)
                $levelConstant = strtoupper($level);
                if (defined("\Monolog\Logger::$levelConstant")) {
                    // Monolog 2.x - use constant
                    $monologLevel = constant("\Monolog\Logger::$levelConstant");
                } elseif (class_exists('\Monolog\Level') && method_exists('\Monolog\Level', 'fromName')) {
                    // Monolog 3.x - use Level enum
                    $monologLevel = \Monolog\Level::fromName($level);
                } else {
                    // Fallback to error level
                    $monologLevel = defined('\Monolog\Logger::ERROR')
                        ? \Monolog\Logger::ERROR
                        : 400;
                }

                $wgSentryMonologLogger->log($monologLevel, $message, $context);
            } catch (\Throwable $e) {
                // Don't break application if logging fails
                error_log('Sentry Monolog logging failed: ' . $e->getMessage());
            }
        }
    }
}
