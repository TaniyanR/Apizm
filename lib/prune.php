<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/settings.php';

function prune_expired_articles(PDO $pdo): void
{
    $retentionRaw = get_setting($pdo, 'article_retention_years', '0');
    $retention = (int) $retentionRaw;
    $allowed = [0, 1, 2, 3, 5];
    if (!in_array($retention, $allowed, true)) {
        $retention = 0;
    }
    if ($retention === 0) {
        return;
    }

    $cutoff = date('Y-m-d H:i:s', strtotime('-' . $retention . ' years'));

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('INSERT IGNORE INTO deleted_articles (article_id, reason, created_at) SELECT id, :reason, NOW() FROM articles WHERE is_deleted = 0 AND COALESCE(published_at, created_at) < :cutoff');
        $stmt->execute([
            ':reason' => '期限切れ',
            ':cutoff' => $cutoff,
        ]);

        $update = $pdo->prepare('UPDATE articles SET is_deleted = 1 WHERE is_deleted = 0 AND COALESCE(published_at, created_at) < :cutoff');
        $update->execute([':cutoff' => $cutoff]);

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}
