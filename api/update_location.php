<?php
define('IN_CHAT', true);
require_once '../config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => '未登录']);
    exit;
}

function get_ip() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) return $_SERVER['HTTP_CLIENT_IP'];
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) return $_SERVER['HTTP_X_FORWARDED_FOR'];
    return $_SERVER['REMOTE_ADDR'];
}

try {
    $ip = get_ip();
    $resp = @file_get_contents("http://ip-api.com/json/{$ip}?lang=zh-CN");
    $data = json_decode($resp, true);

    if (!$data || $data['status'] !== 'success') {
        echo json_encode(['success' => false, 'error' => '获取位置失败']);
        exit;
    }

    $location = $data['regionName'] . ' ' . $data['city'];

    $stmt = $pdo->prepare("
        INSERT INTO user_locations (user_id, ip, city, updated_at)
        VALUES (?, ?, ?, NOW())
    ");
    $stmt->execute([$_SESSION['user_id'], $ip, $location]);

    echo json_encode(['success' => true, 'location' => $location]);
} catch (Exception $e) {
    error_log("Location error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => '更新位置失败']);
}
