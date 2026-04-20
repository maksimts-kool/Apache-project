<?php

/**
 * Shared structured logger for API and DB events.
 * Sends logs to Elasticsearch and falls back to PHP error_log on failure.
 */

function elasticsearch_base_url(): string
{
    return rtrim(getenv('ELASTICSEARCH_URL') ?: 'http://elasticsearch:9200', '/');
}

function send_to_elasticsearch(string $index, array $document): bool
{
    $url = elasticsearch_base_url() . '/' . rawurlencode($index) . '/_doc';
    $payload = json_encode($document);

    if ($payload === false) {
        error_log('Failed to encode log payload as JSON.');
        return false;
    }

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_TIMEOUT => 2,
        ]);

        curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $httpCode >= 200 && $httpCode < 300;
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\n",
            'content' => $payload,
            'timeout' => 2,
            'ignore_errors' => true,
        ],
    ]);

    $result = @file_get_contents($url, false, $context);
    if ($result === false || empty($http_response_header)) {
        return false;
    }

    foreach ($http_response_header as $headerLine) {
        if (preg_match('/^HTTP\/\d(?:\.\d)?\s+(\d{3})/i', $headerLine, $matches)) {
            $httpCode = (int) $matches[1];
            return $httpCode >= 200 && $httpCode < 300;
        }
    }

    return false;
}

function api_log(string $level, string $endpoint, string $action, string $message, array $context = []): void
{
    $document = [
        'timestamp' => gmdate('c'),
        'level' => strtoupper($level),
        'component' => 'api',
        'endpoint' => $endpoint,
        'action' => $action,
        'message' => $message,
        'project' => 'kawaiiemoji',
        'method' => $_SERVER['REQUEST_METHOD'] ?? 'CLI',
        'context' => $context,
    ];

    if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['user_id'])) {
        $document['user_id'] = (int) $_SESSION['user_id'];
    }

    if (!send_to_elasticsearch('api-logs', $document)) {
        error_log('Elasticsearch log delivery failed: ' . $message . ' | context=' . json_encode($context));
    }
}

function api_log_validation_error(string $endpoint, string $action, string $message, array $context = []): void
{
    api_log('WARNING', $endpoint, $action, $message, $context);
}

function api_log_db_error(string $endpoint, string $action, Throwable $e, array $context = []): void
{
    $context['error'] = $e->getMessage();
    api_log('ERROR', $endpoint, $action, 'Database error', $context);
}
