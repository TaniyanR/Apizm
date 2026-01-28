<?php
require __DIR__ . '/../lib/db.php';
require __DIR__ . '/../lib/util.php';

$pdo = get_pdo();
$stmt = $pdo->query('SELECT id, article_id, site_id, reason, contact, message, status, created_at FROM deletion_requests ORDER BY created_at DESC LIMIT 100');
$requests = $stmt->fetchAll();
?>
<!doctype html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <title>削除依頼一覧</title>
    <style>
        body { font-family: sans-serif; padding: 16px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
        th { background: #f0f0f0; }
    </style>
</head>
<body>
    <h1>削除依頼一覧</h1>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>記事ID</th>
                <th>サイトID</th>
                <th>理由</th>
                <th>連絡先</th>
                <th>内容</th>
                <th>状態</th>
                <th>日時</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($requests as $request): ?>
                <tr>
                    <td><?php echo h((string) $request['id']); ?></td>
                    <td><?php echo h((string) $request['article_id']); ?></td>
                    <td><?php echo h((string) $request['site_id']); ?></td>
                    <td><?php echo h((string) $request['reason']); ?></td>
                    <td><?php echo h((string) $request['contact']); ?></td>
                    <td><?php echo h((string) $request['message']); ?></td>
                    <td><?php echo h((string) $request['status']); ?></td>
                    <td><?php echo h((string) $request['created_at']); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>
