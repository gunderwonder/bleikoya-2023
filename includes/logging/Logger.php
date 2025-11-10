<?php

namespace BleikoyaLogging;

use Monolog\Logger as MonologLogger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Formatter\JsonFormatter;
use Psr\Log\LogLevel;

/**
 * Logger class
 *
 * Provides centralized logging to Sentry (error tracking) and Grafana Cloud Loki (log aggregation)
 */
class Logger
{
    private static $instance = null;
    private static $config = [];
    private static $monolog = null;
    private static $sentryInitialized = false;

    /**
     * Initialize the logger with configuration
     */
    public static function init(array $config)
    {
        self::$config = $config;

        // Initialize Monolog
        self::$monolog = new MonologLogger('bleikoya');

        // Set up Sentry if enabled
        if ($config['sentry']['enabled'] && !empty($config['sentry']['dsn'])) {
            self::initSentry($config);
        }

        // Set up Loki handler if enabled
        if ($config['loki']['enabled'] && !empty($config['loki']['url'])) {
            self::addLokiHandler($config);
        }

        // Set up local file logging if enabled
        if ($config['local']['enabled']) {
            self::addLocalHandler($config);
        }

        // Set up error and exception handlers
        self::registerErrorHandlers();
    }

    /**
     * Initialize Sentry
     */
    private static function initSentry(array $config)
    {
        if (self::$sentryInitialized) {
            return;
        }

        try {
            \Sentry\init([
                'dsn' => $config['sentry']['dsn'],
                'environment' => $config['environment'],
                'traces_sample_rate' => $config['sentry']['traces_sample_rate'],
                'send_default_pii' => $config['sentry']['send_default_pii'],
                'error_types' => $config['sentry']['error_types'],
                'release' => self::getRelease(),
            ]);

            self::$sentryInitialized = true;
        } catch (\Exception $e) {
            error_log('Failed to initialize Sentry: ' . $e->getMessage());
        }
    }

    /**
     * Add Loki handler to Monolog
     */
    private static function addLokiHandler(array $config)
    {
        try {
            $handler = new LokiHandler(
                $config['loki']['url'],
                $config['loki']['username'],
                $config['loki']['password'],
                $config['loki']['labels']
            );

            $minLevel = self::getMonologLevel($config['min_level']);
            $handler->setLevel($minLevel);

            self::$monolog->pushHandler($handler);
        } catch (\Exception $e) {
            error_log('Failed to add Loki handler: ' . $e->getMessage());
        }
    }

    /**
     * Add local file handler
     */
    private static function addLocalHandler(array $config)
    {
        try {
            $logPath = get_template_directory() . '/' . $config['local']['path'];
            $logDir = dirname($logPath);

            // Create log directory if it doesn't exist
            if (!is_dir($logDir)) {
                mkdir($logDir, 0755, true);
            }

            $handler = new RotatingFileHandler(
                $logPath,
                $config['local']['max_files'],
                MonologLogger::DEBUG
            );

            $handler->setFormatter(new JsonFormatter());
            self::$monolog->pushHandler($handler);
        } catch (\Exception $e) {
            error_log('Failed to add local file handler: ' . $e->getMessage());
        }
    }

    /**
     * Register PHP error and exception handlers
     */
    private static function registerErrorHandlers()
    {
        // Error handler
        set_error_handler(function ($errno, $errstr, $errfile, $errline) {
            // Don't log if error reporting is disabled
            if (!(error_reporting() & $errno)) {
                return false;
            }

            // Check exclude patterns
            foreach (self::$config['exclude_patterns'] as $pattern) {
                if (preg_match($pattern, $errstr)) {
                    return false;
                }
            }

            $context = [
                'file' => $errfile,
                'line' => $errline,
                'error_type' => self::getErrorTypeName($errno),
            ];

            // Map error types to log levels
            if ($errno & (E_ERROR | E_USER_ERROR | E_RECOVERABLE_ERROR)) {
                self::error($errstr, $context);
            } elseif ($errno & (E_WARNING | E_USER_WARNING)) {
                self::warning($errstr, $context);
            } else {
                self::notice($errstr, $context);
            }

            // Don't execute PHP internal error handler
            return true;
        });

        // Exception handler
        set_exception_handler(function ($exception) {
            self::critical($exception->getMessage(), [
                'exception' => $exception,
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString(),
            ]);

            // Also capture in Sentry if available
            if (self::$sentryInitialized) {
                \Sentry\captureException($exception);
            }
        });

        // Shutdown handler for fatal errors
        register_shutdown_function(function () {
            $error = error_get_last();
            if ($error && ($error['type'] & (E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR))) {
                self::critical('Fatal error: ' . $error['message'], [
                    'file' => $error['file'],
                    'line' => $error['line'],
                    'type' => self::getErrorTypeName($error['type']),
                ]);
            }
        });
    }

    /**
     * Log a debug message
     */
    public static function debug($message, array $context = [])
    {
        self::log(LogLevel::DEBUG, $message, $context);
    }

    /**
     * Log an info message
     */
    public static function info($message, array $context = [])
    {
        self::log(LogLevel::INFO, $message, $context);
    }

    /**
     * Log a notice message
     */
    public static function notice($message, array $context = [])
    {
        self::log(LogLevel::NOTICE, $message, $context);
    }

    /**
     * Log a warning message
     */
    public static function warning($message, array $context = [])
    {
        self::log(LogLevel::WARNING, $message, $context);
    }

    /**
     * Log an error message
     */
    public static function error($message, array $context = [])
    {
        self::log(LogLevel::ERROR, $message, $context);

        // Also send to Sentry
        if (self::$sentryInitialized) {
            \Sentry\captureMessage($message, \Sentry\Severity::error());
        }
    }

    /**
     * Log a critical message
     */
    public static function critical($message, array $context = [])
    {
        self::log(LogLevel::CRITICAL, $message, $context);

        // Also send to Sentry
        if (self::$sentryInitialized) {
            \Sentry\captureMessage($message, \Sentry\Severity::fatal());
        }
    }

    /**
     * Log a message at any level
     */
    private static function log($level, $message, array $context = [])
    {
        if (!self::$config['enabled'] || !self::$monolog) {
            return;
        }

        // Add common context
        $context = array_merge([
            'environment' => self::$config['environment'],
            'timestamp' => time(),
            'user_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
        ], $context);

        self::$monolog->log($level, $message, $context);
    }

    /**
     * Get error type name from error number
     */
    private static function getErrorTypeName($errno)
    {
        $errors = [
            E_ERROR => 'E_ERROR',
            E_WARNING => 'E_WARNING',
            E_PARSE => 'E_PARSE',
            E_NOTICE => 'E_NOTICE',
            E_CORE_ERROR => 'E_CORE_ERROR',
            E_CORE_WARNING => 'E_CORE_WARNING',
            E_COMPILE_ERROR => 'E_COMPILE_ERROR',
            E_COMPILE_WARNING => 'E_COMPILE_WARNING',
            E_USER_ERROR => 'E_USER_ERROR',
            E_USER_WARNING => 'E_USER_WARNING',
            E_USER_NOTICE => 'E_USER_NOTICE',
            E_STRICT => 'E_STRICT',
            E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
            E_DEPRECATED => 'E_DEPRECATED',
            E_USER_DEPRECATED => 'E_USER_DEPRECATED',
        ];

        return $errors[$errno] ?? 'UNKNOWN';
    }

    /**
     * Convert log level string to Monolog constant
     */
    private static function getMonologLevel($level)
    {
        $levels = [
            'DEBUG' => MonologLogger::DEBUG,
            'INFO' => MonologLogger::INFO,
            'NOTICE' => MonologLogger::NOTICE,
            'WARNING' => MonologLogger::WARNING,
            'ERROR' => MonologLogger::ERROR,
            'CRITICAL' => MonologLogger::CRITICAL,
            'ALERT' => MonologLogger::ALERT,
            'EMERGENCY' => MonologLogger::EMERGENCY,
        ];

        return $levels[strtoupper($level)] ?? MonologLogger::WARNING;
    }

    /**
     * Get release version for Sentry
     */
    private static function getRelease()
    {
        // Try to get from git
        $gitDir = get_template_directory() . '/.git';
        if (is_dir($gitDir)) {
            $headFile = $gitDir . '/HEAD';
            if (file_exists($headFile)) {
                $head = trim(file_get_contents($headFile));
                if (preg_match('/ref: (.+)/', $head, $matches)) {
                    $refFile = $gitDir . '/' . $matches[1];
                    if (file_exists($refFile)) {
                        return substr(trim(file_get_contents($refFile)), 0, 7);
                    }
                }
            }
        }

        // Fallback to theme version
        $theme = wp_get_theme();
        return $theme->get('Version') ?: 'unknown';
    }
}
