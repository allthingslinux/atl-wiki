<?php
/**
 * Sentry Error Tracking & Performance Monitoring Configuration
 *
 * Configures Sentry SDK for error tracking, performance monitoring, and structured logging.
 * Integrates with 85-Logging.php for complete observability via Monolog handlers.
 *
 * Features:
 * - PHP error & exception tracking
 * - Performance tracing & profiling (Excimer extension)
 * - JavaScript error tracking (ResourceLoader)
 * - User context, breadcrumbs, and structured tags
 * - PII scrubbing and GDPR-compliant data handling
 * - Distributed tracing (frontend-to-backend)
 * - Security policy reporting (CSP/CT violations)
 *
 * @see https://docs.sentry.io/platforms/php/
 * @see wiki/configs/85-Logging.php
 * @see wiki/modules/ext.Sentry/init.js
 *
 * @category Configuration
 * @package  ATL-Wiki
 * @author   Kaizen <kaizen@allthingslinux.org>
 * @license  https://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 * @link     https://atl.wiki
 */

// ============================================================================
// Sentry SDK Initialization
// ============================================================================
if (!empty($_ENV['SENTRY_DSN'])) {
    // Configure SDK debug logging (optional)
    $sentryLogger = match ($_ENV['SENTRY_DEBUG_LOGGER'] ?? '') {
        'stdout' => new \Sentry\Logger\DebugStdOutLogger(),
        'file' => new \Sentry\Logger\DebugFileLogger('/var/log/mediawiki/sentry-sdk.log'),
        default => null,
    };

    $sentryConfig = [
        'dsn' => $_ENV['SENTRY_DSN'],
        'release' => $_ENV['SENTRY_RELEASE'] ?? null,
        'environment' => $_ENV['ENVIRONMENT'] ?? 'development',
        'enable_logs' => true, // Enable Sentry Logs for searchable log entries
    ];

    if ($sentryLogger !== null) {
        $sentryConfig['logger'] = $sentryLogger;
    }

    \Sentry\init(array_merge($sentryConfig, [

        // Filter logs before sending (reduce production noise)
        'before_send_log' => function (\Sentry\Logs\Log $log): ?\Sentry\Logs\Log {
            if (($_ENV['ENVIRONMENT'] ?? 'development') === 'production') {
                if ($log->getLevel() === \Sentry\Logs\LogLevel::debug()) {
                    return null;
                }
                if ($log->getLevel() === \Sentry\Logs\LogLevel::info()) {
                    return null; // Remove this line to keep info logs
                }
            }
            return $log;
        },

        // Sampling rates
        'traces_sample_rate' => (float) ($_ENV['SENTRY_TRACES_SAMPLE_RATE'] ?? 0.5),
        'profiles_sample_rate' => (float) ($_ENV['SENTRY_PROFILES_SAMPLE_RATE'] ?? 0.5), // Requires Excimer extension
        'sample_rate' => 1.0, // Always capture errors

        'error_types' => E_ALL,
        'attach_stacktrace' => true,
        'send_default_pii' => true, // PII scrubbed via before_send
        'max_breadcrumbs' => 50,
        'max_request_body_size' => 'medium', // ~10KB limit
        'context_lines' => 8,

        // Mark MediaWiki paths as "in app" for better stack traces
        'in_app_include' => [
            '/var/www/wiki/mediawiki/includes/',
            '/var/www/wiki/mediawiki/extensions/',
            '/var/www/wiki/mediawiki/skins/',
            '/var/www/wiki/mediawiki/maintenance/',
            '/var/www/wiki/configs/',
            '/var/www/wiki/modules/',
        ],

        'server_name' => gethostname(),
        'prefixes' => [
            '/var/www/html/',
            '/var/www/wiki/',
        ],

        // Ignore expected/non-critical exceptions
        'ignore_exceptions' => [
            'MediaWiki\\Permissions\\PermissionDeniedError',
            'MediaWiki\\User\\UserNotLoggedIn',
            'MediaWiki\\Api\\ApiUsageException',
        ],

        // Scrub sensitive data before sending events
        'before_send' => function (\Sentry\Event $event): ?\Sentry\Event {
            // Recursively scrub sensitive keys from arrays
            $scrubArrayRecursively = function ($array, $sensitiveKeys) use (&$scrubArrayRecursively) {
                if (!is_array($array)) {
                    return $array;
                }

                $result = [];
                foreach ($array as $key => $value) {
                    if (in_array(strtolower($key), $sensitiveKeys)) {
                        continue;
                    }
                    $result[$key] = is_array($value) ? $scrubArrayRecursively($value, $sensitiveKeys) : $value;
                }
                return $result;
            };

            // Scrub sensitive data from various event sources
            if (isset($event->getExtra()['server_vars']['_ENV'])) {
                unset($event->getExtra()['server_vars']['_ENV']);
            }

            // Cookies
            if (isset($event->getRequest()['cookies'])) {
                $cookies = $event->getRequest()['cookies'];
                foreach (['session', 'token', 'auth', 'login', 'password', 'csrf'] as $name) {
                    unset($cookies[$name]);
                }
                $event->getRequest()['cookies'] = $cookies;
            }

            // Headers
            if (isset($event->getRequest()['headers'])) {
                $headers = $event->getRequest()['headers'];
                unset($headers['authorization'], $headers['x-api-key'], $headers['x-auth-token']);
                if (isset($headers['x-forwarded-for'])) {
                    $headers['x-forwarded-for'] = trim(explode(',', $headers['x-forwarded-for'])[0]);
                }
                $event->getRequest()['headers'] = $headers;
            }

            // Query string
            if (isset($event->getRequest()['query_string'])) {
                parse_str($event->getRequest()['query_string'], $parsedQuery);
                foreach (['password', 'token', 'key', 'secret', 'api_key'] as $param) {
                    unset($parsedQuery[$param]);
                }
                $event->getRequest()['query_string'] = http_build_query($parsedQuery);
            }

            // POST data
            if (isset($event->getRequest()['data']) && is_array($event->getRequest()['data'])) {
                $event->getRequest()['data'] = $scrubArrayRecursively(
                    $event->getRequest()['data'],
                    ['password', 'token', 'secret', 'key', 'auth', 'session', 'csrf']
                );
            }

            // User context
            if ($event->getUser()) {
                $user = $event->getUser();
                unset($user['password'], $user['token'], $user['secret']);
                $event->setUser($user);
            }

            // Transaction names (parameterize IDs)
            $transaction = $event->getTransaction();
            if ($transaction) {
                $transaction = preg_replace('/\/users\/\d+\//', '/users/:id/', $transaction);
                $transaction = preg_replace('/\/pages\/\d+\//', '/pages/:id/', $transaction);
                $event->setTransaction($transaction);
            }

            return $event;
        },

        // Filter breadcrumbs (skip noisy DB queries in production)
        'before_breadcrumb' => function (\Sentry\Breadcrumb $breadcrumb): ?\Sentry\Breadcrumb {
            if (
                $breadcrumb->getCategory() === 'db.query' &&
                ($_ENV['ENVIRONMENT'] ?? '') === 'production' &&
                strpos($breadcrumb->getMessage(), 'SELECT') === 0
            ) {
                return null;
            }
            return $breadcrumb;
        },

        // Skip tracing for high-frequency endpoints
        'ignore_transactions' => [
            '/api.php?action=opensearch',
            '/api.php?action=query&meta=userinfo',
            '/load.php*',
            '/api.php?action=parse',
            '/api.php?action=usercontribs',
            '/api.php?action=recentchanges',
            '/Special:RecentChanges',
            '/Special:Watchlist',
            '/api.php?action=query&list=watchlist',
            '/.+\.(css|js|png|jpg|jpeg|gif|svg|ico|woff|woff2)$',
        ],

        'trace_propagation_targets' => [
            '/^https?:\/\/.*\.atl\.wiki/',
            '/^https?:\/\/localhost(:\d+)?/',
        ],
    ]));
}

// ============================================================================
// ResourceLoader Module Registration
// ============================================================================

$wgResourceModules['ext.sentry'] = [
    'localBasePath' => dirname(__DIR__) . '/modules',
    'remoteExtPath' => 'ATL-Wiki/modules',
    'scripts' => [
        'ext.Sentry/init.js',
    ],
    'targets' => ['desktop', 'mobile'],
];

// ============================================================================
// MediaWiki Hooks
// ============================================================================

// Capture MediaWiki exceptions
$wgHooks['MWExceptionHandlerReport'][] = function ($e) {
    if (class_exists('\Sentry\SentrySdk')) {
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

// User authentication events
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

// Page edit events
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

// Set user context and inject JavaScript SDK
$wgHooks['BeforePageDisplay'][] = function ($out, $skin) {
    if (class_exists('\Sentry\SentrySdk')) {
        try {
            $user = $skin->getUser();
            $title = $out->getTitle();
            $request = $out->getRequest();

            // Continue distributed trace if headers present
            $sentryTraceHeader = $request->getHeader('sentry-trace');
            $baggageHeader = $request->getHeader('baggage');
            if (!empty($sentryTraceHeader) || !empty($baggageHeader)) {
                \Sentry\continueTrace($sentryTraceHeader, $baggageHeader);
            }

            // Add breadcrumbs for key events
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

            // Set comprehensive context and tags
            \Sentry\configureScope(function (\Sentry\State\Scope $scope) use ($user, $out, $title, $request): void {
                // User context
                if ($user && $user->isRegistered()) {
                    $scope->setUser([
                        'id' => (string) $user->getId(),
                        'username' => $user->getName(),
                        'email' => $user->getEmail() ?: null,
                        'ip_address' => $request->getIP(),
                    ]);
                } else {
                    $scope->setUser(['ip_address' => $request->getIP()]);
                }

                // Application contexts
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

                if ($user && $user->isRegistered()) {
                    $session = $request->getSession();
                    $scope->setContext('session', [
                        'session_id' => $session ? substr(session_id(), 0, 8) . '...' : null,
                        'user_groups' => $user->getGroups(),
                        'user_rights' => $user->getRights(),
                        'registration_date' => $user->getRegistration() ?: null,
                        'edit_count' => $user->getEditCount(),
                        'is_admin' => in_array('sysop', $user->getGroups()),
                    ]);
                }

                $scope->setContext('server', [
                    'hostname' => gethostname(),
                    'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? null,
                    'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? null,
                    'script_filename' => $_SERVER['SCRIPT_FILENAME'] ?? null,
                    'memory_limit' => ini_get('memory_limit'),
                    'max_execution_time' => ini_get('max_execution_time'),
                    'upload_max_filesize' => ini_get('upload_max_filesize'),
                ]);

                // Searchable tags (max 32 chars key, 200 chars value)
                $scope->setTag('mediawiki.version', $wgVersion ?? 'unknown');
                $scope->setTag('environment', $_ENV['ENVIRONMENT'] ?? 'unknown');
                $scope->setTag('php.version', PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION);

                if ($title) {
                    $scope->setTag('page.namespace', (string) $title->getNamespace());
                    $scope->setTag('page.action', $request->getVal('action', 'view'));
                    $scope->setTag('page.is_special', $title->isSpecialPage() ? 'true' : 'false');
                    $scope->setTag('page.is_main', $title->isMainPage() ? 'true' : 'false');
                }

                if ($user && $user->isRegistered()) {
                    $scope->setTag('user.authenticated', 'true');
                    $scope->setTag('user.has_groups', !empty($user->getGroups()) ? 'true' : 'false');
                    $scope->setTag('user.is_admin', in_array('sysop', $user->getGroups()) ? 'true' : 'false');
                    $editCount = $user->getEditCount();
                    $scope->setTag('user.edit_range', match (true) {
                        $editCount === 0 => '0',
                        $editCount < 10 => '1-9',
                        $editCount < 100 => '10-99',
                        $editCount < 1000 => '100-999',
                        default => '1000+',
                    });
                } else {
                    $scope->setTag('user.authenticated', 'false');
                }

                $scope->setTag('http.method', $request->getMethod());
                $scope->setTag('http.protocol', $request->getProtocol());
                $scope->setTag('server.hostname', gethostname());

                $memoryUsage = memory_get_peak_usage(true);
                $scope->setTag('memory.usage', match (true) {
                    $memoryUsage < 32 * 1024 * 1024 => 'low',
                    $memoryUsage < 128 * 1024 * 1024 => 'medium',
                    default => 'high',
                });
            });

            // Inject JavaScript SDK configuration
            $sentryDsn = $_ENV['SENTRY_DSN'] ?? null;
            $loaderKey = $_ENV['SENTRY_LOADER_KEY'] ?? null;
            if ($sentryDsn && $loaderKey) {
                $env = $_ENV['ENVIRONMENT'] ?? 'development';
                $release = $_ENV['SENTRY_RELEASE'] ?? null;
                $tracesSampleRate = (float) ($_ENV['SENTRY_TRACES_SAMPLE_RATE'] ?? 1.0);
                $lastEventId = \Sentry\SentrySdk::getCurrentHub()->getLastEventId();

                $out->addJsConfigVars([
                    'wgSentryConfig' => [
                        'environment' => $env,
                        'tracesSampleRate' => $tracesSampleRate,
                        'release' => $release,
                        'loaderKey' => $loaderKey,
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
                    'wgSentryCrashReport' => [
                        'enabled' => true,
                        'eventId' => $lastEventId,
                        'showDialogOnError' => true,
                        'title' => 'Help us fix this issue',
                        'subtitle' => 'We\'ve detected an error. Your feedback helps us improve.',
                        'subtitle2' => 'If you\'d like to help, tell us what happened below.',
                        'labelName' => 'Name',
                        'labelEmail' => 'Email',
                        'labelComments' => 'What happened?',
                        'labelClose' => 'Close',
                        'labelSubmit' => 'Submit',
                        'errorGeneric' => 'An unknown error occurred while submitting your report. Please try again.',
                        'errorFormEntry' => 'Some fields were invalid. Please correct the errors and try again.',
                        'successMessage' => 'Your feedback has been sent. Thank you!',
                        'user' => $user && $user->isRegistered() ? [
                            'name' => $user->getName(),
                            'email' => $user->getEmail() ?: null,
                        ] : null,
                    ],
                ]);

                // Inject trace propagation meta tags
                $out->addHeadItem('sentry-trace-meta', sprintf(
                    '<meta name="sentry-trace" content="%s"/>',
                    htmlspecialchars(\Sentry\getTraceparent() ?? '', ENT_QUOTES, 'UTF-8')
                ));
                $out->addHeadItem('sentry-baggage-meta', sprintf(
                    '<meta name="baggage" content="%s"/>',
                    htmlspecialchars(\Sentry\getBaggage() ?? '', ENT_QUOTES, 'UTF-8')
                ));

                $out->addModules(['ext.sentry']);
            }

            // Flush logs at end of request
            \Sentry\logger()->flush();
        } catch (\Throwable $e) {
            error_log('Sentry hook failed: ' . $e->getMessage());
        }
    }
    return true;
};

// ============================================================================
// Sentry Tunnel Endpoint (Ad-blocker Bypass)
// ============================================================================

$wgHooks['ApiBeforeMain'][] = function (&$processor) {
    if ($processor->getRequest()->getVal('action') === 'sentry-tunnel') {
        try {
            $dsn = $_ENV['SENTRY_DSN'] ?? null;
            if (!$dsn) {
                http_response_code(400);
                echo json_encode(['error' => 'Sentry DSN not configured']);
                exit;
            }

            $dsnParts = parse_url($dsn);
            if (!$dsnParts || !isset($dsnParts['scheme'], $dsnParts['host'], $dsnParts['path'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid Sentry DSN']);
                exit;
            }

            $host = $dsnParts['scheme'] . '://' . $dsnParts['host'];
            $pathParts = explode('/', trim($dsnParts['path'], '/'));
            $projectId = end($pathParts);
            $userParts = explode('@', $dsnParts['user'] ?? '');
            $publicKey = $userParts[0] ?? '';

            if (!$projectId || !$publicKey) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid Sentry DSN format']);
                exit;
            }

            $envelopeData = file_get_contents('php://input');
            if (!$envelopeData) {
                http_response_code(400);
                echo json_encode(['error' => 'No envelope data received']);
                exit;
            }

            $sentryUrl = "{$host}/api/{$projectId}/envelope/";
            $result = file_get_contents($sentryUrl, false, stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => 'Content-Type: application/x-sentry-envelope',
                    'content' => $envelopeData,
                    'timeout' => 5,
                ]
            ]));

            if ($result === false) {
                http_response_code(502);
                echo json_encode(['error' => 'Failed to forward to Sentry']);
                exit;
            }

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

// ============================================================================
// Security Policy Reporting (CSP/CT Violations)
// ============================================================================

$wgCSPHeader = false;
$wgHooks['SecurityResponseHeader'][] = function (&$headers, $name) {
    $sentryDsn = $_ENV['SENTRY_DSN'] ?? null;
    $environment = $_ENV['ENVIRONMENT'] ?? 'development';
    $release = $_ENV['SENTRY_RELEASE'] ?? null;

    if ($name === 'Content-Security-Policy') {
        $cspParts = [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' *.sentry.io js.sentry-cdn.com browser.sentry-cdn.com",
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data: *.sentry.io sentry.io",
            "connect-src 'self' *.sentry.io sentry.io",
            "frame-src 'self' *.sentry.io sentry.io",
            "font-src 'self' data:",
            "media-src 'self'",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'"
        ];

        if ($sentryDsn) {
            $securityUrl = getSentrySecurityUrl($sentryDsn, $environment, $release);
            if ($securityUrl) {
                $cspParts[] = "report-uri {$securityUrl}";
                $cspParts[] = "report-to csp-endpoint";
                $headers[] = "Report-To: {\"group\":\"csp-endpoint\",\"max_age\":10886400,\"endpoints\":[{\"url\":\"{$securityUrl}\"}],\"include_subdomains\":true}";
                $headers[] = "Reporting-Endpoints: csp-endpoint=\"{$securityUrl}\"";
                $headers[] = "Expect-CT: report-uri=\"{$securityUrl}\", max-age=86400, enforce";
            }
        }

        $headers[] = implode('; ', $cspParts);
    }
    return true;
};

function getSentrySecurityUrl($dsn, $environment = null, $release = null)
{
    $dsnParts = parse_url($dsn);
    if (!$dsnParts || !isset($dsnParts['scheme'], $dsnParts['host'], $dsnParts['path'])) {
        return null;
    }

    $host = $dsnParts['scheme'] . '://' . $dsnParts['host'];
    $pathParts = explode('/', trim($dsnParts['path'], '/'));
    $projectId = end($pathParts);
    $userParts = explode('@', $dsnParts['user'] ?? '');
    $publicKey = $userParts[0] ?? '';

    if (!$projectId || !$publicKey) {
        return null;
    }

    $securityUrl = "{$host}/api/{$projectId}/security/?sentry_key={$publicKey}";
    if ($environment) {
        $securityUrl .= "&sentry_environment=" . urlencode($environment);
    }
    if ($release) {
        $securityUrl .= "&sentry_release=" . urlencode($release);
    }

    return $securityUrl;
}
