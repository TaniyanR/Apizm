<?php
require __DIR__ . '/lib/db.php';
require __DIR__ . '/lib/util.php';

$raw = file_get_contents('php://input');
$data = [];

if (!empty($raw)) {
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        $data = $decoded;
    }
}

if (empty($data)) {
    $data = $_POST;
}

$eventType = trim((string) ($data['event_type'] ?? 'click'));
$fromPage = trim((string) ($data['from_page'] ?? ''));
$fromBlock = trim((string) ($data['from_block'] ?? ''));
$toUrl = trim((string) ($data['to_url'] ?? ''));
$articleId = isset($data['article_id']) ? (int) $data['article_id'] : null;

if ($toUrl === '') {
    http_response_code(204);
    exit;
}

$ip = client_ip();
$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';

try {
    $bucket = time_bucket(10);
    $dedup = dedup_key([$eventType, $fromPage, $fromBlock, $toUrl, $articleId, $ip, $ua, $bucket]);
    $stmt = get_pdo()->prepare('INSERT IGNORE INTO analytics_events (event_type, from_page, from_block, to_url, article_id, ip, ua_hash, dedup_key, created_at) VALUES (:event_type, :from_page, :from_block, :to_url, :article_id, :ip, :ua_hash, :dedup_key, NOW())');
    $stmt->execute([
        ':event_type' => $eventType,
        ':from_page' => $fromPage,
        ':from_block' => $fromBlock,
        ':to_url' => $toUrl,
        ':article_id' => $articleId,
        ':ip' => $ip,
        ':ua_hash' => ua_hash($ua),
        ':dedup_key' => $dedup,
    ]);
} catch (Throwable $e) {
    error_log('Analytics insert failed: ' . $e->getMessage());
}

http_response_code(204);
