<?php

namespace BleikoyaLogging;

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\LogRecord;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Loki Handler for Monolog
 *
 * Sends logs to Grafana Cloud Loki via HTTP push API
 */
class LokiHandler extends AbstractProcessingHandler
{
    private $client;
    private $url;
    private $username;
    private $password;
    private $labels;
    private $batchSize = 100;
    private $buffer = [];

    /**
     * Constructor
     */
    public function __construct(
        string $url,
        string $username,
        string $password,
        array $labels = [],
        $level = \Monolog\Logger::DEBUG,
        bool $bubble = true
    ) {
        parent::__construct($level, $bubble);

        $this->url = $url;
        $this->username = $username;
        $this->password = $password;
        $this->labels = $labels;

        $this->client = new Client([
            'timeout' => 5,
            'connect_timeout' => 2,
        ]);

        // Register shutdown function to flush remaining logs
        register_shutdown_function([$this, 'flush']);
    }

    /**
     * Write log record to Loki
     */
    protected function write(LogRecord $record): void
    {
        // Add to buffer
        $this->buffer[] = $record;

        // Flush if buffer is full
        if (count($this->buffer) >= $this->batchSize) {
            $this->flush();
        }
    }

    /**
     * Flush buffered logs to Loki
     */
    public function flush(): void
    {
        if (empty($this->buffer)) {
            return;
        }

        try {
            // Group logs by labels
            $streams = [];

            foreach ($this->buffer as $record) {
                $labels = $this->buildLabels($record);
                $key = json_encode($labels);

                if (!isset($streams[$key])) {
                    $streams[$key] = [
                        'stream' => $labels,
                        'values' => [],
                    ];
                }

                // Loki expects nanosecond timestamps
                $timestamp = $this->getNanosecondTimestamp($record);

                // Format log line
                $logLine = $this->formatLogLine($record);

                $streams[$key]['values'][] = [
                    (string)$timestamp,
                    $logLine,
                ];
            }

            // Send to Loki
            $this->sendToLoki([
                'streams' => array_values($streams),
            ]);

            // Clear buffer
            $this->buffer = [];
        } catch (\Exception $e) {
            // Log error but don't throw - we don't want logging to break the application
            error_log('Failed to send logs to Loki: ' . $e->getMessage());
        }
    }

    /**
     * Build labels for a log record
     */
    private function buildLabels(LogRecord $record): array
    {
        $labels = $this->labels;

        // Add log level as label
        $labels['level'] = strtolower($record->level->getName());

        // Add channel if available
        if (!empty($record->channel)) {
            $labels['channel'] = $record->channel;
        }

        // Add file if available in context
        if (!empty($record->context['file'])) {
            $labels['file'] = basename($record->context['file']);
        }

        return $labels;
    }

    /**
     * Format log line
     */
    private function formatLogLine(LogRecord $record): string
    {
        $data = [
            'message' => $record->message,
            'level' => $record->level->getName(),
            'datetime' => $record->datetime->format('Y-m-d H:i:s'),
        ];

        // Add context if available
        if (!empty($record->context)) {
            // Remove file from context as it's already in labels
            $context = $record->context;
            unset($context['file']);

            if (!empty($context)) {
                $data['context'] = $context;
            }
        }

        // Add extra data if available
        if (!empty($record->extra)) {
            $data['extra'] = $record->extra;
        }

        return json_encode($data);
    }

    /**
     * Get nanosecond timestamp
     */
    private function getNanosecondTimestamp(LogRecord $record): string
    {
        // Convert to nanoseconds
        $timestamp = $record->datetime->getTimestamp();
        $microseconds = $record->datetime->format('u');

        // Loki expects nanoseconds as a string
        return sprintf('%d%06d000', $timestamp, $microseconds);
    }

    /**
     * Send data to Loki
     */
    private function sendToLoki(array $data): void
    {
        try {
            $this->client->post($this->url, [
                'auth' => [$this->username, $this->password],
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'json' => $data,
            ]);
        } catch (GuzzleException $e) {
            error_log('Loki HTTP request failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Destructor - ensure logs are flushed
     */
    public function __destruct()
    {
        $this->flush();
    }
}
