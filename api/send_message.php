<?php
define('IN_CHAT', true);
require_once '../config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => '未登录']);
    exit;
}

$content = trim($_POST['content'] ?? '');
if (empty($content)) {
    echo json_encode(['success' => false, 'error' => '消息不能为空']);
    exit;
}

try {
    // 插入
    $stmt = $pdo->prepare("INSERT INTO messages (user_id, content) VALUES (?, ?)");
    $stmt->execute([$_SESSION['user_id'], $content]);
    $mid = $pdo->lastInsertId();

    // 取回完整消息
    $stmt = $pdo->prepare("
        SELECT m.id, m.content, m.created_at,
            u.id AS user_id, u.username, u.nickname, u.avatar, u.is_admin,
            COALESCE((SELECT city FROM user_locations WHERE user_id = u.id ORDER BY updated_at DESC LIMIT 1), '未知') AS location
        FROM messages m
        JOIN users u ON m.user_id = u.id
        WHERE m.id = ?
    ");
    $stmt->execute([$mid]);
    $message = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'message' => $message]);
} catch (PDOException $e) {
    error_log("Send message error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => '发送失败']);
}
