<?php
require __DIR__ . '/lib/db.php';
require __DIR__ . '/lib/util.php';
require __DIR__ . '/lib/metrics.php';

$aid = isset($_GET['aid']) ? (int) $_GET['aid'] : 0;
if ($aid <= 0) {
    http_response_code(400);
    echo '記事IDが不正です。';
    exit;
}

$pdo = get_pdo();
try {
    maybe_refresh($pdo);
} catch (Throwable $e) {
    error_log('maybe_refresh failed: ' . $e->getMessage());
}
$stmt = $pdo->prepare('SELECT id, site_id, url FROM articles WHERE id = :id LIMIT 1');
$stmt->execute([':id' => $aid]);
$article = $stmt->fetch();

if (!$article) {
    http_response_code(404);
    echo '記事が見つかりませんでした。';
    exit;
}

$ip = client_ip();
$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
$isBot = is_bot_ua($ua);

try {
    $bucket = time_bucket(300);
    $dedup = dedup_key([$aid, $ip, $ua, $bucket]);
    $stmt = $pdo->prepare('INSERT IGNORE INTO access_out (article_id, site_id, ip, ua_hash, created_at, dedup_key, is_bot, is_fraud, fraud_reason) VALUES (:article_id, :site_id, :ip, :ua_hash, NOW(), :dedup_key, :is_bot, :is_fraud, :fraud_reason)');
    $stmt->execute([
        ':article_id' => $article['id'],
        ':site_id' => $article['site_id'],
        ':ip' => $ip,
        ':ua_hash' => ua_hash($ua),
        ':dedup_key' => $dedup,
        ':is_bot' => $isBot ? 1 : 0,
        ':is_fraud' => $isBot ? 1 : 0,
        ':fraud_reason' => $isBot ? 'bot_ua' : null,
    ]);
} catch (Throwable $e) {
    error_log('OUT insert failed: ' . $e->getMessage());
}

$targetUrl = $article['url'];
header('Location: ' . $targetUrl, true, 302);
exit;
