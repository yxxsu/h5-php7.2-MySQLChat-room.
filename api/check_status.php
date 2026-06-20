<?php
define('IN_CHAT', true);
require_once '../config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => '未登录']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT status FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || $user['status'] == 0) {
        echo json_encode([
            'success' => false,
            'error' => 'account_disabled',
            'message' => '您的账号已被禁用或删除'
        ]);
        exit;
    }

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => '检查状态失败']);
}
