<?php

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function client_ip(): string
{
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $parts = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($parts[0]);
    }

    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function ua_hash(string $ua): string
{
    return hash('sha256', $ua);
}

function dedup_key(array $parts): string
{
    return hash('sha256', implode('|', $parts));
}

function time_bucket(int $seconds): int
{
    return (int) floor(time() / $seconds);
}

function is_bot_ua(string $ua): bool
{
    $pattern = '/bot|crawler|spider|curl|wget|python|scrapy|httpclient|monitoring/i';
    return (bool) preg_match($pattern, $ua);
}

function normalize_host(string $host): string
{
    $normalized = strtolower(trim($host));
    $normalized = rtrim($normalized, '.');
    if (str_starts_with($normalized, 'www.')) {
        $normalized = substr($normalized, 4);
    }
    $normalized = preg_replace('/:\d+$/', '', $normalized);

    return $normalized;
}

function referrer_url(): string
{
    return trim((string) ($_SERVER['HTTP_REFERER'] ?? ''));
}

function referrer_host(): string
{
    $ref = referrer_url();
    if ($ref === '') {
        return '';
    }
    $host = parse_url($ref, PHP_URL_HOST);
    if (!is_string($host) || $host === '') {
        return '';
    }

    return normalize_host($host);
}

function current_host(): string
{
    $host = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? '');
    if ($host === '') {
        return '';
    }

    return normalize_host($host);
}

function is_internal_referrer(string $refHost): bool
{
    if ($refHost === '') {
        return false;
    }

    $current = current_host();
    if ($current === '') {
        return false;
    }

    return $refHost === $current;
}
