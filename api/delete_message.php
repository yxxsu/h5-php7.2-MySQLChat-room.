<?php
define('IN_CHAT', true);
require_once '../config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => '未登录']);
    exit;
}

$message_id = (int)($_POST['message_id'] ?? 0);
if ($message_id <= 0) {
    echo json_encode(['success' => false, 'error' => '无效消息ID']);
    exit;
}

try {
    // 获取消息归属
    $stmt = $pdo->prepare("SELECT user_id FROM messages WHERE id = ?");
    $stmt->execute([$message_id]);
    $msg = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$msg) {
        echo json_encode(['success' => false, 'error' => '消息不存在']);
        exit;
    }

    // 判断权限
    $stmt = $pdo->prepare("SELECT is_admin FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($msg['user_id'] != $_SESSION['user_id'] && !$user['is_admin']) {
        echo json_encode(['success' => false, 'error' => '无权限删除']);
        exit;
    }

    // 删除
    $stmt = $pdo->prepare("DELETE FROM messages WHERE id = ?");
    $stmt->execute([$message_id]);

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    error_log("Delete error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => '删除失败']);
}
