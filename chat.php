<?php
define('IN_CHAT', true);
require_once 'config.php';
// 检查用户是否登录
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
// 检查用户状态
$stmt = $pdo->prepare("SELECT status FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();
if (!$user || $user['status'] == 0) {
    session_destroy();
    header('Location: login.php?error=' . urlencode('您的账号已被禁用或删除'));
    exit;
}
// 更新在线状态
$stmt = $pdo->prepare("
    INSERT INTO online_users (user_id, last_active) 
    VALUES (?, NOW()) 
    ON DUPLICATE KEY UPDATE last_active = NOW()
");
$stmt->execute([$_SESSION['user_id']]);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>聊天室 - 在线聊天</title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<style>
/* 全局CSS变量 */
:root {
    --my-bubble-color: #12B886;
    --other-bubble-color: #ffffff;
    --my-bubble-text: #ffffff;
    --other-bubble-text: #212529;
    --bg-opacity: 0.15;
    --code-bg-light: #f1f3f5;
    --code-bg-dark: #2a2a3d;
    --danger-color: #12B886;
    --send-box-bg: rgba(240, 253, 249, 0.9);
    /* 侧边栏纯白（浅色） */
    --sidebar-light-bg: #ffffff;
    /* 侧边栏深色背景 */
    --sidebar-dark-bg: #2d2d44;
    /* 设置弹窗浅色/深色变量 */
    --setting-light-bg: #ffffff;
    --setting-dark-bg: #2d2d44;
    --setting-light-text: #212529;
    --setting-dark-text: #e9ecef;
    --setting-light-border: #eee;
    --setting-dark-border: #444466;
    /* 新增：纯色背景变量 */
    --solid-bg-color: transparent;
}
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}
body {
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    background: var(--solid-bg-color, #F8F9FA);
    height: 100vh;
    overflow: hidden;
    color: #212529;
    transition: background 0.3s;
    position: relative;
}
/* 背景图遮罩层 【已删除blur模糊】 */
body::before {
    content: "";
    position: fixed;
    inset: 0;
    z-index: -1;
    background-image: var(--chat-bg-img, none);
    background-size: cover;
    background-position: center;
    background-repeat: no-repeat;
    opacity: var(--bg-opacity);
    pointer-events: none;
    filter: none;
}
/* 深色模式全局样式 */
body.dark {
    background: #1a1a2e;
    color: #e9ecef;
    --other-bubble-color: #2d2d44;
    --other-bubble-text: #e9ecef;
    --send-box-bg: rgba(26, 42, 38, 0.85);
}
body.dark .header {
    background: rgba(36, 36, 59, 0.92);
    border-bottom: 1px solid #33334d;
}
body.dark .menu-btn {
    background: #33334d;
    color: #ced4da;
}
body.dark .chat-box .msg-list {
    background: transparent;
}
body.dark .send-box {
    background: var(--send-box-bg);
    border-top: 1px solid #33334d;
}
body.dark .send-input {
    background: #2d2d44;
    border-color: #444466;
    color: #e9ecef;
}
body.dark .sidebar,
body.dark .online-panel {
    background: var(--sidebar-dark-bg);
}
body.dark .side-item:hover,
body.dark .online-item:hover {
    background: rgba(255,255,255,0.08);
}
body.dark .online-loc {
    color: #adb5bd;
}
body.dark .msg-name {
    color: #adb5bd;
}
body.dark .code-block {
    background: var(--code-bg-dark);
}
/* 顶部导航 */
.header {
    height: 56px;
    background: rgba(255,255,255,0.92);
    border-bottom: 1px solid #E9ECEF;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 16px;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    z-index: 999;
    transition: background 0.3s;
}
.header-left {
    display: flex;
    align-items: center;
    gap: 12px;
}
.header-right {
    display: flex;
    align-items: center;
    gap: 8px;
}
.menu-btn {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    border: none;
    background: #F1F3F5;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #495057;
    cursor: pointer;
    transition: background 0.3s;
}
.title {
    font-size: 17px;
    font-weight: 600;
}
.online-btn {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    border: none;
    background: var(--my-bubble-color);
    color: #fff;
    position: relative;
    cursor: pointer;
}
.online-badge {
    position: absolute;
    top: -4px;
    right: -4px;
    width: 18px;
    height: 18px;
    background: var(--danger-color);
    color: #fff;
    border-radius: 50%;
    font-size: 11px;
    display: flex;
    align-items: center;
    justify-content: center;
}
/* 主布局 */
.wrap {
    margin-top: 56px;
    height: calc(100vh - 56px);
    display: flex;
    position: relative;
}
/* 消息区域 */
.chat-box {
    flex: 1;
    display: flex;
    flex-direction: column;
    height: 100%;
}
.msg-list {
    flex: 1;
    padding: 16px;
    overflow-y: auto;
    background: rgba(248, 249, 250, 0.75);
    transition: background 0.3s;
}
.msg-item {
    display: flex;
    margin-bottom: 16px;
    max-width: 75%;
}
.msg-me {
    margin-left: auto;
    flex-direction: row-reverse;
}
.msg-avatar {
    width: 38px;
    height: 38px;
    border-radius: 50%;
    object-fit: cover;
}
.msg-content {
    background: var(--other-bubble-color);
    color: var(--other-bubble-text);
    padding: 10px 12px;
    border-radius: 14px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.08);
    position: relative;
    transition: background 0.3s;
}
.msg-me .msg-content {
    background: var(--my-bubble-color);
    color: var(--my-bubble-text);
}
.msg-name {
    font-size: 12px;
    color: #868E96;
    margin-bottom: 4px;
    font-weight 500;
    transition: color 0.3s;
}
.msg-me .msg-name {
    text-align: right;
    color: rgba(255,255,255,0.7);
}
.msg-text {
    font-size: 15px;
    line-height: 1.4;
    word-break: break-word;
}
/* 代码块样式 */
.code-block {
    background: var(--code-bg-light);
    margin: 8px 0;
    padding: 10px;
    border-radius: 8px;
    font-family: Consolas,monospace;
    font-size: 13px;
    white-space: pre-wrap;
    overflow-x: auto;
    position: relative;
}
.msg-me .code-block {
    color: #222;
}
.copy-code {
    position: absolute;
    top: 6px;
    right: 6px;
    border: none;
    background: rgba(0,0,0,0.1);
    color: inherit;
    padding: 2px 6px;
    border-radius: 4px;
    font-size: 12px;
    cursor: pointer;
}
body.dark .copy-code {
    background: rgba(255,255,255,0.1);
}
.admin-tag {
    display: inline-block;
    background: var(--danger-color);
    color: #fff;
    font-size: 11px;
    padding: 1px 6px;
    border-radius: 8px;
    margin-left: 6px;
}
.msg-me .admin-tag {
    background: rgba(255,255,255,0.3);
}
.del-btn {
    position: absolute;
    top: 4px;
    right: 6px;
    font-size: 12px;
    color: rgba(0,0,0,0.3);
    background: none;
    border: none;
    cursor: pointer;
    display: none;
}
.msg-content:hover .del-btn {
    display: block;
}
.msg-me .del-btn {
    color: rgba(255,255,255,0.5);
    left: 6px;
    right: auto;
}
/* 输入框底部栏 */
.send-box {
    padding: 10px 12px;
    background: var(--send-box-bg);
    border-top: 1px solid #E9ECEF;
    transition: background 0.3s;
    position: relative;
}
.send-area {
    display: flex;
    gap: 8px;
    align-items: flex-end;
}
.send-input {
    flex: 1;
    min-height: 40px;
    max-height: 100px;
    border: 1px solid #DEE2E6;
    border-radius: 20px;
    padding: 10px 16px;
    font-size: 15px;
    outline: none;
    resize: none;
    transition: background 0.3s, border-color 0.3s, color 0.3s;
}
.send-input:focus {
    border-color: var(--my-bubble-color);
}
.send-btn {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: var(--my-bubble-color);
    color: #fff;
    border: none;
    flex-shrink: 0;
    cursor: pointer;
}
/* 左侧侧边栏 —— 浅色纯白 */
.sidebar {
    position: fixed;
    top: 56px;
    left: -280px;
    width: 280px;
    height: calc(100vh - 56px);
    background: var(--sidebar-light-bg);
    box-shadow: 2px 0 8px rgba(0,0,0,0.05);
    transition: left 0.25s ease, background 0.3s;
    z-index: 998;
    padding: 20px;
}
.sidebar.show {
    left: 0;
}
.side-item {
    display: flex;
    align-items: center;
    gap: 12px;
    height: 44px;
    padding: 0 12px;
    border-radius: 10px;
    color: #212529;
    text-decoration: none;
    margin-bottom: 6px;
    transition: background 0.3s;
}
body.dark .side-item {
    color: #e9ecef;
}
.side-item:hover {
    background: #F1F3F5;
}
/* 右侧在线面板 —— 浅色纯白 */
.online-panel {
    position: fixed;
    top: 56px;
    right: -320px;
    width: 320px;
    height: calc(100vh - 56px);
    background: var(--sidebar-light-bg);
    box-shadow: -2px 0 8px rgba(0,0,0,0.05);
    transition: right 0.25s ease, background 0.3s;
    z-index: 998;
    padding: 16px;
}
.online-panel.show {
    right: 0;
}
/* ========== 修复后的设置弹窗 ========== */
.setting-panel {
    position: fixed;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 380px;
    max-height: 85vh;
    overflow-y: auto;
    background: var(--setting-light-bg);
    color: var(--setting-light-text);
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.15);
    z-index: 9999;
    padding: 20px;
    display: none;
    transition: background 0.3s, color 0.3s;
}
.setting-panel.show {
    display: block;
}
/* 深色模式设置弹窗适配 */
body.dark .setting-panel {
    background: var(--setting-dark-bg);
    color: var(--setting-dark-text);
}
.setting-title {
    font-size: 18px;
    font-weight: 600;
    margin-bottom: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.close-setting {
    border: none;
    background: none;
    font-size: 20px;
    cursor: pointer;
    color: #868E96;
}
.theme-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 0;
    border-bottom: 1px solid var(--setting-light-border);
}
body.dark .theme-row {
    border-bottom-color: var(--setting-dark-border);
}
.theme-text {
    font-size: 15px;
}
/* 音效设置区域样式 */
.audio-wrap {
    padding: 12px 0;
    border-bottom: 1px solid var(--setting-light-border);
}
body.dark .audio-wrap {
    border-bottom-color: var(--setting-dark-border);
}
.audio-row {
    margin: 10px 0;
}
.audio-label {
    display: block;
    font-size: 14px;
    margin-bottom: 6px;
}
.audio-select, .vol-slider {
    width: 100%;
    padding: 6px;
    border-radius: 6px;
    border: 1px solid #ddd;
}
body.dark .audio-select {
    background: #2d2d44;
    color: #e9ecef;
    border-color: #444466;
}
.vol-val {
    font-size: 13px;
    color: #868E96;
    margin-left: 8px;
}
/* 背景上传区域 */
.bg-wrap {
    padding: 12px 0;
    border-bottom: 1px solid var(--setting-light-border);
    display: none;
}
body.dark .bg-wrap {
    border-bottom-color: var(--setting-dark-border);
}
.bg-row {
    margin: 10px 0;
}
.bg-desc {
    font-size: 13px;
    color: #868E96;
    margin: 6px 0;
}
.op-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-top: 8px;
}
.op-slider {
    width: 100px;
}
.bg-btns {
    display: flex;
    gap: 8px;
    margin-top: 10px;
}
.bg-btn {
    flex: 1;
    padding: 6px 0;
    border-radius: 6px;
    border: 1px solid var(--my-bubble-color);
    background: transparent;
    color: var(--my-bubble-color);
    cursor: pointer;
}
.bg-btn.del {
    border-color: var(--danger-color);
    color: var(--danger-color);
}
/* 纯色背景区域 */
.solid-bg-wrap {
    padding: 12px 0;
    border-bottom: 1px solid var(--setting-light-border);
    display: none;
}
body.dark .solid-bg-wrap {
    border-bottom-color: var(--setting-dark-border);
}
.solid-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-top: 10px;
}
/* 一键主题按钮 */
.theme-pack-row {
    padding: 12px 0;
    border-bottom: 1px solid var(--setting-light-border);
}
body.dark .theme-pack-row {
    border-bottom-color: var(--setting-dark-border);
}
.pack-buttons {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px;
    margin-top: 10px;
}
.pack-btn {
    padding: 8px 0;
    border-radius: 8px;
    border: 1px solid var(--my-bubble-color);
    background: transparent;
    color: var(--my-bubble-color);
    cursor: pointer;
    transition: 0.2s;
}
.pack-btn:hover {
    background: var(--my-bubble-color);
    color: #fff;
}
/* 颜色选择行 */
.color-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 0;
    border-bottom: 1px solid var(--setting-light-border);
}
body.dark .color-row {
    border-bottom-color: var(--setting-dark-border);
}
.color-input {
    width: 44px;
    height: 32px;
    border: none;
    cursor: pointer;
    border-radius: 6px;
}
/* 重置按钮 */
.reset-row {
    margin-top: 20px;
    text-align: center;
}
.reset-btn {
    padding: 8px 20px;
    border-radius: 8px;
    border: 1px solid var(--danger-color);
    background: transparent;
    color: var(--danger-color);
    cursor: pointer;
    transition: 0.2s;
}
.reset-btn:hover {
    background: var(--danger-color);
    color: #fff;
}
/* 开关按钮 */
.switch {
    position: relative;
    display: inline-block;
    width: 44px;
    height: 22px;
}
.switch input {
    opacity: 0;
    width: 0;
    height: 0;
}
.slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: #ccc;
    transition: .3s;
    border-radius: 22px;
}
.slider:before {
    position: absolute;
    content: "";
    height: 18px;
    width: 18px;
    left: 2px;
    bottom: 2px;
    background-color: white;
    transition: .3s;
    border-radius: 50%;
}
input:checked + .slider {
    background-color: var(--my-bubble-color);
}
input:checked + .slider:before {
    transform: translateX(22px);
}
.panel-title {
    font-size: 16px;
    font-weight: 600;
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.close-online {
    background: none;
    border: none;
    color: #868E96;
    cursor: pointer;
}
.online-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px;
    border-radius: 10px;
    margin-bottom: 8px;
    transition: background 0.3s;
}
.online-item:hover {
    background: #F8F9FA;
}
body.dark .online-item:hover {
    background: rgba(255,255,255,0.08);
}
.online-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    object-fit: cover;
}
.online-name {
    font-weight: 500;
    font-size: 15px;
}
.online-loc {
    font-size: 12px;
    color: #868E96;
    margin-top: 2px;
    transition: color 0.3s;
}
/* 遮罩 */
.mask {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.3);
    z-index: 997;
    display: none;
}
.mask.show {
    display: block;
}
/* 滚动条 */
::-webkit-scrollbar {width: 5px;}
::-webkit-scrollbar-thumb {background: #D1D5DB; border-radius: 4px;}
/* ========== 新增Emoji表情面板样式 无冲突 移动端适配 ========== */
.emoji-btn {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: #F1F3F5;
    color: #495057;
    border: none;
    flex-shrink: 0;
    cursor: pointer;
    transition: background 0.2s;
}
body.dark .emoji-btn {
    background: #33334d;
    color: #ced4da;
}
.emoji-btn:hover {
    background: #e2e6ea;
}
body.dark .emoji-btn:hover {
    background: #444466;
}
.emoji-panel {
    position: absolute;
    bottom: 52px;
    left: 8px;
    width: min(340px, calc(100% - 16px));
    max-height: 280px;
    overflow: hidden;
    background: var(--setting-light-bg);
    border-radius: 12px;
    box-shadow: 0 3px 15px rgba(0,0,0,0.12);
    padding: 10px;
    display: none;
    z-index: 999;
}
body.dark .emoji-panel {
    background: var(--setting-dark-bg);
}
.emoji-panel.show {
    display: block;
}
/* 分类标签栏 */
.emoji-tab-bar {
    display: flex;
    gap: 4px;
    overflow-x: auto;
    padding-bottom: 8px;
    margin-bottom: 8px;
}
.emoji-tab-bar::-webkit-scrollbar {height:3px;}
.emoji-tab {
    white-space: nowrap;
    font-size: 12px;
    padding: 4px 8px;
    border-radius: 6px;
    border: 1px solid #ddd;
    cursor: pointer;
    flex-shrink:0;
}
body.dark .emoji-tab {border-color:#444466;}
.emoji-tab.active {
    background: var(--my-bubble-color);
    color:#fff;
    border-color: var(--my-bubble-color);
}
/* 表情容器滚动区域 */
.emoji-scroll-box {
    max-height: 180px;
    overflow-y: auto;
}
.emoji-wrap {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(34px, 1fr));
    gap: 6px;
}
.emoji-item {
    font-size: 22px;
    text-align: center;
    padding: 4px;
    border-radius: 6px;
    cursor: pointer;
    transition: background 0.15s;
}
.emoji-item:hover {
    background: rgba(0,0,0,0.08);
}
body.dark .emoji-item:hover {
    background: rgba(255,255,255,0.12);
}
/* 图片上传预览深色适配，无冲突 */
body.dark #imgPreviewWrap {
    background: var(--setting-dark-bg);
}
#imgPreviewWrap .preview-item {
    position: relative;
    width: 80px;
    height: 80px;
    border-radius: 6px;
    overflow: hidden;
}
#imgPreviewWrap .preview-item img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
#imgPreviewWrap .preview-item .del-prev {
    position: absolute;
    top: 2px;
    right: 2px;
    width: 18px;
    height: 18px;
    background: rgba(0,0,0,0.6);
    color: #fff;
    border: none;
    border-radius: 50%;
    font-size: 12px;
    cursor: pointer;
}
/* 上传进度弹窗样式 */
.upload-progress-modal {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.55);
    z-index: 10000;
    display: none;
    align-items: center;
    justify-content: center;
}
.upload-progress-modal.show {
    display: flex;
}
.progress-box {
    width: 320px;
    background: #fff;
    border-radius: 12px;
    padding: 20px;
}
body.dark .progress-box {
    background: #2d2d44;
}
.progress-title {
    margin-bottom: 12px;
    font-size: 15px;
}
.progress-bar-wrap {
    width: 100%;
    height: 10px;
    background: #ddd;
    border-radius: 99px;
    overflow: hidden;
}
body.dark .progress-bar-wrap {
    background: #444;
}
.progress-bar {
    height: 100%;
    width: 0%;
    background: var(--my-bubble-color);
    transition: width 0.1s linear;
}
.progress-text {
    text-align: center;
    margin-top: 8px;
    font-size: 13px;
    color: #666;
}
body.dark .progress-text {
    color: #ccc;
}
</style>
</head>
<body>
<!-- 顶部 -->
<div class="header">
    <div class="header-left">
        <button class="menu-btn" id="menuBtn"><i class="fas fa-bars"></i></button>
        <div class="title">在线聊天室</div>
    </div>
    <div class="header-right">
        <button class="online-btn" id="onlineBtn">
            <i class="fas fa-users"></i>
            <span class="online-badge" id="onlineNum">0</span>
        </button>
    </div>
</div>
<!-- 主体 -->
<div class="wrap">
    <!-- 聊天区 -->
    <div class="chat-box">
        <div class="msg-list" id="msgList"></div>
        <div class="send-box">
            <div class="send-area">
                <!-- 新增图片上传按钮 -->
                <button class="emoji-btn" id="uploadImgBtn" style="width:40px;height:40px;flex-shrink:0;">
                    <i class="fas fa-image"></i>
                </button>
                <!-- 隐藏图片文件选择器 -->
                <input type="file" id="imgFileInput" accept="image/png,image/jpeg,image/gif,image/webp" hidden multiple>
                <!-- 原有表情按钮 -->
                <button class="emoji-btn" id="emojiBtn"><i class="fas fa-smile"></i></button>
                <textarea class="send-input" id="msgInput" placeholder="发送普通文字，使用```包裹代码块，可上传图片" rows="1"></textarea>
                <button class="send-btn" id="sendBtn"><i class="fas fa-paper-plane"></i></button>
                <!-- Emoji弹窗容器（新增分类标签栏） -->
                <div class="emoji-panel" id="emojiPanel">
                    <div class="emoji-tab-bar" id="emojiTabBar"></div>
                    <div class="emoji-scroll-box">
                        <div class="emoji-wrap" id="emojiWrap"></div>
                    </div>
                </div>
                <!-- 图片预览容器，行内底部展示，移动端自适应 -->
                <div id="imgPreviewWrap" style="position:absolute;bottom:52px;left:12px;display:none;gap:8px;flex-wrap:wrap;max-width:calc(100% - 24px);padding:8px;background:#fff;border-radius:10px;box-shadow:0 2px 12px rgba(0,0,0,0.1);">
                </div>
            </div>
        </div>
    </div>
    <!-- 左侧菜单 -->
    <div class="sidebar" id="sidebar">
        <a href="profile.php" class="side-item">
            <i class="fas fa-user"></i>个人资料
        </a>
        <a href="javascript:openSetting();" class="side-item">
            <i class="fas fa-cog"></i>系统设置
        </a>
        <?php if ($_SESSION['is_admin']): ?>
        <a href="admin/" class="side-item">
            <i class="fas fa-shield-alt"></i>管理后台
        </a>
        <?php endif; ?>
        <a href="logout.php" class="side-item">
            <i class="fas fa-sign-out-alt"></i>退出登录
        </a>
    </div>
    <!-- 在线用户 -->
    <div class="online-panel" id="onlinePanel">
        <div class="panel-title">
            <span>在线用户</span>
            <button class="close-online" id="closeOnline"><i class="fas fa-times"></i></button>
        </div>
        <div id="onlineList"></div>
    </div>
    <!-- 设置弹窗 -->
    <div class="setting-panel" id="settingPanel">
        <div class="setting-title">
            <span>系统设置</span>
            <button class="close-setting" id="closeSetting"><i class="fas fa-times"></i></button>
        </div>
        <!-- 深色模式 -->
        <div class="theme-row">
            <span class="theme-text">深色模式</span>
            <label class="switch">
                <input type="checkbox" id="darkModeSwitch">
                <span class="slider"></span>
            </label>
        </div>
        <!-- 桌面通知 -->
        <div class="theme-row">
            <span class="theme-text">新消息桌面通知</span>
            <label class="switch">
                <input type="checkbox" id="notifySwitch">
                <span class="slider"></span>
            </label>
        </div>
        <!-- 自动滚动开关 -->
        <div class="theme-row">
            <span class="theme-text">新消息自动滚动到底部</span>
            <label class="switch">
                <input type="checkbox" id="autoScrollSwitch">
                <span class="slider"></span>
            </label>
        </div>
        <!-- 通知音效可视化设置（简化版，移除私聊/群聊独立开关） -->
        <div class="audio-wrap">
            <span class="theme-text">通知音效设置</span>
            <!-- 音效总开关 -->
            <div class="theme-row">
                <span class="theme-text">开启消息提示音效</span>
                <label class="switch">
                    <input type="checkbox" id="audioMainSwitch">
                    <span class="slider"></span>
                </label>
            </div>
            <!-- 音量滑块 -->
            <div class="audio-row">
                <label class="audio-label">提示音量 <span class="vol-val" id="audioVolNum">50</span></label>
                <input type="range" min="0" max="100" value="50" class="vol-slider" id="audioVolSlider">
            </div>
            <!-- 提示音下拉选择 -->
            <div class="audio-row">
                <label class="audio-label">提示音类型</label>
                <select class="audio-select" id="audioTypeSelect">
                    <option value="ding">默认叮咚</option>
                    <option value="light">轻快提示</option>
                    <option value="mute">静音</option>
                </select>
            </div>
        </div>
        <!-- 允许自定义图片背景 -->
        <div class="theme-row">
            <span class="theme-text">图片背景（与纯色背景互斥）</span>
            <label class="switch">
                <input type="checkbox" id="bgEnableSwitch">
                <span class="slider"></span>
            </label>
        </div>
        <!-- 背景上传区域 -->
        <div class="bg-wrap" id="bgBox">
            <span class="theme-text">背景图片设置</span>
            <div class="bg-desc">上传本地图片作为聊天背景，自动模糊降低干扰；在不开启深色模式的情况下背景图片会变得很朦胧</div>
            <div class="bg-row">
                <input type="file" id="bgFile" accept="image/*" hidden>
                <div class="op-row">
                    <span>背景透明度</span>
                    <input type="range" min="0.05" max="0.4" step="0.01" class="op-slider" id="bgOpacitySlider">
                    <span id="opacityVal">0.15</span>
                </div>
                <div class="bg-btns">
                    <button class="bg-btn" id="uploadBgBtn">选择图片</button>
                    <button class="bg-btn del" id="clearBgBtn">清除背景</button>
                </div>
            </div>
        </div>
        <!-- 新增：纯色背景总开关 -->
        <div class="theme-row">
            <span class="theme-text">开启纯色背景（与图片背景互斥）</span>
            <label class="switch">
                <input type="checkbox" id="solidBgSwitch">
                <span class="slider"></span>
            </label>
        </div>
        <!-- 纯色背景选择区域 -->
        <div class="solid-bg-wrap" id="solidBgBox">
            <span class="theme-text">聊天室纯色背景</span>
            <div class="solid-row">
                <span>选择颜色</span>
                <input type="color" class="color-input" id="solidBgColor">
            </div>
            <div class="bg-btns" style="margin-top:12px">
                <button class="bg-btn del" id="clearSolidBgBtn">重置纯色背景</button>
            </div>
        </div>
        <!-- 一键主题套装 -->
        <div class="theme-pack-row">
            <span class="theme-text">一键主题套装</span>
            <div class="pack-buttons">
                <button class="pack-btn" data-pack="mint">薄荷绿</button>
                <button class="pack-btn" data-pack="blue">清新蓝</button>
                <button class="pack-btn" data-pack="pink">樱花粉</button>
                <button class="pack-btn" data-pack="purple">暗夜紫</button>
            </div>
        </div>
        <!-- 手动气泡配色 -->
        <div class="color-row">
            <span class="theme-text">我的消息气泡颜色</span>
            <input type="color" class="color-input" id="myBubbleColor">
        </div>
        <div class="color-row">
            <span class="theme-text">他人消息气泡颜色</span>
            <input type="color" class="color-input" id="otherBubbleColor">
        </div>
        <!-- 重置配色 -->
        <div class="reset-row">
            <button class="reset-btn" id="resetThemeBtn">恢复默认全部配色/背景</button>
        </div>
    </div>
</div>
<div class="mask" id="mask"></div>

<!-- 真实上传进度弹窗 -->
<div class="upload-progress-modal" id="uploadProgressModal">
    <div class="progress-box">
        <div class="progress-title">图片上传中...</div>
        <div class="progress-bar-wrap">
            <div class="progress-bar" id="progressBar"></div>
        </div>
        <div class="progress-text" id="progressText">0%</div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
let lastId = 0;
let loading = false;
// 消息去重容器
const renderedMsgIds = new Set();
const body = document.body;
const root = document.documentElement;
// 页面元素
const darkSwitch = document.getElementById('darkModeSwitch');
const notifySwitch = document.getElementById('notifySwitch');
const autoScrollSwitch = document.getElementById('autoScrollSwitch');
const bgEnableSwitch = document.getElementById('bgEnableSwitch');
const bgBox = document.getElementById('bgBox');
const bgFile = document.getElementById('bgFile');
const uploadBgBtn = document.getElementById('uploadBgBtn');
const clearBgBtn = document.getElementById('clearBgBtn');
const bgOpacitySlider = document.getElementById('bgOpacitySlider');
const opacityVal = document.getElementById('opacityVal');
const settingPanel = document.getElementById('settingPanel');
const closeSetting = document.getElementById('closeSetting');
const myColorInput = document.getElementById('myBubbleColor');
const otherColorInput = document.getElementById('otherBubbleColor');
const resetBtn = document.getElementById('resetThemeBtn');
const msgList = document.getElementById('msgList');
const menuBtn = document.getElementById('menuBtn');
const onlineBtn = document.getElementById('onlineBtn');
const closeOnline = document.getElementById('closeOnline');
const mask = document.getElementById('mask');
const onlineNum = document.getElementById('onlineNum');
const onlineList = document.getElementById('onlineList');
const sendInput = document.getElementById('msgInput');
const sendBtn = document.getElementById('sendBtn');
// 新增纯色背景元素
const solidBgSwitch = document.getElementById('solidBgSwitch');
const solidBgBox = document.getElementById('solidBgBox');
const solidBgColor = document.getElementById('solidBgColor');
const clearSolidBgBtn = document.getElementById('clearSolidBgBtn');
// ========== 音效DOM元素 ==========
const audioMainSwitch = document.getElementById('audioMainSwitch');
const audioVolSlider = document.getElementById('audioVolSlider');
const audioVolNum = document.getElementById('audioVolNum');
const audioTypeSelect = document.getElementById('audioTypeSelect');
// ========== Emoji相关DOM ==========
const emojiBtn = document.getElementById('emojiBtn');
const emojiPanel = document.getElementById('emojiPanel');
const emojiWrap = document.getElementById('emojiWrap');
const emojiTabBar = document.getElementById('emojiTabBar');
// ========== 图片上传DOM ==========
const uploadImgBtn = document.getElementById('uploadImgBtn');
const imgFileInput = document.getElementById('imgFileInput');
const imgPreviewWrap = document.getElementById('imgPreviewWrap');
let pendingUploadImages = [];
// ========== 上传进度弹窗DOM ==========
const uploadProgressModal = document.getElementById('uploadProgressModal');
const progressBar = document.getElementById('progressBar');
const progressText = document.getElementById('progressText');

// 完整12分类表情数据
const EMOJI_CATEGORIES = [
    {
        name: "黄脸情绪",
        list: ['😀','😃','😄','😁','😆','😅','🤣','😂','🙂','🙃','😉','😊','😇','🥰','😍','🤩','😘','😗','☺️','😚','😙','🥲','😋','😛','😜','🤪','😝','🤑','🤗','🤭','🤫','🤔','🤐','🤨','😐','😑','😶','😏','🙄','😮','😯','😲','😳','🥺','😦','😧','😨','😰','😥','😢','😭','😱','😖','😣','😞','😓','😩','😫','🥱','😴','😌','😛','😜','🤪','😝','🤤','😋','😷','🤒','🤢','🤮','🤧','😵','🤯','🤠','🥳','🥸','😎','🤓','🧐','😕','😟','🙁','😮‍💨','😔','😬','🤮','🤢','🤧','🥵','🥶','🥴','😵‍💫','🤕','🤡','👹','👺','👻','👽','👾','🤖','😺','😸','😹','😻','😼','😽','🙀','😿','😾']
    },
    {
        name: "人物家庭职业",
        list: ['👶','👧','🧒','👦','👩','🧑','👨','👩‍🦱','🧑‍🦱','👨‍🦱','👩‍🦰','🧑‍🦰','👨‍🦰','👩‍🦳','🧑‍🦳','👨‍🦳','👩‍🦲','🧑‍🦲','👨‍🦲','👵','🧓','👴','👮‍♀️','👮','👮‍♂️','👷‍♀️','👷','👷‍♂️','💂‍♀️','💂','💂‍♂️','🕵️‍♀️','🕵️','🕵️‍♂️','👩‍⚕️','🧑‍⚕️','👨‍⚕️','👩‍🌾','🧑‍🌾','👨‍🌾','👩‍🍳','🧑‍🍳','👨‍🍳','👩‍🔧','🧑‍🔧','👨‍🔧','👩‍🏭','🧑‍🏭','👨‍🏭','👩‍💼','🧑‍💼','👨‍💼','👩‍🔬','🧑‍🔬','👨‍🔬','👩‍💻','🧑‍💻','👨‍💻','👩‍🎤','🧑‍🎤','👨‍🎤','👩‍🎨','🧑‍🎨','👨‍🎨','👩‍✈️','🧑‍✈️','👨‍✈️','👩‍🚀','🧑‍🚀','👨‍🚀','👩‍⚖️','🧑‍⚖️','👨‍⚖️','🧙‍♀️','🧙','🧙‍♂️','🧚‍♀️','🧚','🧚‍♂️','🧜‍♀️','🧜','🧜‍♂️','🧝‍♀️','🧝','🧝‍♂️','🧛‍♀️','🧛','🧛‍♂️','🧟‍♀️','🧟','🧟‍♂️','🧞‍♀️','🧞','🧞‍♂️','🤰','🤱','👼','🎅','🤶','🧑‍🎄','🦌','👨‍👩‍👧','👨‍👩‍👧‍👦','👨‍👩‍👦','👨‍👧','👨‍👧‍👦','👨‍👦','👨‍👦‍👦','👩‍👧','👩‍👧‍👦','👩‍👦','👩‍👦‍👦','👩‍❤️‍👨','👩‍❤️‍👩','👨‍❤️‍👨','💏','👫','👭','👬']
    },
    {
        name: "手势动作",
        list: ['👋','🤚','🖐️','✋','🖖','👌','🤌','🤏','✌️','🤞','🤟','🤘','🤙','👈','👉','👆','🖕','👇','☝️','🫵','🫴','🫷','🫸','🫎','🫐','🙌','👏','🤲','🤝','🙏','🚶‍♀️','🚶','🚶‍♂️','🏃‍♀️','🏃','🏃‍♂️','💃','🕺','🧎‍♀️','🧎','🧎‍♂️','🧍‍♀️','🧍','🧍‍♂️','👯‍♀️','👯','👯‍♂️','🧘‍♀️','🧘','🧘‍♂️','🤸‍♀️','🤸','🤸‍♂️','🤼‍♀️','🤼','🤼‍♂️','🤽‍♀️','🤽','🤽‍♂️','🤾‍♀️','🤾','🤾‍♂️','🏊‍♀️','🏊','🏊‍♂️','⛹️‍♀️','⛹️','⛹️‍♂️','🏋️‍♀️','🏋️','🏋️‍♂️','🚴‍♀️','🚴','🚴‍♂️','🚵‍♀️','🚵','🚵‍♂️','🤰','🤱','👶']
    },
    {
        name: "动物",
        list: ['🐶','🐕','🦮','🐕‍🦺','🐩','🐈','🐈‍⬛','🐅','🐆','🐎','🦄','🦓','🦌','🦬','🐂','🐃','🐄','🐪','🐫','🦙','🦒','🐘','🦣','🦏','🦛','🐭','🐁','🐀','🐹','🐰','🐇','🐿️','🦫','🦔','🐻','🐻‍❄️','🐨','🐼','🦥','🦦','🦨','🦡','🐾','🦃','🐔','🐓','🐣','🐤','🐥','🐦','🐧','🕊️','🦅','🦆','🦢','🦉','🦤','🪶','🦩','🦚','🦜','🐸','🐊','🐢','🦎','🐍','🐲','🐉','🦕','🦖','🐳','🐋','🐬','🦭','🐟','🐠','🐡','🦈','🐙','🦑','🦐','🦞','🦀','🦋','🐌','🐞','🐜','🦟','🦗','🪳','🪲','🪰']
    },
    {
        name: "食物饮品",
        list: ['🍇','🍈','🍉','🍊','🍋','🍌','🍍','🥭','🍎','🍏','🍐','🍑','🍒','🍓','🫐','🥝','🍅','🫒','🥥','🥑','🍆','🥔','🥕','🌽','🌶️','🫑','🥒','🥬','🥦','🧄','🧅','🍞','🥐','🥖','🫓','🥨','🥯','🥞','🧇','🥓','🍔','🍟','🌭','🍕','🫔','🌮','🌯','🥙','🧆','🥚','🍳','🥘','🍲','🫕','🥗','🍿','🧈','🧂','🥫','🍱','🍘','🍙','🍚','🍛','🍜','🍝','🍠','🍢','🍣','🍤','🍥','🥮','🍡','🥟','🥠','🥡','🦪','🍦','🍧','🍨','🍩','🍪','🎂','🍰','🧁','🥧','🍫','🍬','🍭','🍮','🍯','🍼','🥛','☕','🍵','🍶','🍾','🍷','🍸','🍹','🍺','🍻','🥂','🥃','🫗','🥤','🧃','🧉','🍴','🥄','🍽️','🔪','🫙']
    },
    {
        name: "自然天气",
        list: ['💐','🌸','💮','🏵️','🌹','🥀','🌺','🌻','🌼','🌷','🌱','🌿','☘️','🍀','🎍','🎋','🌾','🌳','🌴','🌵','🌲','🌰','🪴','☀️','🌤️','⛅','🌥️','☁️','🌧️','⛈️','❄️','☃️','⛄','🌬️','💨','🌪️','🌈','🌊','💧','💦','☔','⚡','🌑','🌒','🌓','🌔','🌕','🌖','🌗','🌘','🌙','🌚','🌛','🌜','🌝','🌞','⭐','🌟','💫','✨','🪐','☄️','🏔️','⛰️','🌋','🗻','🏕️','🏖️','🏜️','🏝️','🏞️','🌠']
    },
    {
        name: "交通建筑",
        list: ['🚗','🚙','🚚','🚛','🚜','🦼','🛴','🚲','🛵','🏍️','🛺','🚔','🚍','🚎','🚐','🚒','🚑','🚓','🚕','🚖','🚘','🚋','🚃','🚄','🚅','🚆','🚇','🚈','🚞','🚡','🚠','🚟','⚓','⛵','🛶','🚤','🛳️','⛴️','🚢','🛥️','✈️','🛫','🛬','🛩️','🚀','🛸','💺','🪂','🏠','🏡','🏢','🏣','🏤','🏥','🏦','🏨','🏪','🏫','🏬','🏭','🏯','🏰','🗼','🕌','⛪','⛩️','🛕','🕍','🏛️','⛲','🗽','🌉','🏗️','🪦']
    },
    {
        name: "物品工具数码",
        list: ['📱','📲','💻','🖥️','⌨️','🖱️','🖨️','🖲️','📹','🎥','📼','📀','💽','💾','💿','📺','📻','🎙️','🎚️','🎛️','🎧','🎤','📞','☎️','📠','📡','🔋','🔌','💡','🔦','🕯️','📝','📄','📃','📜','📋','📅','📆','🗓️','📇','🗃️','🗄️','📊','📈','📉','📌','📍','📎','🖇️','📏','📐','✂️','🗡️','🔪','🖊️','🖋️','✒️','🖌️','🖍️','🔧','🔨','⚒️','🛠️','⛏️','⚙️','🪛','🪚','🪝','🪜','🧰','🔩','🛡️','🧺','🪣','🪠','🪥','🧴','🪒','🪓','🧽','🪴','🪑','🛋️','🚪','🪞','🪟','🛏️','🧸','🪆','🎈','🎀','🎁','🎗️','🏺','🪙','💰','💴','💵','💶','💷','💸','💳','🩺','💊','💉','🩸','🩹','🩼','🦴','🫀','🫁']
    },
    {
        name: "服饰饰品",
        list: ['👕','👖','👗','👘','🥻','🩱','🩲','🩳','👙','👚','👛','👜','🎒','🥿','👞','👟','🥾','👠','👡','🩰','🧦','🧤','🧣','🎩','👒','🎓','⛑️','🪖','👑','💍','⌚','📿','🧢','🪮','💄']
    },
    {
        name: "运动娱乐",
        list: ['⚽','🏀','🏈','⚾','🥎','🎾','🏐','🏉','🥏','🎱','🪀','🏓','🏸','🏒','🏑','🏏','🪃','🥅','⛳','🏹','🎣','🥊','🥋','🛹','🛼','🪂','🪁','🎮','🎰','🎲','🃏','🀄️','🎴','🎭','🎨','🎬','🎤','🎧','🎼','🎹','🥁','🎷','🎺','🎸','🪗','🎻']
    },
    {
        name: "爱心情感",
        list: ['❤️','🧡','💛','💚','💙','💜','🖤','🤍','🤎','💔','❤️‍🔥','❤️‍🩹','💕','💞','💓','💗','💖','💘','💝','💟','❣️','💌','💋','💯','💢','💥','💫','💦','💨','🕳️','💬','👁️‍🗨️','🗯️','💭']
    },
    {
        name: "标识符号",
        list: ['↩️','↪️','⤴️','⤵️','🔃','🔄','🔙','🔚','🔛','🔜','🔝','🔞','🚫','🚭','🚯','🚱','🚷','📵','🔕','🅾️','🆘','🛑','⚠️','🚸','⛔','✅','❌','❓','❕','❗','➕','➖','✖️','➗','#️⃣','*️⃣','0️⃣','1️⃣','2️⃣','3️⃣','4️⃣','5️⃣','6️⃣','7️⃣','8️⃣','9️⃣','🔟','🔢','©️','®️','™️','ℹ️','🆒','🆓','🆕','🆗','🆙','🆚','🅰️','🅱️','🆎','🅾️','💱','💲','⚖️','🔗','🧿','♻️','🏧','🚮','📶','📳','📴','♈','♉','♊','♋','♌','♍','♎','♏','♐','♑','♒','♓','⛎','🐁','🐂','🐅','🐇','🐉','🐍','🐎','🐐','🐒','🐓','🐕','🐖','🕛','🕧','🕐','🕜','🕑','🕝','🕒','🕞','🕓','🕟','🕔','🕠','🕕','🕡','🕖','🕢','🕗','🕣','🕘','🕤','🕙','🕥','🕚','🕦','⌛','⏳','⌚','⏰','⏱️','⏲️','🕰️']
    }
];
let currentTabIndex = 0;
// 音频对象
let chatAudio = new Audio('ogg/1.ogg');
// 默认音效配置
const AUDIO_DEFAULT = {
    enable: 1,
    volume: 50,
    type: 'ding'
};
// 主题配色包
const THEME_PACKS = {
    mint: { my: "#12B886", other: "#ffffff" },
    blue: { my: "#2589eb", other: "#f0f7ff" },
    pink: { my: "#e868a2", other: "#fff3f8" },
    purple: { my: "#8c52ff", other: "#f5f0ff" }
};
const DEFAULT_MY_COLOR = "#12B886";
const DEFAULT_OTHER_COLOR = "#ffffff";
const DEFAULT_OPACITY = 0.15;
const DEFAULT_SOLID_BG = "transparent";
const DEFAULT_LIGHT_BODY = "#F8F9FA";
const DEFAULT_DARK_BODY = "#1a1a2e";
const CURRENT_USER_ID = <?=$_SESSION['user_id']?>;
const IS_ADMIN = <?=$_SESSION['is_admin'] ? 1 : 0?>;
let windowBlur = false;
window.addEventListener('blur', ()=> windowBlur = true);
window.addEventListener('focus', ()=> windowBlur = false);

// ========== Emoji初始化 ==========
function initEmojiPanel() {
    let tabHtml = '';
    EMOJI_CATEGORIES.forEach((cat, idx) => {
        tabHtml += `<div class="emoji-tab ${idx === currentTabIndex ? 'active' : ''}" data-tab="${idx}">${cat.name}</div>`;
    });
    emojiTabBar.innerHTML = tabHtml;
    document.querySelectorAll('.emoji-tab').forEach(tab => {
        tab.onclick = function() {
            currentTabIndex = Number(this.dataset.tab);
            document.querySelectorAll('.emoji-tab').forEach(t => t.classList.remove('active'));
            this.classList.add('active');
            renderEmojiList(currentTabIndex);
        }
    });
    renderEmojiList(currentTabIndex);
}
function renderEmojiList(tabIndex) {
    const list = EMOJI_CATEGORIES[tabIndex].list;
    let html = '';
    list.forEach(emoji => {
        html += `<div class="emoji-item" data-emoji="${emoji}">${emoji}</div>`;
    });
    emojiWrap.innerHTML = html;
    document.querySelectorAll('.emoji-item').forEach(item => {
        item.onclick = function () {
            const emo = this.dataset.emoji;
            const start = sendInput.selectionStart;
            const end = sendInput.selectionEnd;
            const val = sendInput.value;
            sendInput.value = val.substring(0, start) + emo + val.substring(end);
            sendInput.selectionStart = sendInput.selectionEnd = start + emo.length;
            sendInput.focus();
        }
    })
}
emojiBtn.onclick = function(e) {
    e.stopPropagation();
    emojiPanel.classList.toggle('show');
}

// ========== 图片上传逻辑 ==========
uploadImgBtn.addEventListener('click', function(e) {
    e.stopPropagation();
    imgFileInput.click();
});
imgFileInput.addEventListener('change', function(e) {
    const files = Array.from(this.files);
    // 限制最多9张
    if (pendingUploadImages.length + files.length > 9) {
        alert('最多一次性上传9张图片');
        this.value = '';
        return;
    }
    files.forEach(file => {
        const allowType = ['image/png','image/jpeg','image/gif','image/webp'];
        if (!allowType.includes(file.type) || file.size > 5*1024*1024) {
            alert('仅支持png/jpg/gif/webp，单张最大5MB');
            return;
        }
        pendingUploadImages.push(file);
        const reader = new FileReader();
        reader.onload = ev => {
            const itemDom = document.createElement('div');
            itemDom.className = 'preview-item';
            itemDom.dataset.fileIndex = pendingUploadImages.length - 1;
            itemDom.innerHTML = `
                <img src="${ev.target.result}">
                <button class="del-prev"><i class="fas fa-times"></i></button>
            `;
            itemDom.querySelector('.del-prev').onclick = function(ev) {
                ev.stopPropagation();
                const idx = Number(itemDom.dataset.fileIndex);
                pendingUploadImages.splice(idx, 1);
                itemDom.remove();
                if (pendingUploadImages.length === 0) imgPreviewWrap.style.display = 'none';
            }
            imgPreviewWrap.style.display = 'flex';
            imgPreviewWrap.appendChild(itemDom);
        }
        reader.readAsDataURL(file);
    });
    this.value = '';
});

// 空白关闭弹窗
document.addEventListener('click', function(e) {
    if (!emojiPanel.contains(e.target) && e.target !== emojiBtn && e.target !== uploadImgBtn) {
        emojiPanel.classList.remove('show');
    }
})

// HTML转义
function htmlEscape(str) {
    if (!str) return '';
    return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');
}

// 消息解析（图片+代码块）
function parseMessageContent(rawText) {
    const escaped = htmlEscape(rawText);
    let html = escaped.replace(/!\[.*?\]\((uploads\/.+?\.(png|jpg|jpeg|gif|webp))\)/g, (match, imgSrc) => {
        return `<div style="margin:8px 0;"><img src="${imgSrc}" style="max-width:240px;max-height:300px;border-radius:8px;cursor:pointer;" onclick="window.open('${imgSrc}','_blank')"></div>`;
    });
    const regex = /```([\s\S]*?)```/g;
    return html.replace(regex, (m,code)=>`<div class="code-block">${code}<button class="copy-code" onclick="copyCode(this)"><i class="fas fa-copy"></i></button></div>`);
}
window.copyCode = function(btn){
    const t = btn.parent.innerText.replace('','').trim();
    navigator.clipboard.writeText(t);
    btn.innerHTML = '<i class="fas fa-check"></i>';
    setTimeout(()=>btn.innerHTML='<i class="fas fa-copy"></i>',1500);
}

// 播放提示音
function playChatAudio() {
    const audioEnable = localStorage.getItem('audioMainEnable') === '1';
    const audioType = localStorage.getItem('audioType') || AUDIO_DEFAULT.type;
    const vol = Number(localStorage.getItem('audioVolume')) || AUDIO_DEFAULT.volume;
    if (!audioEnable || audioType === 'mute') return;
    chatAudio.volume = vol / 100;
    chatAudio.currentTime = 0;
    chatAudio.play().catch(err=>{});
}

// 加载本地存储主题配置
function initTheme() {
    const dark = localStorage.getItem('chatDarkMode') === '1';
    dark ? body.classList.add('dark') : body.classList.remove('dark');
    darkSwitch.checked = dark;
    notifySwitch.checked = localStorage.getItem('chatNotify') === '1';
    autoScrollSwitch.checked = localStorage.getItem('chatAutoScroll') !== '0';

    const audioEnable = localStorage.getItem('audioMainEnable') ?? AUDIO_DEFAULT.enable;
    audioMainSwitch.checked = audioEnable === '1';
    const vol = Number(localStorage.getItem('audioVolume')) ?? AUDIO_DEFAULT.volume;
    audioVolSlider.value = vol;
    audioVolNum.textContent = vol;
    const type = localStorage.getItem('audioType') ?? AUDIO_DEFAULT.type;
    audioTypeSelect.value = type;
    chatAudio.volume = vol / 100;

    const bgOn = localStorage.getItem('chatBgEnable') === '1';
    bgEnableSwitch.checked = bgOn;
    bgBox.style.display = bgOn ? 'block' : 'none';
    const op = Number(localStorage.getItem('chatBgOpacity')) || DEFAULT_OPACITY;
    bgOpacitySlider.value = op;
    opacityVal.textContent = op.toFixed(2);
    root.style.setProperty('--bg-opacity', op);
    const bgImg = localStorage.getItem('chatBgImg') || '';
    if(bgImg) root.style.setProperty('--chat-bg-img',`url(${bgImg})`);

    const solidOn = localStorage.getItem('solidBgEnable') === '1';
    solidBgSwitch.checked = solidOn;
    solidBgBox.style.display = solidOn ? 'block' : 'none';
    const solidColor = localStorage.getItem('solidBgColor') || DEFAULT_SOLID_BG;
    solidBgColor.value = solidColor;
    root.style.setProperty('--solid-bg-color', solidColor);

    const my = localStorage.getItem('myBubbleColor') || DEFAULT_MY_COLOR;
    const other = localStorage.getItem('otherBubbleColor') || DEFAULT_OTHER_COLOR;
    setBubbleColor(my, other);
    myColorInput.value = my;
    otherColorInput.value = other;
}

// 音效绑定
audioMainSwitch.onchange = function(){
    localStorage.setItem('audioMainEnable', this.checked ? '1' : '0');
}
audioVolSlider.oninput = function(){
    const v = Number(this.value);
    audioVolNum.textContent = v;
    localStorage.setItem('audioVolume', v);
    chatAudio.volume = v / 100;
}
audioTypeSelect.onchange = function(){
    localStorage.setItem('audioType', this.value);
}

// 设置气泡颜色
function setBubbleColor(my, other) {
    root.style.setProperty('--my-bubble-color', my);
    root.style.setProperty('--other-bubble-color', other);
    localStorage.setItem('myBubbleColor', my);
    localStorage.setItem('otherBubbleColor', other);
    myColorInput.value = my;
    otherColorInput.value = other;
}
function applyPack(name) {
    const p = THEME_PACKS[name];
    if(p) setBubbleColor(p.my, p.other);
}

// 深色模式切换
darkSwitch.onchange = function(){
    if(this.checked){
        body.classList.add('dark');
        localStorage.setItem('chatDarkMode','1');
    }else{
        body.classList.remove('dark');
        localStorage.setItem('chatDarkMode','0');
    }
}

// 桌面通知
notifySwitch.onchange = async function(){
    if(this.checked){
        if(Notification.permission !== 'granted'){
            const perm = await Notification.requestPermission();
            if(perm !== 'granted'){
                this.checked = false;
                localStorage.setItem('chatNotify','0');
                alert('通知权限被拒绝');
                return;
            }
        }
        localStorage.setItem('chatNotify','1');
    }else{
        localStorage.setItem('chatNotify','0');
    }
}
autoScrollSwitch.onchange = ()=>localStorage.setItem('chatAutoScroll',autoScrollSwitch.checked?'1':'0');

// 背景图开关
bgEnableSwitch.onchange = function(){
    const s = this.checked;
    localStorage.setItem('chatBgEnable',s?'1':'0');
    bgBox.style.display = s?'block':'none';
    if(s){
        solidBgSwitch.checked = false;
        solidBgBox.style.display = 'none';
        localStorage.setItem('solidBgEnable','0');
    }
    if(!s){
        localStorage.removeItem('chatBgImg');
        root.style.setProperty('--chat-bg-img','none');
    }
}
bgOpacitySlider.oninput = function(){
    const v = Number(this.value);
    opacityVal.textContent = v.toFixed(2);
    root.style.setProperty('--bg-opacity',v);
    localStorage.setItem('chatBgOpacity',v);
}
uploadBgBtn.onclick = ()=>bgFile.click();
bgFile.onchange = e=>{
    const f = e.target.files[0];
    if(!f) return;
    const r = new FileReader();
    r.onload = ev=>{
        root.style.setProperty('--chat-bg-img',`url(${ev.target.result})`);
        localStorage.setItem('chatBgImg',ev.target.result);
    }
    r.readAsDataURL(f);
}
clearBgBtn.onclick = ()=>{
    localStorage.removeItem('chatBgImg');
    root.style.setProperty('--chat-bg-img','none');
    bgFile.value = '';
}

// 纯色背景
solidBgSwitch.onchange = function(){
    const status = this.checked;
    localStorage.setItem('solidBgEnable', status ? '1' : '0');
    solidBgBox.style.display = status ? 'block' : 'none';
    if(status){
        bgEnableSwitch.checked = false;
        bgBox.style.display = 'none';
        localStorage.setItem('chatBgEnable','0');
        localStorage.removeItem('chatBgImg');
        root.style.setProperty('--chat-bg-img','none');
    }
    if(!status){
        root.style.setProperty('--solid-bg-color', DEFAULT_SOLID_BG);
        localStorage.removeItem('solidBgColor');
    }
}
solidBgColor.oninput = function(){
    const color = this.value;
    root.style.setProperty('--solid-bg-color', color);
    localStorage.setItem('solidBgColor', color);
}
clearSolidBgBtn.onclick = function(){
    solidBgColor.value = "#ffffff";
    root.style.setProperty('--solid-bg-color', DEFAULT_SOLID_BG);
    localStorage.removeItem('solidBgColor');
}

// 主题套装按钮
document.querySelectorAll('.pack-btn').forEach(b=>{
    b.onclick = ()=>applyPack(b.dataset.pack);
})
myColorInput.oninput = ()=>setBubbleColor(myColorInput.value, otherColorInput.value);
otherColorInput.oninput = ()=>setBubbleColor(myColorInput.value, otherColorInput.value);

// 重置全部设置
resetBtn.onclick = ()=>{
    setBubbleColor(DEFAULT_MY_COLOR, DEFAULT_OTHER_COLOR);
    localStorage.removeItem('chatBgImg');
    localStorage.setItem('chatBgEnable','0');
    bgEnableSwitch.checked = false;
    bgBox.style.display = 'none';
    root.style.setProperty('--chat-bg-img','none');
    localStorage.removeItem('solidBgColor');
    localStorage.setItem('solidBgEnable','0');
    solidBgSwitch.checked = false;
    solidBgBox.style.display = 'none';
    root.style.setProperty('--solid-bg-color', DEFAULT_SOLID_BG);
    bgOpacitySlider.value = DEFAULT_OPACITY;
    opacityVal.textContent = DEFAULT_OPACITY.toFixed(2);
    root.style.setProperty('--bg-opacity', DEFAULT_OPACITY);
    localStorage.setItem('chatBgOpacity', DEFAULT_OPACITY);
    localStorage.setItem('audioMainEnable', AUDIO_DEFAULT.enable);
    audioMainSwitch.checked = AUDIO_DEFAULT.enable === 1;
    localStorage.setItem('audioVolume', AUDIO_DEFAULT.volume);
    audioVolSlider.value = AUDIO_DEFAULT.volume;
    audioVolNum.textContent = AUDIO_DEFAULT.volume;
    chatAudio.volume = AUDIO_DEFAULT.volume / 100;
    localStorage.setItem('audioType', AUDIO_DEFAULT.type);
    audioTypeSelect.value = AUDIO_DEFAULT.type;
    pendingUploadImages = [];
    imgPreviewWrap.innerHTML = '';
    imgPreviewWrap.style.display = 'none';
}

// 桌面通知弹窗
function showNotify(title, content){
    if(localStorage.getItem('chatNotify')!=='1' || !windowBlur || Notification.permission!=='granted') return;
    const n = new Notification(title,{body:content,icon:"png/default-avatar.png"});
    n.onclick = ()=>{window.focus();n.close();}
}

// 设置弹窗
function openSetting(){closeAll();settingPanel.classList.add('show');mask.classList.add('show');}
closeSetting.onclick = ()=>{settingPanel.classList.remove('show');mask.classList.remove('show');}

// 获取消息列表
function getMsg(){
    if(loading) return;
    loading = true;
    fetch(`api/messages.php?last_id=${lastId}`)
    .then(r=>r.json())
    .then(d=>{
        if(!Array.isArray(d.messages)) return;
        const auto = localStorage.getItem('chatAutoScroll')!=='0';
        const diff = msgList.scrollHeight - msgList.scrollTop - msgList.clientHeight;
        const near = diff <= 100;
        let max = lastId;
        d.messages.forEach(m=>{
            if(renderedMsgIds.has(m.id)) return;
            renderedMsgIds.add(m.id);
            if(m.id>max) max = m.id;
            const me = m.user_id == CURRENT_USER_ID;
            const admin = m.is_admin ? '<span class="admin-tag">管理员</span>' : '';
            const delBtn = (me || IS_ADMIN) ? `<button class="del-btn" onclick="del(${m.id})"><i class="fas fa-trash"></i></button>` : '';
            const text = parseMessageContent(m.content);
            const html = `<div class="msg-item ${me?'msg-me':''}">
<img src="${htmlEscape(m.avatar||'assets/default-avatar.png')}" class="msg-avatar">
<div class="msg-content">${delBtn}<div class="msg-name">${htmlEscape(m.nickname||m.username)}${admin}</div><div class="msg-text">${text}</div></div>
</div>`;
            msgList.innerHTML += html;
            if(!me) {
                showNotify(htmlEscape(m.nickname||m.username), m.content);
                playChatAudio();
            }
        })
        lastId = max;
        if(auto && near) msgList.scrollTop = msgList.scrollHeight;
    })
    .catch(e=>console.error('拉取消息失败',e))
    .finally(()=>loading=false);
}

// 发送函数（XHR真实上传进度）
function send(){
    const val = sendInput.value.trim();
    if (!val && pendingUploadImages.length === 0 || loading) return;
    loading = true;

    uploadProgressModal.classList.add('show');
    progressBar.style.width = '0%';
    progressText.textContent = '0%';

    const fd = new FormData();
    fd.append('content', val);
    pendingUploadImages.forEach((file, idx) => {
        fd.append('upload_img_' + idx, file);
    });

    const xhr = new XMLHttpRequest();
    xhr.open('POST', 'api/messages.php');

    xhr.upload.onprogress = function (e) {
        if (e.lengthComputable) {
            const percent = Math.round((e.loaded / e.total) * 100);
            progressBar.style.width = percent + '%';
            progressText.textContent = percent + '%';
        }
    };

    xhr.onload = function () {
        uploadProgressModal.classList.remove('show');
        loading = false;
        try {
            const d = JSON.parse(xhr.responseText);
            if (d.success) {
                sendInput.value = '';
                pendingUploadImages = [];
                imgPreviewWrap.innerHTML = '';
                imgPreviewWrap.style.display = 'none';
            } else {
                alert(d.message || '发送失败');
            }
        } catch (err) {
            alert('服务器数据异常');
            console.error(err);
        }
    };
    xhr.onerror = function () {
        uploadProgressModal.classList.remove('show');
        loading = false;
        alert('网络上传失败');
    };
    xhr.onabort = function () {
        uploadProgressModal.classList.remove('show');
        loading = false;
    };
    xhr.send(fd);
}

// 删除消息
function del(id){
    if(!confirm('确定删除这条消息？')) return;
    const fd = new FormData();
    fd.append('message_id', id);
    fetch('api/delete_message.php',{method:'POST',body:fd})
    .then(r=>r.json())
    .then(d=>{if(d.success) location.reload();})
    .catch(e=>console.error('删除失败',e));
}

// 在线用户
function online(){
    fetch('api/online.php')
    .then(d=>d.json())
    .then(d=>{
        if(!d.users) return;
        onlineNum.innerText = d.users.length;
        let html = '';
        d.users.forEach(u=>{
            const admin = u.is_admin ? '<span class="admin-tag">管理员</span>' : '';
            html += `<div class="online-item">
<img src="${htmlEscape(u.avatar||'assets/default-avatar.png')}" class="online-avatar">
<div><div class="online-name">${htmlEscape(u.nickname||u.username)} ${admin}</div>
<div class="online-loc"><i class="fas fa-map-marker-alt"></i> ${htmlEscape(u.location||'未知')}</div></div>
</div>`;
        })
        onlineList.innerHTML = html;
    })
    .catch(e=>console.error('在线用户加载失败',e));
}

// 侧边栏弹窗控制
menuBtn.onclick = ()=>{sidebar.classList.add('show');mask.classList.add('show');}
onlineBtn.onclick = ()=>{onlinePanel.classList.add('show');mask.classList.add('show');}
closeOnline.onclick = closeAll;
mask.onclick = closeAll;
function closeAll(){
    document.getElementById('sidebar').classList.remove('show');
    document.getElementById('onlinePanel').classList.remove('show');
    document.getElementById('settingPanel').classList.remove('show');
    emojiPanel.classList.remove('show');
    mask.classList.remove('show');
}

// 回车发送
sendInput.onkeydown = e=>{
    if(e.key === 'Enter' && !e.shiftKey){
        e.preventDefault();send();
    }
}
sendBtn.onclick = send;

// 页面初始化
initTheme();
initEmojiPanel();
setInterval(getMsg, 3000);
setInterval(online, 15000);
getMsg();
online();
</script>
</body>
</html>
