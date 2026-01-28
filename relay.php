<?php
require __DIR__ . '/lib/db.php';
require __DIR__ . '/lib/util.php';
require __DIR__ . '/lib/metrics.php';

$aid = isset($_GET['aid']) ? (int) $_GET['aid'] : 0;
$anchorText = trim((string) ($_GET['at'] ?? ''));
$backUrl = trim((string) ($_GET['back'] ?? ''));
$backText = trim((string) ($_GET['bt'] ?? ''));
$sectionName = trim((string) ($_GET['sec'] ?? ''));

$pdo = get_pdo();
$article = null;

if ($aid > 0) {
    $stmt = $pdo->prepare('SELECT id, site_id, title, url, is_deleted, hold_status FROM articles WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $aid]);
    $article = $stmt->fetch();
}

if (!$article) {
    http_response_code(404);
    echo '記事が見つかりませんでした。';
    exit;
}

try {
    maybe_refresh($pdo);
} catch (Throwable $e) {
    error_log('maybe_refresh failed: ' . $e->getMessage());
}

$articleTitle = $article['title'] ?? '';
$displayAnchor = $anchorText !== '' ? $anchorText : $articleTitle;
$articleUrl = $article['url'] ?? '';

$ip = client_ip();
$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';

try {
    $bucket = time_bucket(30);
    $dedup = dedup_key([$aid, $ip, $ua, $bucket]);
    $stmt = $pdo->prepare('INSERT IGNORE INTO article_pv (article_id, site_id, ip, ua_hash, created_at, dedup_key) VALUES (:article_id, :site_id, :ip, :ua_hash, NOW(), :dedup_key)');
    $stmt->execute([
        ':article_id' => $article['id'],
        ':site_id' => $article['site_id'],
        ':ip' => $ip,
        ':ua_hash' => ua_hash($ua),
        ':dedup_key' => $dedup,
    ]);
} catch (Throwable $e) {
    error_log('PV insert failed: ' . $e->getMessage());
}

try {
    record_in_if_external('relay', (int) $article['id'], (int) $article['site_id']);
} catch (Throwable $e) {
    error_log('IN record failed: ' . $e->getMessage());
}

$breadcrumbs = [];
$breadcrumbs[] = ['label' => 'トップ', 'url' => '/'];
if ($backUrl !== '' && $backText !== '') {
    $breadcrumbs[] = ['label' => $backText, 'url' => $backUrl];
}
if ($sectionName !== '') {
    $breadcrumbs[] = ['label' => $sectionName, 'url' => ''];
}
$breadcrumbs[] = ['label' => $articleTitle, 'url' => ''];

$fixedLinks = [
    ['label' => 'サイトについて', 'url' => '/about.php'],
    ['label' => 'お問い合わせ', 'url' => '/contact.php'],
    ['label' => '広告掲載', 'url' => '/ads.php'],
];

$isDeleted = (int) ($article['is_deleted'] ?? 0);
$holdStatus = $article['hold_status'] ?? '';

$noticeMessage = '';
if ($isDeleted === 1) {
    $noticeMessage = 'この記事は削除されています。';
}

$deletionSuccess = false;
$deletionError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reason = trim((string) ($_POST['reason'] ?? ''));
    $contact = trim((string) ($_POST['contact'] ?? ''));
    $message = trim((string) ($_POST['message'] ?? ''));
    $honeypot = trim((string) ($_POST['website'] ?? ''));

    if ($honeypot !== '') {
        $deletionError = '送信に失敗しました。';
    } else {
        try {
            $rateLimit = 3;
            $stmt = $pdo->prepare('SELECT COUNT(*) AS cnt FROM deletion_requests WHERE ip = :ip AND created_at >= (NOW() - INTERVAL 1 MINUTE)');
            $stmt->execute([':ip' => $ip]);
            $row = $stmt->fetch();
            $recentCount = $row ? (int) $row['cnt'] : 0;
            if ($recentCount >= $rateLimit) {
                $deletionError = '送信が集中しています。少し時間をおいて再度お試しください。';
            }
        } catch (Throwable $e) {
            error_log('Deletion rate limit check failed: ' . $e->getMessage());
        }
    }

    if ($deletionError === '') {
        try {
            $stmt = $pdo->prepare('INSERT INTO deletion_requests (article_id, site_id, reason, contact, message, ip, user_agent, status, created_at, updated_at) VALUES (:article_id, :site_id, :reason, :contact, :message, :ip, :user_agent, :status, NOW(), NOW())');
            $stmt->execute([
                ':article_id' => $article['id'],
                ':site_id' => $article['site_id'],
                ':reason' => $reason,
                ':contact' => $contact,
                ':message' => $message,
                ':ip' => $ip,
                ':user_agent' => $ua,
                ':status' => 'pending',
            ]);
            $deletionSuccess = true;
        } catch (Throwable $e) {
            error_log('Deletion request insert failed: ' . $e->getMessage());
            $deletionError = '送信に失敗しました。';
        }
    }
}
?>
<!doctype html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <title><?php echo h($articleTitle); ?> - relay</title>
    <script defer src="/assets/track.js"></script>
    <style>
        body { font-family: sans-serif; line-height: 1.6; }
        .container { max-width: 960px; margin: 0 auto; padding: 16px; display: flex; gap: 24px; }
        main { flex: 1; }
        aside { width: 240px; background: #f5f5f5; padding: 12px; }
        .breadcrumbs a { color: #444; text-decoration: none; }
        .relay-link { color: #b00020; font-weight: bold; }
        .notice { padding: 8px; background: #fff3cd; margin-bottom: 12px; }
        .fixed-links ul { list-style: none; padding-left: 0; }
        .fixed-links li { margin-bottom: 4px; }
        form input, form textarea { width: 100%; box-sizing: border-box; margin-bottom: 8px; }
        .honeypot { position: absolute; left: -9999px; top: -9999px; }
    </style>
</head>
<body data-article-id="<?php echo h((string) $article['id']); ?>">
<div class="container">
    <main>
        <nav class="breadcrumbs" aria-label="breadcrumbs">
            <?php foreach ($breadcrumbs as $index => $crumb): ?>
                <?php if ($crumb['url'] !== ''): ?>
                    <a href="<?php echo h($crumb['url']); ?>" data-track-block="breadcrumb"><?php echo h($crumb['label']); ?></a>
                <?php else: ?>
                    <span><?php echo h($crumb['label']); ?></span>
                <?php endif; ?>
                <?php if ($index < count($breadcrumbs) - 1): ?> &gt; <?php endif; ?>
            <?php endforeach; ?>
        </nav>

        <?php if ($noticeMessage !== ''): ?>
            <div class="notice"><?php echo h($noticeMessage); ?></div>
        <?php endif; ?>

        <h1><?php echo h($articleTitle); ?></h1>

        <p>
            <a class="relay-link" href="/out.php?aid=<?php echo h((string) $article['id']); ?>" data-track-block="relay-main">
                <?php echo h($displayAnchor); ?>
            </a>
        </p>

        <?php if ($holdStatus !== ''): ?>
            <p>状態: <?php echo h((string) $holdStatus); ?></p>
        <?php endif; ?>

        <section class="fixed-links" data-track-block="fixed-links">
            <h2>固定リンク</h2>
            <ul>
                <?php foreach ($fixedLinks as $link): ?>
                    <li><a href="<?php echo h($link['url']); ?>"><?php echo h($link['label']); ?></a></li>
                <?php endforeach; ?>
            </ul>
        </section>

        <section class="deletion-request" data-track-block="deletion-request">
            <h2>削除依頼</h2>
            <?php if ($deletionSuccess): ?>
                <p>送信しました。対応までお待ちください。</p>
            <?php endif; ?>
            <?php if ($deletionError !== ''): ?>
                <p><?php echo h($deletionError); ?></p>
            <?php endif; ?>
            <form method="post">
                <div class="honeypot" aria-hidden="true">
                    <label>
                        Webサイト
                        <input type="text" name="website" value="">
                    </label>
                </div>
                <label>
                    理由
                    <input type="text" name="reason" value="<?php echo isset($_POST['reason']) ? h((string) $_POST['reason']) : ''; ?>">
                </label>
                <label>
                    連絡先
                    <input type="text" name="contact" value="<?php echo isset($_POST['contact']) ? h((string) $_POST['contact']) : ''; ?>">
                </label>
                <label>
                    メッセージ
                    <textarea name="message" rows="4"><?php echo isset($_POST['message']) ? h((string) $_POST['message']) : ''; ?></textarea>
                </label>
                <button type="submit">送信</button>
            </form>
        </section>
    </main>
    <aside>
        <p>サイドバー（共通領域）</p>
    </aside>
</div>
</body>
</html>
