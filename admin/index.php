<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();

$pdo = getDB();
$message = '';

// 启用 / 停用平台
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle'])) {
    $id = (int)($_POST['id'] ?? 0);
    $pdo->prepare("UPDATE platforms SET enabled = 1 - enabled WHERE id=?")->execute([$id]);
    header('Location: index.php');
    exit;
}

// 手动更新热榜
if (isset($_POST['update'])) {
    $res = do_update();
    $ok = count($res['updated']);
    $fail = count($res['failed']);
    $message = '更新完成（' . ($res['time'] ?? '') . '），成功 ' . $ok . ' 个，失败 ' . $fail . ' 个'
        . ($fail ? '：' . implode('、', $res['failed']) : '');
    if ($fail) $message .= '（失败可能是平台接口变动或网络问题）';
}

// 修改后台密码
$pmessage = '';
$pok = false;
if (isset($_POST['change_pass'])) {
    $old = $_POST['old_pass'] ?? '';
    $new = $_POST['new_pass'] ?? '';
    $confirm = $_POST['confirm_pass'] ?? '';
    if (!attemptLogin(ADMIN_USER, $old)) {
        $pmessage = '原密码错误';
    } elseif ($new === '' || $new !== $confirm) {
        $pmessage = '两次输入的新密码不一致或为空';
    } else {
        $hash = password_hash($new, PASSWORD_DEFAULT);
        ensureSettingsTable($pdo);
        $pdo->prepare("INSERT OR REPLACE INTO settings (k, v) VALUES ('admin_pass', ?)")
            ->execute([$hash]);
        $pmessage = '密码修改成功，请使用新密码登录';
        $pok = true;
    }
}

$platforms = $pdo->query("SELECT * FROM platforms ORDER BY sort_order")->fetchAll();
$last = $pdo->query("SELECT MAX(created_at) AS t FROM hot_items")->fetchColumn();

// SEO 设置保存
$seoMessage = '';
$seoOk = false;
if (isset($_POST['save_seo'])) {
    ensureSettingsTable($pdo);
    $fields = ['seo_title' => 'seo_title', 'seo_description' => 'seo_description', 'seo_keywords' => 'seo_keywords', 'seo_image' => 'seo_image'];
    $ins = $pdo->prepare("INSERT OR REPLACE INTO settings (k, v) VALUES (?, ?)");
    foreach ($fields as $post => $key) {
        $val = trim((string)($_POST[$post] ?? ''));
        $ins->execute([$key, $val]);
    }
    $seoMessage = 'SEO 设置已保存';
    $seoOk = true;
}
$seo = getSeoSettings($pdo);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>后台管理 · <?php echo SITE_NAME; ?></title>
<link rel="icon" href="../assets/favicon.svg" type="image/svg+xml">
<link rel="stylesheet" href="../assets/style.css">
</head>
<body>
<header class="topbar">
    <div class="topbar-inner">
        <div class="brand">后台管理</div>
        <div class="meta">
            <span>最后更新：<?php echo $last ? htmlspecialchars($last) : '暂无'; ?></span>
            <a class="btn-mini" href="../index.php" target="_blank">查看前台</a>
            <a class="btn-mini" href="logout.php">退出</a>
        </div>
    </div>
</header>

<main class="container admin">
    <div class="card">
        <h2>数据更新</h2>
        <p class="hint">宝塔计划任务会按设定周期自动调用 <code>cron.php?token=...</code> 更新数据。也可在此手动触发：</p>
        <form method="post" style="display:inline-block">
            <button type="submit" name="update" value="1" class="btn">立即更新热榜</button>
        </form>
        <?php if ($message): ?><div class="alert alert-ok"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
    </div>

    <div class="card">
        <h2>SEO 设置</h2>
        <p class="hint">配置站点标题、描述、关键词与分享缩略图，用于搜索引擎及各平台（微信 / 微博 / QQ 等）分享卡片收录。</p>
        <form method="post" class="pass-form">
            <label for="seo_title">站点标题</label>
            <input type="text" id="seo_title" name="seo_title" value="<?php echo htmlspecialchars($seo['title']); ?>" placeholder="全网热榜">
            <label for="seo_description">站点描述</label>
            <textarea id="seo_description" name="seo_description" rows="3" placeholder="一句话描述站点内容"><?php echo htmlspecialchars($seo['description']); ?></textarea>
            <label for="seo_keywords">关键词（英文逗号分隔）</label>
            <input type="text" id="seo_keywords" name="seo_keywords" value="<?php echo htmlspecialchars($seo['keywords']); ?>" placeholder="热榜,热门,微博热搜">
            <label for="seo_image">分享缩略图 URL（可选）</label>
            <input type="url" id="seo_image" name="seo_image" value="<?php echo htmlspecialchars($seo['image']); ?>" placeholder="https://...">
            <button type="submit" name="save_seo" value="1">保存 SEO 设置</button>
        </form>
        <?php if ($seoMessage): ?><div class="alert <?php echo $seoOk ? 'alert-ok' : ''; ?>"><?php echo htmlspecialchars($seoMessage); ?></div><?php endif; ?>
    </div>

    <div class="card">
        <h2>平台管理</h2>
        <table class="ptable">
            <thead>
                <tr><th>图标</th><th>名称</th><th>标识</th><th>状态</th><th>操作</th></tr>
            </thead>
            <tbody>
                <?php foreach ($platforms as $p): ?>
                <tr>
                    <td><?php echo iconHtml($p['icon']); ?></td>
                    <td><?php echo htmlspecialchars($p['name']); ?></td>
                    <td><code><?php echo $p['pkey']; ?></code></td>
                    <td><?php echo $p['enabled'] ? '<span class="on">已启用</span>' : '<span class="off">已停用</span>'; ?></td>
                    <td>
                        <form method="post" style="display:inline-block">
                            <input type="hidden" name="id" value="<?php echo $p['id']; ?>">
                            <button type="submit" name="toggle" value="1" class="btn-mini">
                                <?php echo $p['enabled'] ? '停用' : '启用'; ?>
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="card">
        <h2>修改密码</h2>
        <p class="hint">修改后将覆盖配置文件中的默认密码，请牢记新密码。</p>
        <form method="post" class="pass-form">
            <input type="password" name="old_pass" placeholder="原密码" autocomplete="current-password" required>
            <input type="password" name="new_pass" placeholder="新密码" autocomplete="new-password" required>
            <input type="password" name="confirm_pass" placeholder="确认新密码" autocomplete="new-password" required>
            <button type="submit" name="change_pass" value="1">保存密码</button>
        </form>
        <?php if ($pmessage): ?><div class="alert <?php echo $pok ? 'alert-ok' : ''; ?>"><?php echo htmlspecialchars($pmessage); ?></div><?php endif; ?>
    </div>

    <div class="card">
        <h2>计划任务配置</h2>
        <p class="hint">在宝塔面板「计划任务」中添加：</p>
        <ul class="steps">
            <li>任务类型：<b>访问URL</b></li>
            <li>任务名称：热榜更新</li>
            <li>执行周期：<b>每 5 分钟</b>（或自定义）</li>
            <li>URL：<code>https://你的域名/cron.php?token=<?php echo CRON_TOKEN; ?></code></li>
        </ul>
        <p class="hint">如需新增平台，在 <code>includes/fetcher.php</code> 添加 <code>fetch_xxx()</code> 函数，并在 <code>db.php</code> 默认平台中登记相同 <code>pkey</code> 即可。</p>
    </div>
</main>
</body>
</html>
