<?php
define('IN_CHAT', true);
require_once '../config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => '未登录']);
    exit;
}

// 心跳
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'heartbeat') {
    $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    echo json_encode(['success' => true]);
    exit;
}

// 单个用户信息
if (isset($_GET['user_id'])) {
    $uid = (int)$_GET['user_id'];
    $stmt = $pdo->prepare("
        SELECT u.id, u.username, u.nickname, u.avatar, u.signature, u.created_at, u.is_admin,
            CASE WHEN u.is_admin = 1 THEN u.custom_location
            ELSE (SELECT city FROM user_locations WHERE user_id = u.id ORDER BY id DESC LIMIT 1)
            END AS location
        FROM users u
        WHERE u.id = ? AND u.status = 1
    ");
    $stmt->execute([$uid]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        echo json_encode(['user' => $user]);
    } else {
        echo json_encode(['error' => '用户不存在']);
    }
    exit;
}

// 在线用户（5分钟内活跃）
$stmt = $pdo->prepare("
    SELECT u.id, u.username, u.nickname, u.avatar, u.signature, u.is_admin,
        CASE WHEN u.is_admin = 1 THEN u.custom_location
        ELSE (SELECT city FROM user_locations WHERE user_id = u.id ORDER BY id DESC LIMIT 1)
        END AS location
    FROM users u
    WHERE u.last_login >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
    AND u.status = 1
    ORDER BY u.is_admin DESC, u.nickname, u.username
");
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(['users' => $users]);
