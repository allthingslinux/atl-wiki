/**
 * Sentry JavaScript SDK Initialization
 *
 * Configures Sentry Browser SDK for client-side error tracking, performance monitoring,
 * and user feedback collection. Loaded via MediaWiki ResourceLoader using Sentry Loader Script.
 *
 * Features:
 * - Error tracking and exception capture
 * - Performance tracing (BrowserTracing)
 * - Resource loading error capture (404s, failed assets)
 * - User feedback collection (crash report modal)
 * - Ad-blocker protection (Proxy guard, tunnel option)
 * - Sentry Toolbar integration (development)
 *
 * @see https://docs.sentry.io/platforms/javascript/
 * @see https://docs.sentry.io/platforms/javascript/install/loader/
 * @see wiki/configs/90-Sentry.php
 *
 * @category Configuration
 * @package  ATL-Wiki
 * @author   Kaizen <kaizen@allthingslinux.org>
 * @license  https://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 * @link     https://atl.wiki
 */

(function () {
  "use strict";

  // Ad-blocker protection: Proxy guard for graceful degradation
  if ("Proxy" in window) {
    window.Sentry = new Proxy(window.Sentry || {}, {
      get: function (target, key) {
        if (
          [
            "captureException",
            "captureMessage",
            "captureEvent",
            "addBreadcrumb",
          ].includes(key)
        ) {
          return function () {
            console.warn(
              "[Sentry] SDK blocked by ad-blocker, call ignored:",
              key
            );
            return Promise.resolve();
          };
        }
        if (["flush", "close"].includes(key)) {
          return function () {
            return Promise.resolve();
          };
        }
        return target[key];
      },
    });
  }

  const config = mw.config.get("wgSentryConfig");
  const toolbarConfig = mw.config.get("wgSentryToolbar");
  const crashReportConfig = mw.config.get("wgSentryCrashReport");

  if (!config || !config.loaderKey) {
    console.warn("[Sentry] Configuration missing - SDK not initialized");
    return;
  }

  // Configure sentryOnLoad callback (required by Loader Script)
  window.sentryOnLoad = function () {
    const fullConfig = {
      ...config,
      debug: config.environment === "development" ? "log" : false,
      attachStacktrace: true,
      tunnel:
        config.environment === "production"
          ? "/api.php?action=sentry-tunnel"
          : undefined,

      beforeSend: function (event, hint) {
        if (config.environment === "development") {
          console.log(
            "[Sentry] Capturing event:",
            event.exception?.values?.[0]?.value || event.message
          );
        }

        // Filter browser extension errors in production
        if (
          event.environment === "production" &&
          event.exception?.values?.[0]?.value
        ) {
          const errorValue = event.exception.values[0].value;
          if (
            errorValue.includes("chrome-extension://") ||
            errorValue.includes("moz-extension://") ||
            errorValue.includes("safari-extension://")
          ) {
            return null;
          }
        }

        // Convert plain objects to proper Error instances
        if (event.exception?.values?.[0]) {
          const exception = event.exception.values[0];
          if (exception.value && !exception.type) {
            try {
              const error = new Error(exception.value);
              event.exception.values[0] = {
                ...exception,
                type: "Error",
                stacktrace: error.stack
                  ? Sentry.Handlers.parseStackTrace(error.stack)
                  : undefined,
              };
            } catch (e) {
              console.warn("[Sentry] Failed to convert plain object to Error");
            }
          }
        }

        return event;
      },

      integrations: [
        new Sentry.Integrations.BrowserTracing({
          tracePropagationTargets: [window.location.origin],
        }),
        new Sentry.Integrations.BrowserApiErrors({
          xhr: true,
          console: false,
          fetch: true,
          history: true,
          sentry: false,
        }),
      ],

      initialScope: {
        tags: {
          "toolbar.enabled": "true",
          "browser.userAgent": navigator.userAgent,
          "browser.language": navigator.language,
          "page.url": window.location.href,
          "page.referrer": document.referrer || "none",
        },
      },

      maxValueLength: 250,
      normalizeDepth: 5,
    };

    Sentry.init(fullConfig);

    Sentry.onLoad(function () {
      const user = mw.config.get("wgSentryUser");
      if (user) {
        Sentry.setUser(user);
      }

      // Capture resource loading errors (404s for images, CSS, etc.)
      document.addEventListener(
        "error",
        function (event) {
          const target = event.target;
          if (!target || !target.tagName) return;

          let errorMessage = "Failed to load resource";
          let errorType = "ResourceLoadError";

          if (target.tagName === "IMG") {
            errorMessage = `Failed to load image: ${target.src}`;
            errorType = "ImageLoadError";
          } else if (target.tagName === "LINK" && target.rel === "stylesheet") {
            errorMessage = `Failed to load CSS: ${target.href}`;
            errorType = "CSSLoadError";
          } else if (target.tagName === "SCRIPT") {
            errorMessage = `Failed to load script: ${target.src}`;
            errorType = "ScriptLoadError";
          } else {
            errorMessage = `Failed to load ${target.tagName}: ${
              target.src || target.href
            }`;
          }

          const resourceError = new Error(errorMessage);
          resourceError.name = errorType;

          Sentry.withScope(function (scope) {
            scope.setTag("resource.type", target.tagName.toLowerCase());
            scope.setTag(
              "resource.url",
              target.src || target.href || "unknown"
            );
            scope.setTag(
              "resource.cors",
              target.crossOrigin ? "anonymous" : "none"
            );
            scope.setContext("resource", {
              tagName: target.tagName,
              src: target.src,
              href: target.href,
              crossOrigin: target.crossOrigin,
              integrity: target.integrity,
              currentSrc: target.currentSrc,
            });
            Sentry.captureException(resourceError);
          });
        },
        true
      );

      // Configure crash report modal
      if (
        crashReportConfig &&
        crashReportConfig.enabled &&
        crashReportConfig.eventId
      ) {
        if (crashReportConfig.showDialogOnError) {
          const dialogConfig = {
            eventId: crashReportConfig.eventId,
            title:
              crashReportConfig.title || "It looks like we're having issues.",
            subtitle:
              crashReportConfig.subtitle || "Our team has been notified.",
            subtitle2:
              crashReportConfig.subtitle2 ||
              "If you'd like to help, tell us what happened below.",
            labelName: crashReportConfig.labelName || "Name",
            labelEmail: crashReportConfig.labelEmail || "Email",
            labelComments: crashReportConfig.labelComments || "What happened?",
            labelClose: crashReportConfig.labelClose || "Close",
            labelSubmit: crashReportConfig.labelSubmit || "Submit",
            errorGeneric:
              crashReportConfig.errorGeneric ||
              "An unknown error occurred while submitting your report. Please try again.",
            errorFormEntry:
              crashReportConfig.errorFormEntry ||
              "Some fields were invalid. Please correct the errors and try again.",
            successMessage:
              crashReportConfig.successMessage ||
              "Your feedback has been sent. Thank you!",
            ...(crashReportConfig.user && { user: crashReportConfig.user }),
          };

          if (crashReportConfig.onLoad) {
            dialogConfig.onLoad = function () {
              console.log(
                "Sentry crash report dialog loaded for event:",
                crashReportConfig.eventId
              );
            };
          }

          if (crashReportConfig.onClose) {
            dialogConfig.onClose = function () {
              console.log("Sentry crash report dialog closed");
            };
          }

          Sentry.showReportDialog(dialogConfig);
        }

        // Global function for manual crash report triggering
        window.showSentryCrashReport = function (customConfig = {}) {
          const dialogConfig = {
            eventId: crashReportConfig.eventId,
            title:
              customConfig.title ||
              crashReportConfig.title ||
              "It looks like we're having issues.",
            subtitle:
              customConfig.subtitle ||
              crashReportConfig.subtitle ||
              "Our team has been notified.",
            subtitle2:
              customConfig.subtitle2 ||
              crashReportConfig.subtitle2 ||
              "If you'd like to help, tell us what happened below.",
            labelName:
              customConfig.labelName || crashReportConfig.labelName || "Name",
            labelEmail:
              customConfig.labelEmail ||
              crashReportConfig.labelEmail ||
              "Email",
            labelComments:
              customConfig.labelComments ||
              crashReportConfig.labelComments ||
              "What happened?",
            labelClose:
              customConfig.labelClose ||
              crashReportConfig.labelClose ||
              "Close",
            labelSubmit:
              customConfig.labelSubmit ||
              crashReportConfig.labelSubmit ||
              "Submit",
            errorGeneric:
              customConfig.errorGeneric || crashReportConfig.errorGeneric,
            errorFormEntry:
              customConfig.errorFormEntry || crashReportConfig.errorFormEntry,
            successMessage:
              customConfig.successMessage || crashReportConfig.successMessage,
            ...(customConfig.user || crashReportConfig.user
              ? {
                  user: customConfig.user || crashReportConfig.user,
                }
              : {}),
            onLoad: customConfig.onLoad,
            onClose: customConfig.onClose,
          };

          Sentry.showReportDialog(dialogConfig);
        };
      }

      Sentry.forceLoad();
    });
  };

  // Load Sentry Loader Script (data-lazy="no" for immediate loading)
  const script = document.createElement("script");
  script.src = "https://js.sentry-cdn.com/" + config.loaderKey + ".min.js";
  script.crossOrigin = "anonymous";
  script.setAttribute("data-lazy", "no");

  script.onerror = function () {
    console.error("[Sentry] Failed to load SDK from CDN");
  };

  script.onload = function () {
    console.log("[Sentry] SDK loaded successfully");

    // Load toolbar after main SDK
    if (toolbarConfig && toolbarConfig.enabled) {
      const toolbarScript = document.createElement("script");
      toolbarScript.src =
        "https://browser.sentry-cdn.com/sentry-toolbar/latest/toolbar.min.js";
      toolbarScript.crossOrigin = "anonymous";

      toolbarScript.onload = function () {
        if (window.SentryToolbar) {
          window.SentryToolbar.init({
            organizationSlug: toolbarConfig.organizationSlug,
            projectIdOrSlug: toolbarConfig.projectSlug,
            environment: config.environment,
            theme: "system",
            debug: config.environment === "development" ? "logging" : undefined,
          });
        }
      };

      document.head.appendChild(toolbarScript);
    }
  };

  document.head.appendChild(script);
})();
