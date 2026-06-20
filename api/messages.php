<?php
define('IN_CHAT', true);
require_once '../config.php';
session_write_close();
ob_clean();
header('Content-Type: application/json');
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'error' => 'no_login',
        'message' => '未登录'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
$stmt = $pdo->prepare("SELECT status FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user || $user['status'] == 0) {
    echo json_encode([
        'success' => false,
        'error' => 'account_disabled',
        'message' => '您的账号已被禁用或删除'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $last_id = (int)($_GET['last_id'] ?? 0);
    $stmt = $pdo->prepare("
        SELECT m.*, u.username, u.nickname, u.avatar, u.is_admin,
            COALESCE(
                CASE WHEN u.is_admin = 1 THEN u.custom_location END,
                (SELECT city FROM user_locations WHERE user_id = u.id ORDER BY id LIMIT 1),
                '未知'
            ) AS location
        FROM messages m
        JOIN users u ON m.user_id = u.id
        WHERE m.id > ?
        ORDER BY m.created_at ASC
        LIMIT 50
    ");
    $stmt->execute([$last_id]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode([
        'success' => true,
        'messages' => $messages
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $content = trim($_POST['content'] ?? '');
    $uploadDir = '../uploads/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    // 允许图片+视频MIME，兼容二进制兜底
    $allowMime = [
        'image/jpeg','image/png','image/gif','image/webp',
        'video/mp4','video/mov','video/avi','video/mpeg','video/webm',
        'application/octet-stream'
    ];
    $maxSize = 10 * 1024 * 1024;
    $maxUploadCount = 9;
    $mediaUrlList = [];
    if (!empty($_FILES)) {
        foreach ($_FILES as $fileItem) {
            if (empty($fileItem['tmp_name']) || $fileItem['error'] !== UPLOAD_ERR_OK) {
                continue;
            }
            if(count($mediaUrlList) >= $maxUploadCount){
                echo json_encode([
                    'success' => false,
                    'error' => 'img_count',
                    'message' => '单次最多上传9个媒体（图片+视频）'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
            if ($fileItem['size'] > $maxSize) {
                echo json_encode([
                    'success' => false,
                    'error' => 'img_size',
                    'message' => '文件最大5MB'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
            $mime = @mime_content_type($fileItem['tmp_name']);
            $ext = strtolower(pathinfo($fileItem['name'], PATHINFO_EXTENSION));
            $isMediaExt = in_array($ext, ['jpg','jpeg','png','gif','webp','mp4','mov','avi','mpeg','webm']);
            if(!in_array($mime, $allowMime) && !$isMediaExt){
                echo json_encode([
                    'success' => false,
                    'error' => 'img_type',
                    'message' => '仅支持jpg/png/gif/webp图片、mp4/mov/avi/webm视频'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
            $saveName = date('YmdHis') . '_' . uniqid() . '.' . $ext;
            $saveFullPath = $uploadDir . $saveName;
            $moveRes = @move_uploaded_file($fileItem['tmp_name'], $saveFullPath);
            if (!$moveRes || !file_exists($saveFullPath)) {
                echo json_encode([
                    'success' => false,
                    'error' => 'upload_fail',
                    'message' => '文件保存失败，请检查uploads读写权限'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
            $mediaUrlList[] = "uploads/{$saveName}";
        }
    }
    // 区分图片/视频拼接内容
    foreach ($mediaUrlList as $url) {
        $filePath = $uploadDir . basename($url);
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        if (in_array($ext, ['mp4','mov','avi','mpeg','webm'])) {
            $content .= "\n<video src=\"$url\" controls style=\"max-width:240px;max-height:300px;border-radius:8px;\"></video>";
        } else {
            $content .= "\n![]($url)";
        }
    }
    if (empty($content)) {
        echo json_encode([
            'success' => false,
            'error' => 'empty',
            'message' => '消息不能为空'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if (mb_strlen($content) > 2000) {
        echo json_encode([
            'success' => false,
            'error' => 'long',
            'message' => '消息过长'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    try {
        $ins = $pdo->prepare("INSERT INTO messages(user_id, content) VALUES (?,?)");
        $ins->execute([$_SESSION['user_id'], $content]);
        echo json_encode([
            'success' => true,
            'message_id' => $pdo->lastInsertId()
        ], JSON_UNESCAPED_UNICODE);
    } catch (PDOException $e) {
        error_log('数据库写入异常：'.$e->getMessage());
        echo json_encode([
            'success' => false,
            'error' => 'db',
            'message' => '发送失败'
        ], JSON_UNESCAPED_UNICODE);
    }
    exit;
}
echo json_encode([
    'success' => false,
    'message' => '无效请求'
], JSON_UNESCAPED_UNICODE);
exit;
