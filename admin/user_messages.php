<?php
define('IN_CHAT', true);
require_once '../config.php';

if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header('Location: ../login.php');
    exit;
}

$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

$stmt = $pdo->prepare("SELECT username, nickname FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) die('用户不存在');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_message'])) {
    $mid = (int)$_POST['message_id'];
    $stmt = $pdo->prepare("DELETE FROM messages WHERE id = ? AND user_id = ?");
    $stmt->execute([$mid, $user_id]);
    header("Location: user_messages.php?user_id=$user_id&deleted=1");
    exit;
}

$stmt = $pdo->prepare("
    SELECT m.id, m.content, m.created_at,
    COALESCE((SELECT city FROM user_locations WHERE user_id = u.id ORDER BY updated_at DESC LIMIT 1), '未知') AS location
    FROM messages m
    JOIN users u ON m.user_id = u.id
    WHERE m.user_id = ?
    ORDER BY m.created_at DESC
");
$stmt->execute([$user_id]);
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>用户消息管理</title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: system-ui, -apple-system, sans-serif; background: #F8F9FA; padding: 24px; }
.container { max-width: 900px; margin: 0 auto; }
.page-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
.btn-back { background: #6c757d; color: #fff; border: none; padding: 8px 16px; border-radius: 8px; text-decoration: none; }
.card { background: #fff; border-radius: 12px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.04); margin-bottom: 20px; }
.msg-item { position: relative; background: #f8f9fa; padding: 16px; border-radius: 10px; margin-bottom: 12px; }
.msg-time { font-size: 12px; color: #6c757d; margin-bottom: 6px; }
.msg-content { word-break: break-word; line-height: 1.5; }
.msg-del { position: absolute; top: 16px; right: 16px; background: #dc3545; color: #fff; border: none; padding: 4px 10px; border-radius: 6px; cursor: pointer; }
.alert { padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; }
.alert-success { background: #d1fae5; color: #065f46; }
</style>
</head>
<body>
<div class="container">
    <div class="page-head">
        <h2>用户消息管理</h2>
        <a href="users.php" class="btn-back"><i class="fas fa-arrow-left"></i> 返回</a>
    </div>

    <div class="card">
        <h5>用户：<?php echo htmlspecialchars($user['username']); ?>
            <?php if ($user['nickname']): ?>
                (<?php echo htmlspecialchars($user['nickname']); ?>)
            <?php endif; ?>
        </h5>
    </div>

    <?php if (isset($_GET['deleted'])): ?>
        <div class="alert alert-success">消息已删除</div>
    <?php endif; ?>

    <?php foreach ($messages as $msg): ?>
        <div class="msg-item">
            <div class="msg-time">
                <i class="far fa-clock"></i> <?php echo $msg['created_at']; ?>
                <span class="ms-2"><i class="fas fa-map-marker-alt"></i> <?php echo $msg['location']; ?></span>
            </div>
            <div class="msg-content"><?php echo nl2br(htmlspecialchars($msg['content'])); ?></div>
            <button class="msg-del" onclick="del(<?php echo $msg['id']; ?>)">删除</button>
        </div>
    <?php endforeach; ?>
</div>

<form method="POST" id="delForm" style="display: none;">
    <input type="hidden" name="delete_message" value="1">
    <input type="hidden" name="message_id" id="delId">
</form>

<script>
function del(id) {
    if (!confirm('确定删除这条消息？')) return;
    document.getElementById('delId').value = id;
    document.getElementById('delForm').submit();
}
</script>
</body>
</html>