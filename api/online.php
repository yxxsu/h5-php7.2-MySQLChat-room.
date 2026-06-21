<?php
define('IN_CHAT', true);
require_once '../config.php';
header('Content-Type: application/json');

define('ONLINE_TIMEOUT', 60);

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => '未登录']);
    exit;
}

try {
    // 清理超时
    $stmt = $pdo->prepare("DELETE FROM online_users WHERE last_active < DATE_SUB(NOW(), INTERVAL ? SECOND)");
    $stmt->execute([ONLINE_TIMEOUT]);

    // 更新在线
    $stmt = $pdo->prepare("
        INSERT INTO online_users (user_id, last_active)
        VALUES (?, NOW())
        ON DUPLICATE KEY UPDATE last_active = NOW()
    ");
    $stmt->execute([$_SESSION['user_id']]);

    // 获取在线列表
    $stmt = $pdo->prepare("
        SELECT u.id, u.username, u.nickname, u.avatar, u.is_admin,
            CASE WHEN u.is_admin = 1 THEN u.custom_location
            ELSE (SELECT city FROM user_locations WHERE user_id = u.id ORDER BY updated_at DESC LIMIT 1)
            END AS location
        FROM online_users o
        JOIN users u ON o.user_id = u.id
        WHERE u.status = 1
        AND o.last_active >= DATE_SUB(NOW(), INTERVAL ? SECOND)
        ORDER BY u.is_admin DESC, u.nickname, u.username
    ");
    $stmt->execute([ONLINE_TIMEOUT]);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'users' => $users,
        'total' => count($users)
    ]);
} catch (PDOException $e) {
    error_log("Online error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => '获取在线用户失败']);
}
