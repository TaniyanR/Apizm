<?php
require_once __DIR__ . '/db.php';

function rebuild_rank_cache(PDO $pdo): void
{
    $now = date('Y-m-d H:i:s');
    $definitions = [
        'out24' => [
            'table' => 'access_out',
            'group' => 'article_id',
            'interval' => 'INTERVAL 1 DAY',
        ],
        'out7' => [
            'table' => 'access_out',
            'group' => 'article_id',
            'interval' => 'INTERVAL 7 DAY',
        ],
        'pv24' => [
            'table' => 'article_pv',
            'group' => 'article_id',
            'interval' => 'INTERVAL 1 DAY',
        ],
        'pv7' => [
            'table' => 'article_pv',
            'group' => 'article_id',
            'interval' => 'INTERVAL 7 DAY',
        ],
        'in24_site' => [
            'table' => 'access_in',
            'group' => 'site_id',
            'interval' => 'INTERVAL 1 DAY',
            'where' => 'site_id IS NOT NULL',
        ],
        'in7_site' => [
            'table' => 'access_in',
            'group' => 'site_id',
            'interval' => 'INTERVAL 7 DAY',
            'where' => 'site_id IS NOT NULL',
        ],
    ];

    $insert = $pdo->prepare('INSERT INTO rank_cache (cache_key, rank_position, target_id, value, updated_at) VALUES (:cache_key, :rank_position, :target_id, :value, :updated_at)');
    $delete = $pdo->prepare('DELETE FROM rank_cache WHERE cache_key = :cache_key');

    foreach ($definitions as $cacheKey => $def) {
        $group = $def['group'];
        $table = $def['table'];
        $interval = $def['interval'];
        $where = $def['where'] ?? '1=1';

        $sql = sprintf(
            'SELECT %1$s AS target_id, COUNT(*) AS total FROM %2$s WHERE %3$s AND created_at >= (NOW() - %4$s) GROUP BY %1$s ORDER BY total DESC LIMIT 1000',
            $group,
            $table,
            $where,
            $interval
        );
        $stmt = $pdo->query($sql);
        $rows = $stmt->fetchAll();

        $delete->execute([':cache_key' => $cacheKey]);

        $rank = 1;
        foreach ($rows as $row) {
            $targetId = (int) $row['target_id'];
            $value = (int) $row['total'];
            $insert->execute([
                ':cache_key' => $cacheKey,
                ':rank_position' => $rank,
                ':target_id' => $targetId,
                ':value' => $value,
                ':updated_at' => $now,
            ]);
            $rank++;
        }
    }
}
