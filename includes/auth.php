<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

session_start();

/**
 * 读取已存储的后台密码哈希（settings 表），无则返回 false
 */
function getAdminHash() {
    static $h = null;
    if ($h !== null) return $h;
    try {
        $pdo = getDB();
        ensureSettingsTable($pdo);
        $st = $pdo->prepare("SELECT v FROM settings WHERE k='admin_pass'");
        $st->execute();
        $h = $st->fetchColumn();
    } catch (Exception $e) {
        $h = false;
    }
    return $h;
}

/**
 * 是否已登录
 */
function isLoggedIn() {
    return !empty($_SESSION['admin']) && $_SESSION['admin'] === true;
}

/**
 * 校验登录：优先使用 settings 中存储的密码哈希，未设置时回退到配置文件默认密码
 */
function attemptLogin($user, $pass) {
    if ($user !== ADMIN_USER) {
        return false;
    }
    $hash = getAdminHash();
    if ($hash) {
        if (password_verify($pass, $hash)) {
            $_SESSION['admin'] = true;
            return true;
        }
        return false;
    }
    if ($pass === ADMIN_PASS) {
        $_SESSION['admin'] = true;
        return true;
    }
    return false;
}

/**
 * 要求登录，否则跳转到登录页
 */
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}
