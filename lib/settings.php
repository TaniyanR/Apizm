<?php

function get_setting(PDO $pdo, string $name, ?string $default = null): ?string
{
    $stmt = $pdo->prepare('SELECT value FROM settings WHERE name = :name LIMIT 1');
    $stmt->execute([':name' => $name]);
    $row = $stmt->fetch();
    if (!$row) {
        return $default;
    }

    return (string) $row['value'];
}

function set_setting(PDO $pdo, string $name, string $value): void
{
    $stmt = $pdo->prepare('INSERT INTO settings (name, value) VALUES (:name, :value) ON DUPLICATE KEY UPDATE value = VALUES(value)');
    $stmt->execute([
        ':name' => $name,
        ':value' => $value,
    ]);
}
