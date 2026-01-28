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
