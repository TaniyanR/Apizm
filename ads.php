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
    record_in_if_external('static');
} catch (Throwable $e) {
    error_log('IN record failed: ' . $e->getMessage());
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
    <title>広告掲載 - Apizm</title>
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
        <h1>広告掲載</h1>
    </header>
    <div class="container">
        <main>
            <section>
                <p>広告掲載のご相談は準備中です。</p>
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
