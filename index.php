<?php
require_once __DIR__ . '/includes/functions.php';

$pdo = getDB();
$seo = getSeoSettings($pdo);
$platforms = $pdo->query("SELECT * FROM platforms WHERE enabled=1 ORDER BY sort_order")->fetchAll();
$last = $pdo->query("SELECT MAX(created_at) AS t FROM hot_items")->fetchColumn();
$data = [];
foreach ($platforms as $p) {
    $st = $pdo->prepare("SELECT rank, title, url, hot FROM hot_items WHERE platform_id=? ORDER BY rank");
    $st->execute([$p['id']]);
    $data[$p['id']] = $st->fetchAll();
}
$boardLimit = 12;
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo htmlspecialchars($seo['title']); ?></title>
<meta name="description" content="<?php echo htmlspecialchars($seo['description']); ?>">
<meta name="keywords" content="<?php echo htmlspecialchars($seo['keywords']); ?>">
<meta name="author" content="<?php echo htmlspecialchars(SITE_NAME); ?>">
<meta name="robots" content="index,follow">
<link rel="canonical" href="https://<?php echo htmlspecialchars($_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']); ?>">
<meta property="og:type" content="website">
<meta property="og:site_name" content="<?php echo htmlspecialchars(SITE_NAME); ?>">
<meta property="og:title" content="<?php echo htmlspecialchars($seo['title']); ?>">
<meta property="og:description" content="<?php echo htmlspecialchars($seo['description']); ?>">
<meta property="og:url" content="https://<?php echo htmlspecialchars($_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']); ?>">
<?php if ($seo['image']): ?><meta property="og:image" content="<?php echo htmlspecialchars($seo['image']); ?>">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?php echo htmlspecialchars($seo['title']); ?>">
<meta name="twitter:description" content="<?php echo htmlspecialchars($seo['description']); ?>">
<meta name="twitter:image" content="<?php echo htmlspecialchars($seo['image']); ?>"><?php endif; ?>
<link rel="icon" href="assets/favicon.svg" type="image/svg+xml">
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<header class="topbar">
    <div class="topbar-inner">
        <div class="brand"><?php echo SITE_NAME; ?></div>
        <span class="search-wrap">
            <svg class="search-icon" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input id="searchInput" class="search" placeholder="搜索热榜…" autocomplete="off">
        </span>
        <div class="meta">
            <span id="lastUpdate">最后更新：<?php echo $last ? htmlspecialchars($last) : '暂无数据'; ?></span>
            <button id="settingsBtn" class="btn-mini" title="个性化设置">⚙ 设置</button>
            <button id="refreshBtn" class="btn-mini">刷新</button>
        </div>
    </div>
</header>

<main class="container">
    <div class="board-grid">
        <?php foreach ($platforms as $p): ?>
        <section class="board" id="board-<?php echo $p['pkey']; ?>" data-pkey="<?php echo $p['pkey']; ?>">
            <div class="board-head">
                <h2><?php echo iconHtml($p['icon']); ?><span class="p-name"><?php echo htmlspecialchars($p['name']); ?></span></h2>
                <a class="more-btn" href="board.php?pkey=<?php echo urlencode($p['pkey']); ?>">更多 ›</a>
            </div>
            <ol class="list">
                <?php $items = $data[$p['id']] ?? []; if (empty($items)): ?>
                    <li class="empty">暂无数据，请等待计划任务更新或到后台手动更新。</li>
                <?php else: foreach (array_slice($items, 0, $boardLimit) as $it): ?>
                    <li class="item">
                        <span class="rank rank-<?php echo $it['rank'] <= 3 ? $it['rank'] : 'n'; ?>"><?php echo $it['rank']; ?></span>
                        <a class="title" href="<?php echo htmlspecialchars($it['url']); ?>" target="_blank" rel="noopener"><?php echo htmlspecialchars($it['title']); ?></a>
                        <?php if ($it['hot'] > 0): ?><span class="hot"><?php echo formatHot($it['hot']); ?></span><?php endif; ?>
                    </li>
                <?php endforeach; endif; ?>
            </ol>
        </section>
        <?php endforeach; ?>
    </div>
</main>

<footer class="foot">数据来自各平台公开接口，仅供学习交流使用。</footer>

<div id="settingsModal" class="modal" hidden>
    <div class="modal-mask"></div>
    <div class="modal-panel">
        <div class="modal-head">
            <h3>个性化设置</h3>
            <button id="settingsClose" class="modal-x" title="关闭">×</button>
        </div>
        <div class="modal-body">
            <div class="set-row theme-row">
                <span>深色模式</span>
                <label class="switch"><input type="checkbox" id="themeToggle"><span class="slider"></span></label>
            </div>
            <p class="set-tip">拖拽调整板块顺序，关闭不想显示的板块。设置仅保存在本机浏览器。</p>
            <ul id="platList" class="plat-list"></ul>
        </div>
        <div class="modal-foot">
            <button id="resetBtn" class="btn-danger">重置所有数据</button>
            <button id="settingsDone" class="btn">完成</button>
        </div>
    </div>
</div>

<script>
window.__PLATFORMS__ = <?php
    $platJson = [];
    foreach ($platforms as $p) {
        $platJson[] = ['pkey' => $p['pkey'], 'name' => $p['name'], 'icon' => $p['icon']];
    }
    echo json_encode($platJson, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
?>;
</script>
<script src="assets/app.js"></script>
</body>
</html>