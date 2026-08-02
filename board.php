<?php
require_once __DIR__ . '/includes/functions.php';

$pkey = $_GET['pkey'] ?? '';
$pdo = getDB();
$st = $pdo->prepare("SELECT * FROM platforms WHERE pkey=? AND enabled=1");
$st->execute([$pkey]);
$platform = $st->fetch();
if (!$platform) {
    http_response_code(404);
    ?>
    <!DOCTYPE html>
    <html lang="zh-CN"><head><meta charset="utf-8"><title>未找到</title>
    <link rel="icon" href="assets/favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="assets/style.css"></head>
    <body><header class="topbar"><div class="topbar-inner">
    <div class="brand">未找到该板块</div>
    <a class="btn-mini" href="index.php">返回首页</a>
    </div></header>
    <footer class="foot">平台不存在或已禁用。</footer></body></html>
    <?php
    exit;
}
$seo = [
    'title'       => $platform['name'] . ' · ' . SITE_NAME,
    'description' => $platform['name'] . '实时热榜，聚合' . $platform['name'] . '热门内容，一站看尽全网热点。',
];
$st = $pdo->prepare("SELECT rank, title, url, hot FROM hot_items WHERE platform_id=? ORDER BY rank");
$st->execute([$platform['id']]);
$items = $st->fetchAll();
$last = $pdo->query("SELECT MAX(created_at) AS t FROM hot_items WHERE platform_id=" . (int)$platform['id'])->fetchColumn();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo htmlspecialchars($seo['title']); ?></title>
<meta name="description" content="<?php echo htmlspecialchars($seo['description']); ?>">
<meta name="keywords" content="<?php echo htmlspecialchars($seo['keywords'] ?? ''); ?>">
<meta name="robots" content="index,follow">
<meta property="og:type" content="website">
<meta property="og:site_name" content="<?php echo htmlspecialchars(SITE_NAME); ?>">
<meta property="og:title" content="<?php echo htmlspecialchars($seo['title']); ?>">
<meta property="og:description" content="<?php echo htmlspecialchars($seo['description']); ?>">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?php echo htmlspecialchars($seo['title']); ?>">
<meta name="twitter:description" content="<?php echo htmlspecialchars($seo['description']); ?>">
<link rel="icon" href="assets/favicon.svg" type="image/svg+xml">
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<header class="topbar">
    <div class="topbar-inner">
        <div class="brand">
            <a class="back-link" href="index.php" title="返回首页">‹</a>
            <a class="brand-home" href="index.php"><?php echo SITE_NAME; ?></a>
        </div>
        <div class="meta">
            <button id="refreshBtn" class="btn-mini">刷新</button>
        </div>
    </div>
</header>

<main class="container">
    <section class="board-detail">
        <div class="detail-hero">
            <?php echo iconHtml($platform['icon']); ?>
            <div class="detail-hero-text">
                <h1><?php echo htmlspecialchars($platform['name']); ?></h1>
                <div class="detail-sub">
                    <span class="detail-count"><?php echo count($items); ?> 条</span>
                    <span class="detail-dot">·</span>
                    <span id="lastUpdate">最后更新：<?php echo $last ? htmlspecialchars($last) : '暂无数据'; ?></span>
                </div>
            </div>
        </div>
        <ol class="list">
            <?php if (empty($items)): ?>
                <li class="empty">暂无数据，请等待计划任务更新或到后台手动更新。</li>
            <?php else: foreach ($items as $it): ?>
                <li class="item">
                    <span class="rank rank-<?php echo $it['rank'] <= 3 ? $it['rank'] : 'n'; ?>"><?php echo $it['rank']; ?></span>
                    <a class="title" href="<?php echo htmlspecialchars($it['url']); ?>" target="_blank" rel="noopener"><?php echo htmlspecialchars($it['title']); ?></a>
                    <?php if ($it['hot'] > 0): ?><span class="hot"><?php echo formatHot($it['hot']); ?></span><?php endif; ?>
                </li>
            <?php endforeach; endif; ?>
        </ol>
    </section>
</main>

<footer class="foot">数据来自各平台公开接口，仅供学习交流使用。</footer>
<script src="assets/app.js"></script>
</body>
</html>
