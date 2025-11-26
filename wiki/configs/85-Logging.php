<?php
/**
 * MediaWiki Logging Configuration
 *
 * PSR-3 compliant structured logging with Monolog and Sentry integration.
 * Preserves context data (unlike MediaWiki's default logger) and routes logs to files and Sentry.
 *
 * Features:
 * - PSR-3 LoggerInterface with 8 RFC 5424 log levels
 * - Structured context arrays with placeholder interpolation ({key})
 * - Monolog channels for component-specific logging
 * - Environment-aware filtering (dev vs production)
 * - Sentry integration for error tracking and searchable logs
 * - MediaWiki debug features (toolbar, exception details, SQL logging)
 *
 * LogRecord Structure:
 * - message: Interpolated log text
 * - level: RFC 5424 severity (DEBUG=100, INFO=200, etc.)
 * - context: User-provided data (preserved, used for interpolation)
 * - extra: System/processor-added data (memory, PID, etc.)
 *
 * @see https://www.mediawiki.org/wiki/Manual:Structured_logging
 * @see https://docs.sentry.io/platforms/php/guides/monolog/
 *
 * @category Configuration
 * @package  ATL-Wiki
 * @author   Kaizen <kaizen@allthingslinux.org>
 * @license  https://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 * @link     https://atl.wiki
 */

// ============================================================================
// PHP Error Reporting
// ============================================================================

error_reporting(-1);

ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);

// Preserve stack trace arguments for Sentry (PHP 7.4+ defaults to ignoring them)
if (version_compare(PHP_VERSION, '7.4.0', '>=')) {
    ini_set('zend.exception_ignore_args', 0);
}

// ============================================================================
// MediaWiki Debug Configuration
// ============================================================================

/**
 * Get debug setting from environment variable with safe defaults
 *
 * Returns true if env var is 'true' or '1', false if 'false' or '0'.
 * If unset and $defaultToDev is true, returns true for development environment.
 *
 * @param string $envVar Environment variable name
 * @param bool $defaultToDev Default to true in development if env var not set
 * @return bool Debug setting value
 */
function getDebugSetting(string $envVar, bool $defaultToDev = true): bool
{
    $value = isset($_ENV[$envVar]) ? $_ENV[$envVar] : null;
    if ($value === 'true' || $value === '1') {
        return true;
    }
    if ($value === 'false' || $value === '0') {
        return false;
    }
    $environment = $_ENV['ENVIRONMENT'] ?? 'development';
    return $defaultToDev && ($environment === 'development');
}

$wgShowExceptionDetails = getDebugSetting('MW_DEBUG_EXCEPTION_DETAILS');
$wgDebugToolbar = getDebugSetting('MW_DEBUG_TOOLBAR');
$wgShowDebug = getDebugSetting('MW_DEBUG_SHOW_LOGS');
$wgDevelopmentWarnings = getDebugSetting('MW_DEBUG_DEVELOPMENT_WARNINGS');
$wgDebugDumpSql = getDebugSetting('MW_DEBUG_DUMP_SQL');
$wgDebugComments = false; // Never enable in production

// ============================================================================
// ResourceLoader Debug Configuration
// ============================================================================

$wgResourceLoaderDebug = getDebugSetting('MW_DEBUG_RESOURCE_LOADER');

// ============================================================================
// File-based Logging Configuration
// ============================================================================
// Security: Log files contain sensitive data - never make them publicly accessible!

$wgDebugLogFile = $_ENV['MW_DEBUG_LOG_FILE'] ?? '/var/log/mediawiki/debug.log';

$wgDBerrorLog = $_ENV['MW_DEBUG_DB_ERROR_LOG_FILE'] ?? '/var/log/mediawiki/dberror.log';

$wgDebugLogGroups = [
    'exception' => $_ENV['MW_EXCEPTION_LOG_FILE'] ?? '/var/log/mediawiki/exception.log',
    'dberror' => $_ENV['MW_DBERROR_LOG_FILE'] ?? '/var/log/mediawiki/dberror.log',
    'resourceloader' => $_ENV['MW_RESOURCELOADER_LOG_FILE'] ?? '/var/log/mediawiki/resourceloader.log',
    'ratelimit' => $_ENV['MW_RATELIMIT_LOG_FILE'] ?? '/var/log/mediawiki/ratelimit.log',
];

// ============================================================================
// Monolog Integration for Sentry
// ============================================================================
// Monolog preserves context data (unlike MediaWiki's default logger)

$sentryLoggingEnabled = ($_ENV['MW_SENTRY_LOGGING_ENABLED'] ?? 'true') === 'true' ||
    $_ENV['MW_SENTRY_LOGGING_ENABLED'] === '1';

/**
 * Get Sentry log level from environment variable
 *
 * Supports: TRACE, DEBUG, INFO, WARN, ERROR, FATAL
 * Defaults to WARN in production, INFO in development if env var not set.
 *
 * @param string $envVar Environment variable name
 * @param bool $isProduction Whether running in production environment
 * @return \Sentry\Logs\LogLevel Sentry log level instance
 */
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
            return $isProduction ? \Sentry\Logs\LogLevel::warn() : \Sentry\Logs\LogLevel::info();
    }
}

/**
 * Get Monolog log level from environment variable
 *
 * Supports: DEBUG, INFO, NOTICE, WARNING, ERROR, CRITICAL, ALERT, EMERGENCY
 * Falls back to RFC 5424 numeric levels if constants not defined.
 *
 * @param string $envVar Environment variable name
 * @param int $defaultLevel Default numeric level (RFC 5424) if env var not set
 * @return int Monolog log level constant or numeric value
 */
function getMonologLogLevel($envVar, $defaultLevel = 200)
{
    $level = strtoupper($_ENV[$envVar] ?? '');

    if (defined('\Monolog\Logger::' . $level)) {
        return constant('\Monolog\Logger::' . $level);
    }

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
    $isProduction = ($_ENV['ENVIRONMENT'] ?? 'development') === 'production';

    $wgMWLoggerDefaultSpi = [
        'class' => \MediaWiki\Logger\MonologSpi::class,
        'args' => [
            [
                'loggers' => [
                    '@default' => [
                        'processors' => ['wiki', 'psr', 'context', 'web', 'memory', 'process', 'uid'],
                        'handlers' => ['stream', 'sentry-logs', 'sentry-breadcrumbs'],
                    ],
                    'exception' => [
                        'processors' => ['wiki', 'psr', 'context', 'web', 'memory', 'process', 'uid'],
                        'handlers' => ['stream-exception', 'sentry-events'],
                    ],
                    'dberror' => [
                        'processors' => ['wiki', 'psr', 'context', 'web', 'memory', 'process', 'uid'],
                        'handlers' => ['stream-dberror', 'sentry-events'],
                    ],
                    'resourceloader' => [
                        'processors' => ['wiki', 'psr', 'context', 'web', 'memory', 'process'],
                        'handlers' => ['stream', 'sentry-logs', 'sentry-breadcrumbs'],
                    ],
                    'ratelimit' => [
                        'processors' => ['wiki', 'psr', 'context', 'web', 'memory', 'process'],
                        'handlers' => ['stream', 'sentry-logs', 'sentry-breadcrumbs'],
                    ],
                ],

                // Processors add system data to LogRecord.extra (not context!)
                'processors' => [
                    'wiki' => ['class' => \MediaWiki\Logger\Monolog\WikiProcessor::class],
                    'psr' => ['class' => \Monolog\Processor\PsrLogMessageProcessor::class],
                    'context' => ['class' => \MediaWiki\Logger\Monolog\ContextProcessor::class],
                    'web' => ['class' => \Monolog\Processor\WebProcessor::class],
                    'memory' => ['class' => \Monolog\Processor\MemoryUsageProcessor::class],
                    'process' => ['class' => \Monolog\Processor\ProcessIdProcessor::class],
                    'uid' => ['class' => \Monolog\Processor\UidProcessor::class],
                ],

                'handlers' => [
                    'stream' => [
                        'class' => \Monolog\Handler\StreamHandler::class,
                        'args' => ['/var/log/mediawiki/monolog.log'],
                        'formatter' => 'line',
                    ],
                    'stream-exception' => [
                        'class' => \Monolog\Handler\StreamHandler::class,
                        'args' => ['/var/log/mediawiki/exception.log'],
                        'formatter' => 'line',
                    ],
                    'stream-dberror' => [
                        'class' => \Monolog\Handler\StreamHandler::class,
                        'args' => ['/var/log/mediawiki/dberror.log'],
                        'formatter' => 'line',
                    ],
                    'sentry-logs' => [
                        'class' => \Sentry\Monolog\LogsHandler::class,
                        'args' => [getSentryLogLevel('MW_SENTRY_LOGS_LEVEL', $isProduction)],
                    ],
                    'sentry-events' => [
                        'class' => \Sentry\Monolog\Handler::class,
                        'args' => [
                            \Sentry\SentrySdk::getCurrentHub(),
                            getMonologLogLevel('MW_SENTRY_EVENTS_LEVEL', defined('\Monolog\Logger::ERROR') ? \Monolog\Logger::ERROR : 400),
                            true, // bubble
                            true, // fillExtraContext
                        ],
                    ],
                    'sentry-breadcrumbs' => [
                        'class' => \Sentry\Monolog\BreadcrumbHandler::class,
                        'args' => [
                            \Sentry\SentrySdk::getCurrentHub(),
                            getMonologLogLevel('MW_SENTRY_BREADCRUMBS_LEVEL', defined('\Monolog\Logger::INFO') ? \Monolog\Logger::INFO : 200),
                        ],
                    ],
                ],

                'formatters' => [
                    'line' => ['class' => \Monolog\Formatter\LineFormatter::class],
                ],
            ]
        ],
    ];
}

// ============================================================================
// Helper Functions
// ============================================================================

if (!function_exists('sentry_log')) {
    /**
     * Log to Sentry via Monolog
     *
     * Convenience function for logging with PSR-3 compliance and Monolog integration.
     * Supports both Monolog 2.x (constants) and 3.x (Level enum).
     *
     * @param string $level PSR-3 log level (emergency, alert, critical, error, warning, notice, info, debug)
     * @param string $message Log message (may contain {placeholders} for context interpolation)
     * @param array $context Additional context data for structured logging
     * @return void
     *
     * @example
     * sentry_log('error', 'Database connection failed', [
     *     'host' => 'db.example.com',
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
