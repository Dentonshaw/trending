<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (attemptLogin($_POST['user'] ?? '', $_POST['pass'] ?? '')) {
        header('Location: index.php');
        exit;
    }
    $error = '用户名或密码错误';
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>登录 · <?php echo SITE_NAME; ?>后台</title>
<link rel="icon" href="../assets/favicon.svg" type="image/svg+xml">
<link rel="stylesheet" href="../assets/style.css">
</head>
<body>
<div class="login-wrap">
    <form class="login-box" method="post">
        <h1>后台登录</h1>
        <?php if ($error): ?><div class="alert"><?php echo $error; ?></div><?php endif; ?>
        <input name="user" placeholder="用户名" autocomplete="username" required>
        <input name="pass" type="password" placeholder="密码" autocomplete="current-password" required>
        <button type="submit">登录</button>
        <a class="back" href="../index.php">← 返回首页</a>
    </form>
</div>
</body>
</html>
