# 全网今日热榜免费PHP源码
一个轻量的全网平台热榜聚合网站，包含前端展示与后台管理，使用 SQLite 存储，支持宝塔计划任务自动更新。

## 功能

- 前端以板块形式聚合展示各大平台热榜（微博、百度、哔哩哔哩、今日头条、抖音、GitHub、IT之家）
- 首页一行三个板块，每个板块右上角「更多 ›」可进入对应平台完整榜单页
- 前端支持关键词搜索过滤
- 后台账号密码登录，可手动更新热榜、启停平台
- 宝塔「计划任务 - 访问URL」自动拉取最新热榜
- SQLite 单文件数据库，零额外依赖

## 环境要求

- PHP >= 7.1（需开启 `curl`、`pdo_sqlite`、`sqlite3` 扩展）
- 宝塔面板（可选，用于计划任务）

## 部署步骤

1. 将本目录上传到网站根目录（如 `/www/wwwroot/rebang`）。
2. 确保 PHP 已开启扩展：`curl`、`pdo_sqlite`、`sqlite3`。
3. 浏览器访问 `http://域名/index.php` 即可看到前端。
4. 访问 `http://域名/admin/login.php` 进入后台。

### 修改配置（务必）

编辑 `includes/config.php`：

```php
define('ADMIN_USER', 'admin');      // 后台用户名
define('ADMIN_PASS', 'admin123');   // 后台密码（请修改）
define('CRON_TOKEN', 'rebang2026'); // 计划任务令牌（请修改）
```

## 配置宝塔计划任务（自动更新）

1. 打开宝塔面板 → **计划任务**。
2. 添加任务：
   - 任务类型：**访问URL**
   - 任务名称：热榜更新
   - 执行周期：**每 5 分钟**（Nginx/Apache 需能访问外网）
   - 网址：`https://你的域名/cron.php?token=你设置的CRON_TOKEN`
3. 保存后点击「执行」可立即测试，返回 `{"ok":true,...}` 即成功。

> 后台「立即更新热榜」按钮同样可用（需登录会话）。

## 数据库

数据库文件自动创建于 `data/rebang.db`（已通过 `.htaccess` 禁止 Web 访问）。
表结构：`platforms`（平台）、`hot_items`（热榜条目）。

## 新增平台

1. 在 `includes/fetcher.php` 中新增函数 `fetch_xxx()`，返回 `[['rank','title','url','hot'], ...]`。
2. 在 `includes/db.php` 的 `$defaults` 数组中登记相同 `pkey`、名称、图标、排序。
3. 到后台「平台管理」启用即可。

## 目录结构

```
rebang/
├── index.php          # 前端展示
├── cron.php           # 计划任务更新入口
├── api.php            # 通用接口（update/data）
├── admin/             # 后台（login / logout / index）
├── includes/          # 配置、数据库、抓取、认证
├── assets/            # 样式与脚本
└── data/              # SQLite 数据库（自动生成）
```

## 说明

- 所有热榜数据来自各平台公开接口，仅供学习交流。
- 部分平台接口可能随版本变动失效，可在后台看到失败提示，需更新对应 `fetch_xxx()`。
