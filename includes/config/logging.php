<?php

/**
 * Logging Configuration
 *
 * This file configures error logging to Sentry and Grafana Cloud Loki.
 *
 * To enable logging, you need to set up:
 * 1. Sentry DSN - Get from https://sentry.io/ (free tier: 5K events/month)
 * 2. Grafana Cloud Loki credentials - Get from https://grafana.com/products/cloud/ (free tier: 50GB logs/month)
 */

return [
    /**
     * Environment detection
     * Automatically detect if we're in development or production
     */
    'environment' => defined('WP_DEBUG') && WP_DEBUG ? 'development' : 'production',

    /**
     * Enable/disable logging
     * Set to false to disable all external logging
     */
    'enabled' => true,

    /**
     * Sentry Configuration
     * For error tracking and exception monitoring
     */
    'sentry' => [
        'enabled' => !empty($_ENV['SENTRY_DSN'] ?? ''),

        // Get your DSN from https://sentry.io/settings/[org]/projects/[project]/keys/
        'dsn' => $_ENV['SENTRY_DSN'] ?? '',

        // Sample rate for performance monitoring (0.0 to 1.0)
        // Set to 0.1 (10%) to stay within free tier limits
        'traces_sample_rate' => 0.1,

        // Send default PII (Personally Identifiable Information)?
        // Set to false for privacy
        'send_default_pii' => false,

        // Error types to capture
        'error_types' => E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED & ~E_NOTICE,
    ],

    /**
     * Grafana Cloud Loki Configuration
     * For log aggregation and visualization
     */
    'loki' => [
        'enabled' => !empty($_ENV['LOKI_URL'] ?? ''),

        // Loki push endpoint
        // Format: https://logs-prod-XXX.grafana.net/loki/api/v1/push
        'url' => $_ENV['LOKI_URL'] ?? '',

        // Basic auth credentials
        // Username is usually a numeric user ID
        'username' => $_ENV['LOKI_USERNAME'] ?? '',

        // API key/password
        'password' => $_ENV['LOKI_PASSWORD'] ?? '',

        // Labels to attach to all logs
        'labels' => [
            'app' => 'bleikoya-net',
            'environment' => defined('WP_DEBUG') && WP_DEBUG ? 'development' : 'production',
            'server' => gethostname() ?: 'unknown',
        ],
    ],

    /**
     * Local logging configuration
     * For development debugging
     */
    'local' => [
        // Enable local file logging in development?
        'enabled' => defined('WP_DEBUG') && WP_DEBUG,

        // Log file path (relative to theme root)
        'path' => 'logs/app.log',

        // Maximum number of log files to keep
        'max_files' => 7,
    ],

    /**
     * Minimum log level
     * Options: DEBUG, INFO, NOTICE, WARNING, ERROR, CRITICAL, ALERT, EMERGENCY
     */
    'min_level' => defined('WP_DEBUG') && WP_DEBUG ? 'DEBUG' : 'WARNING',

    /**
     * Exclude patterns
     * Don't log errors that match these patterns
     */
    'exclude_patterns' => [
        '/deprecated/i',
        '/notice/i',
    ],
];
