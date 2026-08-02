<?php
require_once __DIR__ . '/config.php';

/**
 * 获取（并初始化）SQLite 数据库连接
 */
function getDB() {
    static $pdo = null;
    if ($pdo) return $pdo;

    $dir = dirname(DB_PATH);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $pdo = new PDO('sqlite:' . DB_PATH);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA journal_mode=WAL;');
    initSchema($pdo);
    return $pdo;
}

/**
 * 初始化数据表与默认平台
 */
function initSchema($pdo) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS platforms (
        id          INTEGER PRIMARY KEY AUTOINCREMENT,
        pkey        TEXT UNIQUE,
        name        TEXT,
        icon        TEXT,
        enabled     INTEGER DEFAULT 1,
        sort_order  INTEGER DEFAULT 0
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS hot_items (
        id          INTEGER PRIMARY KEY AUTOINCREMENT,
        platform_id INTEGER,
        rank        INTEGER DEFAULT 0,
        title       TEXT,
        url         TEXT,
        hot         INTEGER DEFAULT 0,
        created_at  TEXT,
        FOREIGN KEY(platform_id) REFERENCES platforms(id)
    )");

    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_hot_platform ON hot_items(platform_id)");

    $pdo->exec("CREATE TABLE IF NOT EXISTS hot_history (
        id          INTEGER PRIMARY KEY AUTOINCREMENT,
        platform_id INTEGER,
        rank        INTEGER DEFAULT 0,
        title       TEXT,
        hot         INTEGER DEFAULT 0,
        snapshot_at TEXT,
        FOREIGN KEY(platform_id) REFERENCES platforms(id)
    )");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_hist_platform ON hot_history(platform_id)");

    // 站点设置（用于存放后台登录密码哈希等可动态修改项）
    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
        k TEXT PRIMARY KEY,
        v TEXT
    )");


    // 默认内置平台
    // pkey 即数据源标识：自带直连接口的平台（weibo/baidu/bilibili/douyin/toutiao/github）
    // 与 zhihu/ithome 写有专属 fetch_ 函数；其余平台统一走 uapis.cn 通用热榜，pkey 即其 type
    // icon 使用各平台官方站点 favicon 链接
    $defaults = [
        ['weibo',         '微博热搜',     'https://weibo.com/favicon.ico', 1],
        ['baidu',         '百度热搜',     'https://www.baidu.com/favicon.ico', 2],
        ['zhihu',         '知乎热榜',     'https://www.zhihu.com/favicon.ico', 3],
        ['bilibili',      '哔哩哔哩',     'https://www.bilibili.com/favicon.ico', 4],
        ['toutiao',       '今日头条',     'https://www.toutiao.com/favicon.ico', 5],
        ['douyin',        '抖音热点',     'https://www.douyin.com/favicon.ico', 6],
        ['github',        'GitHub',       'https://github.com/favicon.ico', 8],
        ['ithome',        'IT之家',       'https://www.ithome.com/favicon.ico', 9],
        // —— 以下均通过 uapis.cn 通用热榜获取（pkey = type）——
        ['acfun',         'A站',          'https://www.acfun.cn/favicon.ico', 10],
        ['zhihu-daily',   '知乎日报',     'https://daily.zhihu.com/favicon.ico', 11],
        ['xiaohongshu',   '小红书',       'https://www.xiaohongshu.com/favicon.ico', 12],
        ['kuaishou',      '快手',         'https://www.kuaishou.com/favicon.ico', 13],
        ['douban-movie',  '豆瓣电影',     'https://movie.douban.com/favicon.ico', 14],
        ['douban-group',  '豆瓣小组',     'https://www.douban.com/favicon.ico', 15],
        ['tieba',         '百度贴吧',     'https://tieba.baidu.com/favicon.ico', 16],
        ['hupu',          '虎扑',         'https://www.hupu.com/favicon.ico', 17],
        ['ngabbs',        'NGA论坛',      'https://nga.178.com/favicon.ico', 18],
        ['v2ex',          'V2EX',         'https://www.v2ex.com/favicon.ico', 19],
        ['52pojie',       '吾爱破解',     'https://www.52pojie.cn/favicon.ico', 20],
        ['coolapk',       '酷安',         'https://www.coolapk.com/favicon.ico', 21],
        ['thepaper',      '澎湃新闻',     'https://www.thepaper.cn/favicon.ico', 22],
        ['qq-news',       '腾讯新闻',     'https://news.qq.com/favicon.ico', 23],
        ['sina',          '新浪热搜',     'https://hot.sina.com.cn/favicon.ico', 24],
        ['sina-news',     '新浪新闻',     'https://news.sina.com.cn/favicon.ico', 25],
        ['netease-news',  '网易新闻',     'https://news.163.com/favicon.ico', 26],
        ['huxiu',         '虎嗅',         'https://www.huxiu.com/favicon.ico', 27],
        ['ifanr',         '爱范儿',       'https://www.ifanr.com/favicon.ico', 28],
        ['sspai',         '少数派',       'https://sspai.com/favicon.ico', 29],
        ['ithome-xijiayi','IT之家喜加一', 'https://www.ithome.com/favicon.ico', 30],
        ['juejin',        '掘金',         'https://juejin.cn/favicon.ico', 31],
        ['jianshu',       '简书',         'https://www.jianshu.com/favicon.ico', 32],
        ['guokr',         '果壳',         'https://www.guokr.com/favicon.ico', 33],
        ['36kr',          '36氪',         'https://36kr.com/favicon.ico', 34],
        ['51cto',         '51CTO',        'https://www.51cto.com/favicon.ico', 35],
        ['csdn',          'CSDN',         'https://www.csdn.net/favicon.ico', 36],
        ['nodeseek',      'NodeSeek',     'https://www.nodeseek.com/favicon.ico', 37],
        ['nodeseek-hot',  'NodeSeek热门', 'https://www.nodeseek.com/favicon.ico', 38],
        ['hellogithub',   'HelloGitHub',  'https://hellogithub.com/favicon.ico', 39],
        ['lol',           '英雄联盟',     'https://lol.qq.com/favicon.ico', 40],
        ['genshin',       '原神',         'https://ys.mihoyo.com/favicon.ico', 41],
        ['honkai',        '崩坏3',        'https://bk.mihoyo.com/favicon.ico', 42],
        ['starrail',      '星穹铁道',     'https://sr.mihoyo.com/favicon.ico', 43],
        ['netease-music', '网易云音乐热歌榜', 'https://music.163.com/favicon.ico', 44],
        ['qq-music',      'QQ音乐热歌榜', 'https://y.qq.com/favicon.ico', 45],
        ['weread',        '微信读书',     'https://weread.qq.com/favicon.ico', 46],
        ['weatheralarm',  '天气预警',     'https://www.weather.com.cn/favicon.ico', 47],
        ['earthquake',    '地震速报',     'https://www.ceic.ac.cn/favicon.ico', 48],
        ['history',       '历史上的今天', 'https://baike.baidu.com/favicon.ico', 49],
    ];
    $stmt = $pdo->prepare("INSERT OR IGNORE INTO platforms (pkey, name, icon, sort_order) VALUES (?, ?, ?, ?)");
    foreach ($defaults as $d) {
        $stmt->execute($d);
    }
    // 已部署站点 icon 仍是旧表情：将已有记录同步为官方 favicon 链接
    $logos = [];
    foreach ($defaults as $d) { $logos[$d[0]] = $d[2]; }
    $upd = $pdo->prepare("UPDATE platforms SET icon=? WHERE pkey=?");
    foreach ($logos as $k => $u) { $upd->execute([$u, $k]); }
    // 已移除的平台（无法稳定获取）：禁用旧记录，避免残留数据展示
    $pdo->exec("UPDATE platforms SET enabled=0 WHERE pkey IN ('kr36')");
}

/**
 * 确保 settings 表存在（兼容线上 db.php 尚未包含建表逻辑的部署环境）
 */
function ensureSettingsTable($pdo = null) {
    if ($pdo === null) {
        $pdo = getDB();
    }
    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (k TEXT PRIMARY KEY, v TEXT)");
}
