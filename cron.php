<?php
/**
 * 计划任务入口（宝塔「访问URL」类型调用此文件）
 * 访问方式：https://你的域名/cron.php?token=你的令牌
 */
require_once __DIR__ . '/includes/functions.php';

$token = $_GET['token'] ?? '';
if ($token !== CRON_TOKEN) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'msg' => 'token 错误']);
    exit;
}

$res = do_update();
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['ok' => true, 'data' => $res]);
