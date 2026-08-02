<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/fetcher.php';

/**
 * 热度数值格式化：超过 1 万显示为「万」
 */
function formatHot($n) {
    $n = (int)$n;
    if ($n >= 100000000) {
        return round($n / 100000000, 1) . '亿';
    }
    if ($n >= 10000) {
        return round($n / 10000, 1) . '万';
    }
    return (string)$n;
}

/**
 * 平台图标渲染：若为图片链接则输出 <img>，否则原样输出（兼容旧表情）
 */
function iconHtml($icon) {
    $icon = (string)$icon;
    if (preg_match('#^https?://#i', $icon)) {
        return '<img class="p-ico" src="' . htmlspecialchars($icon, ENT_QUOTES) . '" alt="" loading="lazy" onerror="this.style.display=\'none\'">';
    }
    return htmlspecialchars($icon, ENT_QUOTES);
}

/**
 * 执行全量热榜更新：遍历已启用平台，抓取后替换旧数据
 * 返回结构化结果
 */
function do_update() {
    $pdo = getDB();
    $platforms = $pdo->query("SELECT * FROM platforms WHERE enabled=1 ORDER BY sort_order")->fetchAll();
    $updated = [];
    $failed  = [];
    $now = date('Y-m-d H:i:s');

    foreach ($platforms as $p) {
        $func = 'fetch_' . $p['pkey'];
        if (function_exists($func)) {
            try {
                $items = call_user_func($func);
            } catch (Exception $e) {
                $items = [];
            }
        } else {
            // 无专属抓取函数时，回退到 uapis.cn 通用热榜（pkey 即接口 type）
            $items = fetch_uapis($p['pkey']);
        }
        if (empty($items)) {
            $reason = '';
            if (!empty($GLOBALS['http_last_error'])) {
                $reason = ' (curl错误: ' . $GLOBALS['http_last_error'] . ')';
            } elseif (!empty($GLOBALS['http_last_code']) && $GLOBALS['http_last_code'] >= 400) {
                $reason = ' (HTTP ' . $GLOBALS['http_last_code'] . ')';
            } elseif (!empty($GLOBALS['http_last_code']) && $GLOBALS['http_last_code'] == 0) {
                $reason = ' (无法连接/无响应)';
            }
            $failed[] = $p['name'] . $reason;
            usleep(150000);
            continue;
        }
        // 清空该平台旧数据并写入新数据
        $pdo->prepare("DELETE FROM hot_items WHERE platform_id=?")->execute([$p['id']]);
        $ins = $pdo->prepare("INSERT INTO hot_items (platform_id, rank, title, url, hot, created_at) VALUES (?,?,?,?,?,?)");
        foreach ($items as $it) {
            $ins->execute([$p['id'], $it['rank'], $it['title'], $it['url'], $it['hot'], $now]);
        }
        $updated[$p['name']] = count($items);
        usleep(150000);
    }

    return [
        'time'    => $now,
        'updated' => $updated,
        'failed'  => $failed,
    ];
}

/**
 * 读取 SEO 设置（settings 表），缺失时返回基于站点名称的默认值
 */
function getSeoSettings($pdo = null) {
    if ($pdo === null) {
        $pdo = getDB();
    }
    ensureSettingsTable($pdo);
    $keys = ['seo_title', 'seo_description', 'seo_keywords', 'seo_image'];
    $map = [];
    $st = $pdo->prepare("SELECT v FROM settings WHERE k = ?");
    foreach ($keys as $k) {
        $st->execute([$k]);
        $v = $st->fetchColumn();
        if ($v !== false && $v !== null) {
            $map[$k] = $v;
        }
    }
    return [
        'title'       => $map['seo_title'] ?? SITE_NAME,
        'description' => $map['seo_description'] ?? '聚合微博热搜、百度热搜、知乎热榜、B站、抖音、今日头条等全网热门榜单，实时更新，一站看尽全网热点。',
        'keywords'    => $map['seo_keywords'] ?? '热榜,热门,微博热搜,百度热搜,知乎热榜,全网热榜聚合,实时热点',
        'image'       => $map['seo_image'] ?? '',
    ];
}
