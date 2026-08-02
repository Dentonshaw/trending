<?php
/**
 * 通用接口
 *  - action=update : 更新热榜（需 token 或登录会话），供后台手动更新使用
 *  - action=data   : 返回当前热榜数据（前端异步刷新）
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? '';

if ($action === 'update') {
    $ok = false;
    if (isset($_GET['token']) && $_GET['token'] === CRON_TOKEN) {
        $ok = true;
    } elseif (isLoggedIn()) {
        $ok = true;
    }
    if (!$ok) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'msg' => '未授权']);
        exit;
    }
    $res = do_update();
    echo json_encode(['ok' => true, 'data' => $res]);
    exit;
}

if ($action === 'data') {
    $pdo = getDB();
    $platforms = $pdo->query("SELECT id, pkey, name, icon FROM platforms WHERE enabled=1 ORDER BY sort_order")->fetchAll();
    $out = [];
    foreach ($platforms as $p) {
        $st = $pdo->prepare("SELECT rank, title, url, hot FROM hot_items WHERE platform_id=? ORDER BY rank");
        $st->execute([$p['id']]);
        $out[] = [
            'platform' => $p,
            'items'    => $st->fetchAll(),
        ];
    }
    $last = $pdo->query("SELECT MAX(created_at) AS t FROM hot_items")->fetchColumn();
    echo json_encode(['ok' => true, 'last_update' => $last, 'data' => $out]);
    exit;
}

http_response_code(404);
echo json_encode(['ok' => false, 'msg' => '未知操作']);
