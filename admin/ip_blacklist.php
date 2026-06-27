<?php
session_start();
define('IN_CHAT', true);
require_once '../config.php';
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];
$success = '';
$error = '';
if (!isset($_SESSION['user_id']) || empty($_SESSION['is_admin'])) {
    header("Location: ../login.php");
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "非法请求，安全校验失败";
    } else {
        try {
            if ($_POST['action'] === 'add') {
                $ip = trim($_POST['ip'] ?? '');
                $reason = trim($_POST['reason'] ?? '');
                if (!filter_var($ip, FILTER_VALIDATE_IP)) {
                    $error = "IP 格式不合法";
                } elseif (empty($reason)) {
                    $error = "请填写封禁原因";
                } else {
                    $check = $pdo->prepare("SELECT id FROM ip_blacklist WHERE ip = ?");
                    $check->execute([$ip]);
                    if ($check->rowCount() > 0) {
                        $error = "该IP已存在黑名单中";
                    } else {
                        $stmt = $pdo->prepare("INSERT INTO ip_blacklist (ip, reason, created_at) VALUES (?, ?, NOW())");
                        $stmt->execute([$ip, $reason]);
                        $success = "IP 封禁成功";
                        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                        $csrf_token = $_SESSION['csrf_token'];
                    }
                }
            } elseif ($_POST['action'] === 'delete') {
                $id = (int)($_POST['id'] ?? 0);
                if ($id <= 0) {
                    $error = "参数错误";
                } else {
                    $stmt = $pdo->prepare("DELETE FROM ip_blacklist WHERE id = ?");
                    $stmt->execute([$id]);
                    $success = "IP 已解封";
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                    $csrf_token = $_SESSION['csrf_token'];
                }
            }
        } catch (PDOException $e) {
            $error = "数据库操作失败：" . $e->getMessage();
        }
    }
}
$page = max(1, isset($_GET['page']) ? (int)$_GET['page'] : 1);
$per_page = 20;
$offset = ($page - 1) * $per_page;
try {
    $total = $pdo->query("SELECT COUNT(*) FROM ip_blacklist")->fetchColumn();
} catch (PDOException $e) {
    $total = 0;
}
$total_pages = $total > 0 ? ceil($total / $per_page) : 1;
if ($page > $total_pages) {
    $page = $total_pages;
}
$offset = ($page - 1) * $per_page;
$list = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM ip_blacklist ORDER BY created_at DESC LIMIT :limit OFFSET :offset");
    $stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $list = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "读取黑名单失败：" . $e->getMessage();
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>IP封禁管理</title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}
body {
    font-family: system-ui, -apple-system, sans-serif;
    background: #F8F9FA;
    color: #212529;
}
.wrap {
    display: flex;
    min-height: 100vh;
}
.sidebar {
    width: 240px;
    background: #212529;
    padding: 24px 0;
    flex-shrink: 0;
}
.sidebar h3 {
    color: #fff;
    text-align: center;
    margin-bottom: 24px;
    font-size: 18px;
}
.sidebar a {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 20px;
    color: #e9ecef;
    text-decoration: none;
    border-radius: 8px;
    margin: 0 8px;
    margin-bottom: 4px;
}
.sidebar a:hover {
    background: #343a40;
    color: #fff;
}
.sidebar a.active {
    background: #12B886;
    color: #fff;
}
.main {
    flex: 1;
    padding: 24px;
    overflow-x: auto;
}
.page-head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    flex-wrap: wrap;
    gap: 10px;
}
.page-title {
    font-size: 22px;
    font-weight: 600;
}
.text-muted {
    color: #6c757d;
    font-size: 14px;
}
.card {
    background: #fff;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.04);
    margin-bottom: 20px;
}
.form-row {
    display: flex;
    gap: 12px;
    align-items: end;
    flex-wrap: wrap;
}
.form-group {
    flex: 1;
    min-width: 200px;
}
.form-group label {
    display: block;
    margin-bottom: 6px;
    font-size: 14px;
    font-weight: 500;
}
.form-control {
    width: 100%;
    height: 40px;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 0 12px;
    outline: none;
    font-size: 14px;
}
.form-control:focus {
    border-color: #12B886;
    box-shadow: 0 0 0 2px rgba(18,184,134,0.2);
}
.btn {
    height: 40px;
    padding: 0 16px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    white-space: nowrap;
    font-size: 14px;
}
.btn-sm {
    height: 32px;
    padding: 0 10px;
    font-size: 13px;
}
.btn-success {
    background: #12B886;
    color: #fff;
}
.btn-danger {
    background: #dc3545;
    color: #fff;
}
.table-box {
    background: #fff;
    border-radius: 12px;
    overflow: hidden;
}
table {
    width: 100%;
    border-collapse: collapse;
    min-width: 600px;
}
th {
    background: #f1f3f5;
    padding: 12px;
    text-align: left;
    font-weight: 600;
}
td {
    padding: 12px;
    border-top: 1px solid #e9ecef;
}
.pagination {
    display: flex;
    justify-content: center;
    gap: 8px;
    margin-top: 20px;
    flex-wrap: wrap;
}
.page-item {
    padding: 6px 12px;
    border: 1px solid #ddd;
    border-radius: 6px;
    text-decoration: none;
    color: #333;
}
.page-item.active {
    background: #12B886;
    color: #fff;
    border-color: #12B886;
}
.alert {
    padding: 12px 16px;
    border-radius: 8px;
    margin-bottom: 16px;
}
.alert-success {
    background: #d1fae5;
    color: #065f46;
    border: 1px solid #badbcc;
}
.alert-danger {
    background: #fee2e2;
    color: #991b1b;
    border: 1px solid #fecdd3;
}
.empty-tip {
    text-align: center;
    padding: 40px;
    color: #6c757d;
}
@media (max-width: 768px) {
    .wrap {
        flex-direction: column;
    }
    .sidebar {
        width: 100%;
    }
    .form-row {
        flex-direction: column;
        align-items: stretch;
    }
    .btn {
        width: 100%;
    }
}
</style>
</head>
<body>
<div class="wrap">
    <div class="sidebar">
        <h3>管理后台</h3>
        <a href="index.php"><i class="fas fa-tachometer-alt"></i> 仪表盘</a>
        <a href="users.php"><i class="fas fa-users"></i> 用户管理</a>
        <a href="ip_blacklist.php" class="active"><i class="fas fa-ban"></i> IP封禁</a>
        <a href="clean_uploads.php"><i class="fas fa-image"></i> 图片清理</a>
        <a href="clean_videos.php"><i class="fas fa-video"></i> 视频清理</a>
        <a href="clean_music.php"><i class="fas fa-music"></i> 音乐音频清理</a>
        <a href="../chat.php"><i class="fas fa-comments"></i> 返回聊天室</a>
    </div>
    <div class="main">
        <div class="page-head">
            <div class="page-title">IP 封禁管理</div>
            <div class="text-muted"><i class="far fa-clock"></i> <?php echo date('Y-m-d H:i:s') ?></div>
        </div>
        <div class="card">
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <form method="POST" class="form-row">
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <div class="form-group">
                    <label>IP 地址</label>
                    <input type="text" name="ip" class="form-control" placeholder="例如：192.168.1.1 / 2001::1" required maxlength="50">
                </div>
                <div class="form-group">
                    <label>封禁原因</label>
                    <input type="text" name="reason" class="form-control" placeholder="说明封禁原因" required maxlength="200">
                </div>
                <button type="submit" class="btn btn-success">添加封禁</button>
            </form>
        </div>
        <div class="table-box">
            <table>
                <tr>
                    <th>ID</th>
                    <th>IP</th>
                    <th>原因</th>
                    <th>封禁时间</th>
                    <th>操作</th>
                </tr>
                <?php if (empty($list)): ?>
                    <tr>
                        <td colspan="5" class="empty-tip">暂无封禁IP数据</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($list as $item): ?>
                    <tr>
                        <td><?php echo (int)$item['id']; ?></td>
                        <td><?php echo htmlspecialchars($item['ip']); ?></td>
                        <td><?php echo htmlspecialchars($item['reason']); ?></td>
                        <td><?php echo htmlspecialchars($item['created_at']); ?></td>
                        <td>
                            <form method="POST" style="display: inline;" onsubmit="return confirm('确定解封该IP？')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?php echo (int)$item['id']; ?>">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                <button type="submit" class="btn btn-sm btn-danger">解封</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </table>
        </div>
        <div class="pagination">
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <a href="?page=<?php echo $i; ?>" class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                    <?php echo $i; ?>
                </a>
            <?php endfor; ?>
        </div>
    </div>
</div>
</body>
</html>
