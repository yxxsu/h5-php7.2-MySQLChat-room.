<?php
// 检查是否已安装
if (!file_exists('config.php')) {
    header("Location: install.php");
    exit;
}

define('IN_CHAT', true);
require_once 'config.php';

// 如果用户已登录
if (isset($_SESSION['user_id'])) {
    header("Location: chat.php");
    exit;
}

// 如果用户未登录
header("Location: login.php");
exit; 