<?php
require dirname(__DIR__) . '/lib/admin_auth.php';
require dirname(__DIR__) . '/lib/util.php';

if (is_admin_logged_in()) {
    header('Location: /admin/deletion_requests.php');
    exit;
}

$error = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $csrfToken = (string) ($_POST['csrf_token'] ?? '');

    if (!verify_admin_login_csrf_token($csrfToken)) {
        $error = '不正なリクエストです。';
    } elseif (!admin_login($username, $password)) {
        $error = 'ユーザー名またはパスワードが正しくありません。';
    } else {
        header('Location: /admin/deletion_requests.php');
        exit;
    }
}

$token = admin_login_csrf_token();
?>
<!doctype html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <title>Apizm 管理ログイン</title>
    <style>
        body { font-family: sans-serif; padding: 24px; }
        .login-box { max-width: 420px; }
        label { display: block; margin-bottom: 12px; }
        input { width: 100%; box-sizing: border-box; padding: 8px; }
        button { padding: 8px 16px; }
        .error { color: #b00020; margin-bottom: 12px; }
    </style>
</head>
<body>
    <div class="login-box">
        <h1>Apizm 管理ログイン</h1>
        <?php if ($error !== ''): ?>
            <p class="error"><?php echo h($error); ?></p>
        <?php endif; ?>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?php echo h($token); ?>">
            <label>
                ユーザー名
                <input type="text" name="username" value="<?php echo h($username); ?>" required>
            </label>
            <label>
                パスワード
                <input type="password" name="password" required>
            </label>
            <button type="submit">ログイン</button>
        </form>
    </div>
</body>
</html>
