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
    record_in_if_external('cat');
} catch (Throwable $e) {
    error_log('IN record failed: ' . $e->getMessage());
}

$categoryId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$category = null;
$articles = [];

if ($categoryId > 0) {
    $stmt = $pdo->prepare('SELECT id, name FROM categories WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $categoryId]);
    $category = $stmt->fetch();
}

if ($category) {
    $stmt = $pdo->prepare(
        'SELECT a.id, a.title, a.site_id, COALESCE(a.published_at, a.created_at) AS published_at, s.name AS site_name
         FROM articles a
         LEFT JOIN sites s ON s.id = a.site_id
         WHERE a.is_deleted = 0 AND (a.hold_status IS NULL OR a.hold_status = "")
           AND a.category_id = :category_id
         ORDER BY COALESCE(a.published_at, a.created_at) DESC'
    );
    $stmt->execute([':category_id' => $categoryId]);
    $articles = $stmt->fetchAll();
}

function build_relay_url(array $article, int $categoryId): string
{
    $params = [
        'aid' => (int) $article['id'],
        'at' => (string) ($article['title'] ?? ''),
        'back' => '/cat.php?id=' . $categoryId,
        'bt' => 'カテゴリ',
        'sec' => 'カテゴリ',
    ];

    return '/relay.php?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
}

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
    <title>カテゴリ - Apizm</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script defer src="/assets/track.js"></script>
    <style>
        body { margin: 0; font-family: sans-serif; background: #f7f7f9; color: #222; }
        header { background: #1f2937; color: #fff; padding: 16px; }
        header h1 { margin: 0; font-size: 1.5rem; }
        .container { max-width: 960px; margin: 0 auto; padding: 16px; display: flex; gap: 24px; }
        main { flex: 1; }
        aside { width: 240px; background: #fff; border-radius: 12px; padding: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); height: fit-content; }
        section { background: #fff; border-radius: 12px; padding: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); }
        ul { list-style: none; padding: 0; margin: 0; display: grid; gap: 10px; }
        .meta { font-size: 0.85rem; color: #6b7280; }
        a { color: #2563eb; text-decoration: none; }
        a:hover { text-decoration: underline; }
        @media (max-width: 900px) {
            .container { flex-direction: column; }
            aside { width: auto; }
        }
    </style>
</head>
<body>
    <header>
        <h1>カテゴリ: <?php echo h((string) ($category['name'] ?? '未指定')); ?></h1>
    </header>
    <div class="container">
        <main>
            <section>
                <?php if (!$category): ?>
                    <p>カテゴリが見つかりません。</p>
                <?php elseif (empty($articles)): ?>
                    <p>該当する記事がありません。</p>
                <?php else: ?>
                    <ul>
                        <?php foreach ($articles as $article): ?>
                            <li>
                                <a href="<?php echo h(build_relay_url($article, $categoryId)); ?>">
                                    <?php echo h((string) $article['title']); ?>
                                </a>
                                <div class="meta">
                                    <?php echo h((string) ($article['site_name'] ?? '')); ?>
                                    <?php if (!empty($article['published_at'])): ?>
                                        ・<?php echo h(date('m/d H:i', strtotime((string) $article['published_at']))); ?>
                                    <?php endif; ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>
        </main>
        <aside>
            <h2>固定リンク</h2>
            <ul>
                <?php foreach ($fixedLinks as $link): ?>
                    <li><a href="<?php echo h($link['url']); ?>"><?php echo h($link['label']); ?></a></li>
                <?php endforeach; ?>
            </ul>
        </aside>
    </div>
</body>
</html>
