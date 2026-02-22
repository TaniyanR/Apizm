<?php
require __DIR__ . '/../lib/admin_auth.php';
require __DIR__ . '/../lib/db.php';
require __DIR__ . '/../lib/util.php';

require_admin_login();

$pdo = get_pdo();

function fetch_rank(PDO $pdo, string $cacheKey, int $limit = 100): array
{
    $stmt = $pdo->prepare('SELECT rank_position, target_id, value FROM rank_cache WHERE cache_key = :cache_key ORDER BY rank_position ASC LIMIT :limit');
    $stmt->bindValue(':cache_key', $cacheKey, PDO::PARAM_STR);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

$siteIn24 = fetch_rank($pdo, 'in24_site');
$siteIn7 = fetch_rank($pdo, 'in7_site');
$out24 = fetch_rank($pdo, 'out24');
$out7 = fetch_rank($pdo, 'out7');
$pv24 = fetch_rank($pdo, 'pv24');
$pv7 = fetch_rank($pdo, 'pv7');

$articleIds = [];
foreach ([$out24, $out7, $pv24, $pv7] as $list) {
    foreach ($list as $row) {
        $articleIds[(int) $row['target_id']] = true;
    }
}
$articleMap = [];
if (!empty($articleIds)) {
    $placeholders = implode(',', array_fill(0, count($articleIds), '?'));
    $stmt = $pdo->prepare('SELECT id, title FROM articles WHERE id IN (' . $placeholders . ')');
    $stmt->execute(array_keys($articleIds));
    foreach ($stmt->fetchAll() as $row) {
        $articleMap[(int) $row['id']] = (string) $row['title'];
    }
}

$siteIds = [];
foreach ([$siteIn24, $siteIn7] as $list) {
    foreach ($list as $row) {
        $siteIds[(int) $row['target_id']] = true;
    }
}
$siteMap = [];
if (!empty($siteIds)) {
    $placeholders = implode(',', array_fill(0, count($siteIds), '?'));
    $stmt = $pdo->prepare('SELECT id, name FROM sites WHERE id IN (' . $placeholders . ')');
    $stmt->execute(array_keys($siteIds));
    foreach ($stmt->fetchAll() as $row) {
        $siteMap[(int) $row['id']] = (string) $row['name'];
    }
}
?>
<!doctype html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <title>ランキングキャッシュ</title>
    <style>
        body { font-family: sans-serif; padding: 16px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
        th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
        th { background: #f0f0f0; }
    </style>
</head>
<body>
    <p><a href="/admin/logout.php">ログアウト</a></p>
    <h1>ランキングキャッシュ</h1>

    <h2>サイトランキング（IN 24h）</h2>
    <table>
        <thead>
            <tr>
                <th>順位</th>
                <th>サイトID</th>
                <th>サイト名</th>
                <th>IN数</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($siteIn24 as $row): ?>
                <?php $siteId = (int) $row['target_id']; ?>
                <tr>
                    <td><?php echo h((string) $row['rank_position']); ?></td>
                    <td><?php echo h((string) $siteId); ?></td>
                    <td><?php echo h($siteMap[$siteId] ?? ''); ?></td>
                    <td><?php echo h((string) $row['value']); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <h2>サイトランキング（IN 7d）</h2>
    <table>
        <thead>
            <tr>
                <th>順位</th>
                <th>サイトID</th>
                <th>サイト名</th>
                <th>IN数</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($siteIn7 as $row): ?>
                <?php $siteId = (int) $row['target_id']; ?>
                <tr>
                    <td><?php echo h((string) $row['rank_position']); ?></td>
                    <td><?php echo h((string) $siteId); ?></td>
                    <td><?php echo h($siteMap[$siteId] ?? ''); ?></td>
                    <td><?php echo h((string) $row['value']); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <h2>記事OUTランキング（24h）</h2>
    <table>
        <thead>
            <tr>
                <th>順位</th>
                <th>記事ID</th>
                <th>タイトル</th>
                <th>OUT数</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($out24 as $row): ?>
                <?php $articleId = (int) $row['target_id']; ?>
                <tr>
                    <td><?php echo h((string) $row['rank_position']); ?></td>
                    <td><?php echo h((string) $articleId); ?></td>
                    <td><?php echo h($articleMap[$articleId] ?? ''); ?></td>
                    <td><?php echo h((string) $row['value']); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <h2>記事OUTランキング（7d）</h2>
    <table>
        <thead>
            <tr>
                <th>順位</th>
                <th>記事ID</th>
                <th>タイトル</th>
                <th>OUT数</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($out7 as $row): ?>
                <?php $articleId = (int) $row['target_id']; ?>
                <tr>
                    <td><?php echo h((string) $row['rank_position']); ?></td>
                    <td><?php echo h((string) $articleId); ?></td>
                    <td><?php echo h($articleMap[$articleId] ?? ''); ?></td>
                    <td><?php echo h((string) $row['value']); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <h2>記事PVランキング（24h）</h2>
    <table>
        <thead>
            <tr>
                <th>順位</th>
                <th>記事ID</th>
                <th>タイトル</th>
                <th>PV数</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($pv24 as $row): ?>
                <?php $articleId = (int) $row['target_id']; ?>
                <tr>
                    <td><?php echo h((string) $row['rank_position']); ?></td>
                    <td><?php echo h((string) $articleId); ?></td>
                    <td><?php echo h($articleMap[$articleId] ?? ''); ?></td>
                    <td><?php echo h((string) $row['value']); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <h2>記事PVランキング（7d）</h2>
    <table>
        <thead>
            <tr>
                <th>順位</th>
                <th>記事ID</th>
                <th>タイトル</th>
                <th>PV数</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($pv7 as $row): ?>
                <?php $articleId = (int) $row['target_id']; ?>
                <tr>
                    <td><?php echo h((string) $row['rank_position']); ?></td>
                    <td><?php echo h((string) $articleId); ?></td>
                    <td><?php echo h($articleMap[$articleId] ?? ''); ?></td>
                    <td><?php echo h((string) $row['value']); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>
