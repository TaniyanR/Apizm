<?php
require __DIR__ . '/../lib/db.php';
require __DIR__ . '/../lib/util.php';
require __DIR__ . '/../lib/metrics.php';

$pdo = get_pdo();
$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $hostInput = trim((string) ($_POST['host'] ?? ''));
    $matchType = isset($_POST['match_type']) ? (int) $_POST['match_type'] : 0;
    $normalized = normalize_host($hostInput);

    if ($normalized === '') {
        $errors[] = 'ホストを入力してください。';
    }
    if (!in_array($matchType, [0, 1], true)) {
        $matchType = 0;
    }

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare('INSERT INTO excluded_urls (host, match_type, created_at) VALUES (:host, :match_type, NOW()) ON DUPLICATE KEY UPDATE match_type = VALUES(match_type)');
            $stmt->execute([
                ':host' => $normalized,
                ':match_type' => $matchType,
            ]);
            $success = '除外URLを追加しました。';
        } catch (Throwable $e) {
            error_log('Excluded URL insert failed: ' . $e->getMessage());
            $errors[] = '保存に失敗しました。';
        }
    }
}

$excludedStmt = $pdo->query('SELECT id, host, match_type, created_at FROM excluded_urls ORDER BY created_at DESC LIMIT 200');
$excludedList = $excludedStmt->fetchAll();

$refStmt = $pdo->query('SELECT ref_host, COUNT(*) AS cnt FROM access_in WHERE ref_host <> "" GROUP BY ref_host ORDER BY cnt DESC LIMIT 200');
$refRows = $refStmt->fetchAll();
$unregistered = [];
foreach ($refRows as $row) {
    $host = (string) $row['ref_host'];
    if (!is_excluded_host($pdo, $host)) {
        $unregistered[] = [
            'host' => $host,
            'cnt' => (int) $row['cnt'],
        ];
    }
    if (count($unregistered) >= 50) {
        break;
    }
}
?>
<!doctype html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <title>除外URL管理</title>
    <style>
        body { font-family: sans-serif; padding: 16px; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
        th { background: #f0f0f0; }
        form { margin-bottom: 16px; }
    </style>
</head>
<body>
    <h1>除外URL管理</h1>

    <?php if (!empty($errors)): ?>
        <ul>
            <?php foreach ($errors as $error): ?>
                <li><?php echo h($error); ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
    <?php if ($success !== ''): ?>
        <p><?php echo h($success); ?></p>
    <?php endif; ?>

    <form method="post">
        <label>
            ホスト
            <input type="text" name="host" value="">
        </label>
        <label>
            マッチタイプ
            <select name="match_type">
                <option value="0">完全一致</option>
                <option value="1">配下含む</option>
            </select>
        </label>
        <button type="submit">追加</button>
    </form>

    <h2>未登録IN（上位）</h2>
    <table>
        <thead>
            <tr>
                <th>ホスト</th>
                <th>件数</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($unregistered as $row): ?>
                <tr>
                    <td><?php echo h($row['host']); ?></td>
                    <td><?php echo h((string) $row['cnt']); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <h2>除外URL一覧</h2>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>ホスト</th>
                <th>マッチタイプ</th>
                <th>作成日</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($excludedList as $row): ?>
                <tr>
                    <td><?php echo h((string) $row['id']); ?></td>
                    <td><?php echo h((string) $row['host']); ?></td>
                    <td><?php echo h((string) $row['match_type']); ?></td>
                    <td><?php echo h((string) $row['created_at']); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>
