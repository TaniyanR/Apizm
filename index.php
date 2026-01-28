<?php
require __DIR__ . '/lib/db.php';
require __DIR__ . '/lib/util.php';
require __DIR__ . '/lib/metrics.php';
require __DIR__ . '/lib/settings.php';

$pdo = get_pdo();

try {
    maybe_refresh($pdo);
} catch (Throwable $e) {
    error_log('maybe_refresh failed: ' . $e->getMessage());
}
try {
    record_in_if_external('top');
} catch (Throwable $e) {
    error_log('IN record failed: ' . $e->getMessage());
}

function fetch_latest_articles(PDO $pdo, int $limit = 20): array
{
    $stmt = $pdo->prepare(
        'SELECT a.id, a.title, a.site_id, a.created_at, s.name AS site_name
         FROM articles a
         LEFT JOIN sites s ON s.id = a.site_id
         WHERE a.is_deleted = 0 AND (a.hold_status IS NULL OR a.hold_status = "")
         ORDER BY a.created_at DESC
         LIMIT :limit'
    );
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

function fetch_favored_articles(PDO $pdo, int $limit = 3): array
{
    $stmt = $pdo->prepare('SELECT slot, site_id, article_id FROM favored ORDER BY slot ASC LIMIT :limit');
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $favored = $stmt->fetchAll();

    $articles = [];
    $used = [];

    foreach ($favored as $row) {
        $article = null;
        $articleId = (int) ($row['article_id'] ?? 0);
        $siteId = (int) ($row['site_id'] ?? 0);

        if ($articleId > 0) {
            $stmt = $pdo->prepare(
                'SELECT a.id, a.title, a.site_id, a.created_at, s.name AS site_name
                 FROM articles a
                 LEFT JOIN sites s ON s.id = a.site_id
                 WHERE a.id = :id AND a.is_deleted = 0 AND (a.hold_status IS NULL OR a.hold_status = "")
                 LIMIT 1'
            );
            $stmt->execute([':id' => $articleId]);
            $article = $stmt->fetch();
        } elseif ($siteId > 0) {
            $stmt = $pdo->prepare(
                'SELECT a.id, a.title, a.site_id, a.created_at, s.name AS site_name
                 FROM articles a
                 LEFT JOIN sites s ON s.id = a.site_id
                 WHERE a.site_id = :site_id AND a.is_deleted = 0 AND (a.hold_status IS NULL OR a.hold_status = "")
                 ORDER BY a.created_at DESC
                 LIMIT 1'
            );
            $stmt->execute([':site_id' => $siteId]);
            $article = $stmt->fetch();
        }

        if ($article && !isset($used[$article['id']])) {
            $articles[] = $article;
            $used[$article['id']] = true;
        }
    }

    return $articles;
}

function fetch_ranked_articles(PDO $pdo, string $cacheKey, int $limit = 20): array
{
    $stmt = $pdo->prepare(
        'SELECT rc.rank_position, rc.value, a.id, a.title, a.site_id, a.created_at, s.name AS site_name
         FROM rank_cache rc
         INNER JOIN articles a ON a.id = rc.target_id
         LEFT JOIN sites s ON s.id = a.site_id
         WHERE rc.cache_key = :cache_key AND a.is_deleted = 0 AND (a.hold_status IS NULL OR a.hold_status = "")
         ORDER BY rc.rank_position ASC
         LIMIT :limit'
    );
    $stmt->bindValue(':cache_key', $cacheKey, PDO::PARAM_STR);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

function build_relay_url(array $article, string $section): string
{
    $params = [
        'aid' => (int) $article['id'],
        'at' => (string) ($article['title'] ?? ''),
        'back' => '/',
        'bt' => 'トップ',
        'sec' => $section,
    ];

    return '/relay.php?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
}

$latestArticles = fetch_latest_articles($pdo, 20);
$recommendedArticles = fetch_favored_articles($pdo, 3);
if (empty($recommendedArticles)) {
    $recommendedArticles = array_slice($latestArticles, 0, 3);
}

$popular24 = fetch_ranked_articles($pdo, 'out24', 20);
$popular7 = fetch_ranked_articles($pdo, 'out7', 20);

$adPc1 = trim((string) get_setting($pdo, 'ad_pc_1', ''));
$adPc2 = trim((string) get_setting($pdo, 'ad_pc_2', ''));
$adSp1 = trim((string) get_setting($pdo, 'ad_sp_1', ''));
$adSp2 = trim((string) get_setting($pdo, 'ad_sp_2', ''));
$adSp3 = trim((string) get_setting($pdo, 'ad_sp_3', ''));

$fixedLinks = [
    ['label' => 'サイトについて', 'url' => '/about.php'],
    ['label' => 'お問い合わせ', 'url' => '/contact.php'],
    ['label' => '広告掲載', 'url' => '/ads.php'],
];
?>
<!doctype html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <title>Apizm</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script defer src="/assets/track.js"></script>
    <style>
        body { margin: 0; font-family: sans-serif; background: #f7f7f9; color: #222; }
        header { background: #1f2937; color: #fff; padding: 16px; }
        header h1 { margin: 0; font-size: 1.5rem; }
        .container { max-width: 1120px; margin: 0 auto; padding: 16px; display: flex; gap: 24px; }
        main { flex: 1; min-width: 0; }
        aside { width: 260px; background: #fff; border-radius: 12px; padding: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); height: fit-content; }
        section { margin-bottom: 24px; background: #fff; border-radius: 12px; padding: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); }
        h2 { margin: 0 0 12px; font-size: 1.1rem; }
        .scroll-list { display: flex; gap: 12px; overflow-x: auto; padding-bottom: 8px; }
        .scroll-card { min-width: 220px; flex: 0 0 auto; border: 1px solid #e5e7eb; border-radius: 10px; padding: 12px; background: #fff; }
        .scroll-card h3 { margin: 0 0 8px; font-size: 1rem; }
        .meta { font-size: 0.85rem; color: #6b7280; }
        .recommend-list { display: grid; gap: 12px; }
        .recommend-card { border: 1px solid #e5e7eb; border-radius: 10px; padding: 14px; min-height: 120px; display: flex; flex-direction: column; justify-content: space-between; }
        .rank-list { display: grid; gap: 10px; }
        .rank-item { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px dashed #e5e7eb; padding-bottom: 6px; }
        .rank-item:last-child { border-bottom: none; padding-bottom: 0; }
        .rank-title { font-weight: 600; }
        .rank-count { font-size: 0.85rem; color: #6b7280; }
        .ad-slot { border: 1px dashed #cbd5f5; padding: 12px; border-radius: 10px; text-align: center; color: #475569; background: #f8fafc; }
        .fixed-links ul { list-style: none; padding-left: 0; margin: 0; }
        .fixed-links li { margin-bottom: 8px; }
        .fixed-links a { text-decoration: none; color: #1f2937; }
        a { color: #2563eb; text-decoration: none; }
        a:hover { text-decoration: underline; }
        @media (max-width: 900px) {
            .container { flex-direction: column; }
            aside { width: auto; }
        }
        .ad-pc { display: block; }
        .ad-sp { display: none; }
        @media (max-width: 768px) {
            .ad-pc { display: none; }
            .ad-sp { display: block; }
        }
    </style>
</head>
<body>
    <header>
        <h1>Apizm</h1>
    </header>
    <div class="container">
        <main>
            <section data-track-block="latest">
                <h2>新着</h2>
                <div class="scroll-list">
                    <?php if (empty($latestArticles)): ?>
                        <p>新着記事はまだありません。</p>
                    <?php else: ?>
                        <?php foreach ($latestArticles as $article): ?>
                            <article class="scroll-card">
                                <h3>
                                    <a href="<?php echo h(build_relay_url($article, '新着')); ?>">
                                        <?php echo h((string) $article['title']); ?>
                                    </a>
                                </h3>
                                <div class="meta">
                                    <?php echo h((string) ($article['site_name'] ?? '')); ?>
                                    <?php if (!empty($article['created_at'])): ?>
                                        ・<?php echo h(date('m/d H:i', strtotime((string) $article['created_at']))); ?>
                                    <?php endif; ?>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>

            <?php if ($adPc1 !== ''): ?>
                <div class="ad-slot ad-pc" data-track-block="ad-pc-1"><?php echo $adPc1; ?></div>
            <?php endif; ?>
            <?php if ($adSp1 !== ''): ?>
                <div class="ad-slot ad-sp" data-track-block="ad-sp-1"><?php echo $adSp1; ?></div>
            <?php endif; ?>

            <section data-track-block="recommend">
                <h2>おすすめ</h2>
                <div class="recommend-list">
                    <?php if (empty($recommendedArticles)): ?>
                        <p>おすすめ記事は準備中です。</p>
                    <?php else: ?>
                        <?php foreach ($recommendedArticles as $article): ?>
                            <article class="recommend-card">
                                <div class="rank-title">
                                    <a href="<?php echo h(build_relay_url($article, 'おすすめ')); ?>">
                                        <?php echo h((string) $article['title']); ?>
                                    </a>
                                </div>
                                <div class="meta">
                                    <?php echo h((string) ($article['site_name'] ?? '')); ?>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>

            <?php if ($adPc2 !== ''): ?>
                <div class="ad-slot ad-pc" data-track-block="ad-pc-2"><?php echo $adPc2; ?></div>
            <?php endif; ?>
            <?php if ($adSp2 !== ''): ?>
                <div class="ad-slot ad-sp" data-track-block="ad-sp-2"><?php echo $adSp2; ?></div>
            <?php endif; ?>

            <section data-track-block="popular-24">
                <h2>人気24h</h2>
                <div class="rank-list">
                    <?php if (empty($popular24)): ?>
                        <p>人気24hは集計中です。</p>
                    <?php else: ?>
                        <?php foreach ($popular24 as $row): ?>
                            <div class="rank-item">
                                <div>
                                    <span class="rank-title"><?php echo h((string) $row['rank_position']); ?>位</span>
                                    <a href="<?php echo h(build_relay_url($row, '人気24h')); ?>">
                                        <?php echo h((string) $row['title']); ?>
                                    </a>
                                    <span class="meta"><?php echo h((string) ($row['site_name'] ?? '')); ?></span>
                                </div>
                                <span class="rank-count"><?php echo h((string) $row['value']); ?> OUT</span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>

            <?php if ($adSp3 !== ''): ?>
                <div class="ad-slot ad-sp" data-track-block="ad-sp-3"><?php echo $adSp3; ?></div>
            <?php endif; ?>

            <section data-track-block="popular-7">
                <h2>人気7d</h2>
                <div class="rank-list">
                    <?php if (empty($popular7)): ?>
                        <p>人気7dは集計中です。</p>
                    <?php else: ?>
                        <?php foreach ($popular7 as $row): ?>
                            <div class="rank-item">
                                <div>
                                    <span class="rank-title"><?php echo h((string) $row['rank_position']); ?>位</span>
                                    <a href="<?php echo h(build_relay_url($row, '人気7d')); ?>">
                                        <?php echo h((string) $row['title']); ?>
                                    </a>
                                    <span class="meta"><?php echo h((string) ($row['site_name'] ?? '')); ?></span>
                                </div>
                                <span class="rank-count"><?php echo h((string) $row['value']); ?> OUT</span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>
        </main>

        <aside data-track-block="side">
            <section class="fixed-links">
                <h2>固定リンク</h2>
                <ul>
                    <?php foreach ($fixedLinks as $link): ?>
                        <li><a href="<?php echo h($link['url']); ?>"><?php echo h($link['label']); ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </section>
        </aside>
    </div>
</body>
</html>
