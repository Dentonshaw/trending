<?php
// ========== 网站配置（部署前请务必修改） ==========

// 站点名称
define('SITE_NAME', '全网热榜');

// SQLite 数据库文件路径
define('DB_PATH', __DIR__ . '/../data/rebang.db');

// 后台管理员账号（请务必修改）
define('ADMIN_USER', 'admin');
define('ADMIN_PASS', 'admin123');

// 计划任务令牌：宝塔访问URL时带上 ?token=此值（请务必修改）
define('CRON_TOKEN', 'rebang2026');

// 抓取时使用的浏览器标识
define('UA', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
