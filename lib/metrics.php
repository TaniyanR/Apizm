<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/util.php';
require_once __DIR__ . '/settings.php';
require_once __DIR__ . '/rank_cache.php';
require_once __DIR__ . '/prune.php';

function is_excluded_host(PDO $pdo, string $host): bool
{
    if ($host === '') {
        return false;
    }

    $stmt = $pdo->prepare('SELECT host, match_type FROM excluded_urls WHERE host = :host OR (:host LIKE CONCAT("%.", host) AND match_type = 1)');
    $stmt->execute([':host' => $host]);
    $rows = $stmt->fetchAll();

    foreach ($rows as $row) {
        $rowHost = (string) $row['host'];
        $matchType = (int) $row['match_type'];
        if ($matchType === 0 && $rowHost === $host) {
            return true;
        }
        if ($matchType === 1 && ($rowHost === $host || str_ends_with($host, '.' . $rowHost))) {
            return true;
        }
    }

    return false;
}

function record_in_if_external(string $pageType, ?int $articleId = null, ?int $siteId = null): void
{
    $refHost = referrer_host();
    if (is_internal_referrer($refHost)) {
        return;
    }

    $pdo = get_pdo();

    try {
        if ($refHost !== '' && is_excluded_host($pdo, $refHost)) {
            return;
        }
    } catch (Throwable $e) {
        error_log('Excluded host check failed: ' . $e->getMessage());
        return;
    }

    $ip = client_ip();
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $refUrl = referrer_url();

    try {
        $bucket = time_bucket(43200);
        $siteKey = $siteId ?? $refHost;
        $dedup = dedup_key([$ip, $siteKey, $ua, $bucket]);
        $stmt = $pdo->prepare('INSERT IGNORE INTO access_in (page_type, article_id, site_id, ref_host, ref_url, ip, ua_hash, created_at, dedup_key) VALUES (:page_type, :article_id, :site_id, :ref_host, :ref_url, :ip, :ua_hash, NOW(), :dedup_key)');
        $stmt->execute([
            ':page_type' => $pageType,
            ':article_id' => $articleId,
            ':site_id' => $siteId,
            ':ref_host' => $refHost,
            ':ref_url' => $refUrl,
            ':ip' => $ip,
            ':ua_hash' => ua_hash($ua),
            ':dedup_key' => $dedup,
        ]);
    } catch (Throwable $e) {
        error_log('IN insert failed: ' . $e->getMessage());
    }
}

function maybe_refresh(PDO $pdo): void
{
    $intervalRaw = get_setting($pdo, 'refresh_interval_min', '60');
    $interval = (int) $intervalRaw;
    $allowed = [10, 30, 60, 120, 180, 360];
    if (!in_array($interval, $allowed, true)) {
        $interval = 60;
    }

    $lastRefresh = get_setting($pdo, 'last_refresh_at');
    $lastTime = $lastRefresh ? strtotime($lastRefresh) : 0;
    if ($lastTime > 0 && (time() - $lastTime) < ($interval * 60)) {
        return;
    }

    set_setting($pdo, 'last_refresh_at', date('Y-m-d H:i:s'));

    try {
        rebuild_rank_cache($pdo);
    } catch (Throwable $e) {
        error_log('Rank cache rebuild failed: ' . $e->getMessage());
    }

    $lastPrune = get_setting($pdo, 'last_prune_at');
    $lastPruneTime = $lastPrune ? strtotime($lastPrune) : 0;
    if ($lastPruneTime === 0 || (time() - $lastPruneTime) >= 86400) {
        try {
            prune_expired_articles($pdo);
            set_setting($pdo, 'last_prune_at', date('Y-m-d H:i:s'));
        } catch (Throwable $e) {
            error_log('Prune failed: ' . $e->getMessage());
        }
    }
}
