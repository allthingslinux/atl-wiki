// Sentry JavaScript SDK initialization
// This module is loaded via MediaWiki's ResourceLoader
// Uses Sentry Loader Script for optimal performance and reliability
// https://docs.sentry.io/platforms/javascript/install/loader/
// Load timing: data-lazy="no" loads SDK after all scripts but before first error
// https://docs.sentry.io/platforms/javascript/install/loader/#load-timing

(function () {
    'use strict';

    // Proxy guard for ad-blocker protection
    // https://docs.sentry.io/platforms/javascript/troubleshooting/#using-the-javascript-proxy-api
    // Provides fallback when SDK is blocked by ad-blockers
    if ('Proxy' in window) {
        window.Sentry = new Proxy(window.Sentry || {}, {
            get: function(target, key) {
                // Return a no-op function for common SDK methods
                if (['captureException', 'captureMessage', 'captureEvent', 'addBreadcrumb'].includes(key)) {
                    return function() {
                        console.warn('[Sentry] SDK blocked by ad-blocker, call ignored:', key);
                        return Promise.resolve();
                    };
                }
                // Return resolved promise for flush/close
                if (['flush', 'close'].includes(key)) {
                    return function() { return Promise.resolve(); };
                }
                // Return the original value if it exists
                return target[key];
            }
        });
    }

    // Get configuration from MediaWiki
    const config = mw.config.get('wgSentryConfig');
    const toolbarConfig = mw.config.get('wgSentryToolbar');
    const crashReportConfig = mw.config.get('wgSentryCrashReport');

    // Diagnostic logging for troubleshooting
    console.log('[Sentry] Initializing SDK with config:', {
        environment: config?.environment,
        hasLoaderKey: !!config?.loaderKey,
        hasUser: !!mw.config.get('wgSentryUser'),
        hasCrashReport: !!crashReportConfig,
        hasToolbar: !!toolbarConfig,
        adBlockerProtection: 'Proxy' in window,
        userAgent: navigator.userAgent.substring(0, 100) + '...'
    });

    if (!config) {
        console.warn('[Sentry] No configuration available - SDK not initialized');
        return; // No Sentry config available
    }

    if (!config.loaderKey) {
        console.warn('[Sentry] No loader key (DSN) configured - SDK not initialized');
        return; // No DSN available
    }

    // Configure sentryOnLoad before adding loader script (required by Sentry)
    // https://docs.sentry.io/platforms/javascript/install/loader/#custom-configuration
    // Default configuration when tracing enabled: tracesSampleRate: 1
    // When session replay enabled: replaysSessionSampleRate: 0.1, replaysOnErrorSampleRate: 1
    // https://docs.sentry.io/platforms/javascript/install/loader/#default-configuration
    window.sentryOnLoad = function() {
        // Build complete config with beforeSend filter and toolbar enabled
        const fullConfig = {
            ...config,
            // Enhanced debugging for troubleshooting
            debug: config.environment === 'development' ? 'log' : false, // Log level debug in dev
            attachStacktrace: true,

            // Tunnel option for ad-blocker bypass
            // Routes events through server endpoint to avoid ad-blocker blocking
            // https://docs.sentry.io/platforms/javascript/troubleshooting/#using-the-tunnel-option
            tunnel: config.environment === 'production' ? '/w/api.php?action=sentry-tunnel' : undefined,

            // Comprehensive beforeSend filter for production noise reduction
            beforeSend: function(event, hint) {
                // Log event details in development for debugging
                if (config.environment === 'development') {
                    console.log('[Sentry] Capturing event:', event.exception?.values?.[0]?.value || event.message);
                }

                // Filter out browser extension errors in production
                if (event.environment === 'production') {
                    if (event.exception?.values?.[0]?.value) {
                        const errorValue = event.exception.values[0].value;
                        if (errorValue.includes('chrome-extension://') ||
                            errorValue.includes('moz-extension://') ||
                            errorValue.includes('safari-extension://')) {
                            console.log('[Sentry] Filtered browser extension error');
                            return null; // Filter out browser extension errors
                        }
                    }
                }

                // Validate error objects - convert plain objects to proper Errors
                if (event.exception?.values?.[0]) {
                    const exception = event.exception.values[0];
                    if (exception.value && !exception.type) {
                        // Convert non-Error objects to proper Error instances
                        console.warn('[Sentry] Converting plain object to Error:', exception.value);
                        try {
                            const error = new Error(exception.value);
                            event.exception.values[0] = {
                                ...exception,
                                type: 'Error',
                                stacktrace: error.stack ? Sentry.Handlers.parseStackTrace(error.stack) : undefined
                            };
                        } catch (e) {
                            console.warn('[Sentry] Failed to convert plain object to Error');
                        }
                    }
                }

                return event;
            },

            // Enhanced integrations with better error handling
            integrations: [
                new Sentry.Integrations.BrowserTracing({
                    tracePropagationTargets: [window.location.origin],
                }),
                // Capture resource loading errors (404s for images, CSS, etc.)
                new Sentry.Integrations.BrowserApiErrors({
                    xhr: true,
                    console: false, // We handle console elsewhere
                    fetch: true,
                    history: true,
                    sentry: false, // Avoid infinite loops
                }),
            ],

            // Explicitly enable toolbar (requires toolbar to be enabled in Sentry project)
            // The toolbar will show "Connecting to..." when working properly
            initialScope: {
                tags: {
                    'toolbar.enabled': 'true',
                    'browser.userAgent': navigator.userAgent,
                    'browser.language': navigator.language,
                    'page.url': window.location.href,
                    'page.referrer': document.referrer || 'none'
                }
            },

            // Performance and reliability settings
            maxValueLength: 250, // Truncate very long strings to prevent payload issues
            normalizeDepth: 5, // Limit object normalization depth
        };

        // Initialize Sentry with full configuration
        Sentry.init(fullConfig);

        // Set up additional configuration using Sentry.onLoad guard
        Sentry.onLoad(function() {
            // Set user context if available
            const user = mw.config.get('wgSentryUser');
            if (user) {
                Sentry.setUser(user);
            }

            // Capture resource loading errors (404s for images, CSS, etc.)
            // https://docs.sentry.io/platforms/javascript/troubleshooting/#capturing-resource-404s
            document.addEventListener('error', function(event) {
                const target = event.target;
                if (!target) return;

                // Only capture resource loading errors, not JavaScript errors
                if (target.tagName) {
                    let errorMessage = `Failed to load resource`;
                    let errorType = 'ResourceLoadError';

                    if (target.tagName === 'IMG') {
                        errorMessage = `Failed to load image: ${target.src}`;
                        errorType = 'ImageLoadError';
                    } else if (target.tagName === 'LINK' && target.rel === 'stylesheet') {
                        errorMessage = `Failed to load CSS: ${target.href}`;
                        errorType = 'CSSLoadError';
                    } else if (target.tagName === 'SCRIPT') {
                        errorMessage = `Failed to load script: ${target.src}`;
                        errorType = 'ScriptLoadError';
                    } else {
                        errorMessage = `Failed to load ${target.tagName}: ${target.src || target.href}`;
                    }

                    // Create a custom error with resource details
                    const resourceError = new Error(errorMessage);
                    resourceError.name = errorType;

                    // Add resource context
                    Sentry.withScope(function(scope) {
                        scope.setTag('resource.type', target.tagName.toLowerCase());
                        scope.setTag('resource.url', target.src || target.href || 'unknown');
                        scope.setTag('resource.cors', target.crossOrigin ? 'anonymous' : 'none');
                        scope.setContext('resource', {
                            tagName: target.tagName,
                            src: target.src,
                            href: target.href,
                            crossOrigin: target.crossOrigin,
                            integrity: target.integrity,
                            currentSrc: target.currentSrc, // For images
                        });
                        Sentry.captureException(resourceError);
                    });
                }
            }, true); // useCapture=true for resource loading errors

            // Configure crash report modal for user feedback collection
            // Shows when errors occur to collect user feedback
            // https://docs.sentry.io/platforms/javascript/user-feedback/#crash-report-modal
            if (crashReportConfig && crashReportConfig.enabled && crashReportConfig.eventId) {
                // Show crash report dialog if configured to auto-show
                if (crashReportConfig.showDialogOnError) {
                    // Build dialog configuration with all available options
                    const dialogConfig = {
                        eventId: crashReportConfig.eventId,

                        // UI Text (with fallbacks to defaults)
                        title: crashReportConfig.title || 'It looks like we\'re having issues.',
                        subtitle: crashReportConfig.subtitle || 'Our team has been notified.',
                        subtitle2: crashReportConfig.subtitle2 || 'If you\'d like to help, tell us what happened below.',

                        // Form Labels
                        labelName: crashReportConfig.labelName || 'Name',
                        labelEmail: crashReportConfig.labelEmail || 'Email',
                        labelComments: crashReportConfig.labelComments || 'What happened?',
                        labelClose: crashReportConfig.labelClose || 'Close',
                        labelSubmit: crashReportConfig.labelSubmit || 'Submit',

                        // Status Messages
                        errorGeneric: crashReportConfig.errorGeneric || 'An unknown error occurred while submitting your report. Please try again.',
                        errorFormEntry: crashReportConfig.errorFormEntry || 'Some fields were invalid. Please correct the errors and try again.',
                        successMessage: crashReportConfig.successMessage || 'Your feedback has been sent. Thank you!',

                        // Pre-fill user data if available
                        ...(crashReportConfig.user && { user: crashReportConfig.user }),
                    };

                    // Add callbacks if provided
                    if (crashReportConfig.onLoad) {
                        dialogConfig.onLoad = function() {
                            console.log('Sentry crash report dialog loaded for event:', crashReportConfig.eventId);
                            // Could call custom onLoad callback here
                        };
                    }

                    if (crashReportConfig.onClose) {
                        dialogConfig.onClose = function() {
                            console.log('Sentry crash report dialog closed');
                            // Could call custom onClose callback here
                        };
                    }

                    Sentry.showReportDialog(dialogConfig);
                }

                // Also make the crash report available globally for manual triggering
                // Usage: window.showSentryCrashReport({title: 'Custom Title'})
                window.showSentryCrashReport = function(customConfig = {}) {
                    // Merge default config with custom overrides
                    const dialogConfig = {
                        eventId: crashReportConfig.eventId,

                        // Use crash report config as defaults, allow custom overrides
                        title: customConfig.title || crashReportConfig.title || 'It looks like we\'re having issues.',
                        subtitle: customConfig.subtitle || crashReportConfig.subtitle || 'Our team has been notified.',
                        subtitle2: customConfig.subtitle2 || crashReportConfig.subtitle2 || 'If you\'d like to help, tell us what happened below.',

                        labelName: customConfig.labelName || crashReportConfig.labelName || 'Name',
                        labelEmail: customConfig.labelEmail || crashReportConfig.labelEmail || 'Email',
                        labelComments: customConfig.labelComments || crashReportConfig.labelComments || 'What happened?',
                        labelClose: customConfig.labelClose || crashReportConfig.labelClose || 'Close',
                        labelSubmit: customConfig.labelSubmit || crashReportConfig.labelSubmit || 'Submit',

                        errorGeneric: customConfig.errorGeneric || crashReportConfig.errorGeneric,
                        errorFormEntry: customConfig.errorFormEntry || crashReportConfig.errorFormEntry,
                        successMessage: customConfig.successMessage || crashReportConfig.successMessage,

                        // Allow custom user data override, fallback to config
                        ...(customConfig.user || crashReportConfig.user ? {
                            user: customConfig.user || crashReportConfig.user
                        } : {}),

                        // Allow custom callbacks to override defaults
                        onLoad: customConfig.onLoad,
                        onClose: customConfig.onClose,
                    };

                    Sentry.showReportDialog(dialogConfig);
                };
            }

            // Force immediate loading to ensure all errors are captured
            // This ensures breadcrumbs and other data are collected from page start
            // Equivalent to calling forceLoad() early: https://docs.sentry.io/platforms/javascript/install/loader/#load-timing
            Sentry.forceLoad();
        });
    };

    // Load Sentry Loader Script with data-lazy="no" for immediate SDK loading
    // https://docs.sentry.io/platforms/javascript/install/loader/#load-timing
    // data-lazy="no": Loads SDK after all other scripts but before first error/capture call
    // This ensures tracing and replay work from page start, unlike default lazy loading
    const script = document.createElement('script');
    script.src = 'https://js.sentry-cdn.com/' + config.loaderKey + '.min.js';
    script.crossOrigin = 'anonymous';
    script.setAttribute('data-lazy', 'no'); // Load SDK immediately, not on first error

    // Add error handling for SDK loading failures
    script.onerror = function() {
        console.error('[Sentry] Failed to load SDK from CDN. Possible ad-blocker or network issue.');
        console.warn('[Sentry] Consider using npm package or self-hosted SDK for better reliability.');
    };

    // Add load success logging
    script.onload = function() {
        console.log('[Sentry] SDK loaded successfully from CDN');
    };

    document.head.appendChild(script);

    // Load Sentry Toolbar separately (BETA feature)
    // The toolbar will show in the bottom-right corner when:
    // 1. Enabled in Sentry project settings (Settings > Projects > [Project] > Client Keys)
    // 2. Domain is added to "Allow Domains" in project settings
    // 3. CSP allows frame-src from sentry.io
    // 4. SENTRY_ORG_SLUG and SENTRY_PROJECT_SLUG environment variables are set
    const toolbarScript = document.createElement('script');
    toolbarScript.src = 'https://browser.sentry-cdn.com/sentry-toolbar/latest/toolbar.min.js';
    toolbarScript.crossOrigin = 'anonymous';

    // Initialize toolbar after it loads
    toolbarScript.onload = function() {
        if (window.SentryToolbar && toolbarConfig && toolbarConfig.enabled) {
            window.SentryToolbar.init({
                organizationSlug: toolbarConfig.organizationSlug,
                projectIdOrSlug: toolbarConfig.projectSlug,
                environment: config.environment,
                theme: 'system',
                debug: config.environment === 'development' ? 'logging' : undefined
            });
        }
    };

    // Load toolbar after main SDK
    script.onload = function() {
        // Only load toolbar if configuration is available
        if (toolbarConfig && toolbarConfig.enabled) {
            document.head.appendChild(toolbarScript);
        }
    };

    // Guard SDK function calls (available even before SDK loads)
    // https://docs.sentry.io/platforms/javascript/install/loader/#guarding-sdk-function-calls
    // captureException, captureMessage, captureEvent, addBreadcrumb available immediately
    // Other functions need Sentry.onLoad() guard
})();
