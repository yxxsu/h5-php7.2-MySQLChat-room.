<?php
define('IN_CHAT', true);
require_once '../config.php';
if (!isset($_SESSION['user_id']) || !$_SESSION['is_admin']) {
    header("Location: ../login.php");
    exit;
}
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $user_id = (int)$_POST['user_id'];
    $action = $_POST['action'];
    switch ($action) {
        case 'ban': $pdo->prepare("UPDATE users SET status=0 WHERE id=?")->execute([$user_id]); break;
        case 'unban': $pdo->prepare("UPDATE users SET status=1 WHERE id=?")->execute([$user_id]); break;
        case 'delete':
            $pdo->prepare("DELETE FROM messages WHERE user_id=?")->execute([$user_id]);
            $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$user_id]);
            break;
        case 'ban_ip':
            $ip = $pdo->prepare("SELECT ip FROM users WHERE id=?")->execute([$user_id])->fetchColumn();
            if($ip) $pdo->prepare("INSERT INTO ip_blacklist(ip,reason) VALUES(?,?)")->execute([$ip,"管理员封禁"]);
            break;
        case 'update_location':
            $loc = trim($_POST['custom_location']);
            $pdo->prepare("UPDATE users SET custom_location=? WHERE id=?")->execute([$loc,$user_id]);
            break;
    }
}
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per = 20;
$offset = ($page-1)*$per;
$total = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$pages = ceil($total/$per);
$users = $pdo->query("SELECT * FROM users ORDER BY created_at DESC LIMIT $per OFFSET $offset")->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>用户管理</title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;background:#F8F9FA}
.wrap{display:flex;min-height:100vh}
.sidebar{width:240px;background:#212529;padding:24px 0}
.side-title{color:#fff;text-align:center;margin-bottom:24px}
.side-link{display:flex;align-items:center;gap:12px;padding:12px 20px;color:#e9ecef;text-decoration:none;border-radius:8px;margin:0 8px}
.side-link.active{background:#12B886;color:#fff}
.main{flex:1;padding:24px}
.page-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:16px}
.table-box{background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.04)}
table{width:100%;border-collapse:collapse}
th{background:#f1f3f5;padding:12px;text-align:left;font-weight:600}
td{padding:12px;border-top:1px solid #e9ecef}
.avatar{width:36px;height:36px;border-radius:50%;object-fit:cover}
.badge{padding:4px 10px;border-radius:12px;font-size:12px}
.badge-success{background:#d1fae5;color:#065f46}
.badge-danger{background:#fee2e2;color:#991b1b}
.btn{padding:6px 10px;border-radius:6px;border:none;cursor:pointer}
.btn-primary{background:#12B886;color:#fff}
.btn-warning{background:#f59e0b;color:#fff}
.btn-danger{background:#ef4444;color:#fff}
.btn-info{background:#38bdf8;color:#fff}
.btn-dark{background:#475569;color:#fff}
.pagination{display:flex;justify-content:center;gap:8px;margin-top:16px}
.page-btn{padding:6px 12px;border:1px solid #ddd;border-radius:6px;text-decoration:none;color:#333}
.page-btn.active{background:#12B886;color:#fff;border-color:#12B886}
</style>
</head>
<body>
<div class="wrap">
    <div class="sidebar">
        <h3 class="side-title">管理后台</h3>
        <a href="index.php" class="side-link"><i class="fas fa-tachometer-alt"></i> 仪表盘</a>
        <a href="users.php" class="side-link active"><i class="fas fa-users"></i> 用户管理</a>
        <a href="ip_blacklist.php" class="side-link"><i class="fas fa-ban"></i> IP封禁</a>
        <a href="clean_uploads.php" class="side-link"><i class="fas fa-image"></i> 图片清理</a>
        <a href="clean_videos.php" class="side-link"><i class="fas fa-video"></i> 视频清理</a>
        <a href="clean_music.php" class="side-link"><i class="fas fa-music"></i> 音乐音频清理</a>
        <a href="../chat.php" class="side-link"><i class="fas fa-comments"></i> 返回聊天室</a>
    </div>
    <div class="main">
        <div class="page-head">
            <h2>用户管理</h2>
            <div class="text-muted"><i class="far fa-clock"></i> <?=date('Y-m-d H:i:s')?></div>
        </div>
        <div class="table-box">
            <table>
                <tr>
                    <th>ID</th><th>头像</th><th>用户名</th><th>昵称</th><th>IP</th><th>状态</th><th>注册</th><th>操作</th>
                </tr>
                <?php foreach($users as $u): ?>
                <tr>
                    <td><?=$u['id']?></td>
                    <td><img src="<?=($u['avatar']?'../'.$u['avatar']:'../assets/default-avatar.png')?>" class="avatar"></td>
                    <td><?=htmlspecialchars($u['username'])?></td>
                    <td><?=htmlspecialchars($u['nickname'])?></td>
                    <td><?=$u['ip']?></td>
                    <td>
                        <span class="badge badge-<?=$u['status']==1?'success':'danger'?>">
                            <?=$u['status']==1?'正常':'禁用'?>
                        </span>
                    </td>
                    <td><?=$u['created_at']?></td>
                    <td>
                        <a href="user_messages.php?user_id=<?=$u['id']?>" class="btn btn-info"><i class="fas fa-comments"></i></a>
                        <button class="btn btn-warning" onclick="act(<?=$u['id']?>,'<?=$u['status']==1?'ban':'unban'?>')">
                            <i class="fas fa-user-<?=$u['status']==1?'slash':'check'?>"></i>
                        </button>
                        <button class="btn btn-danger" onclick="act(<?=$u['id']?>,'delete')"><i class="fas fa-trash"></i></button>
                        <button class="btn btn-dark" onclick="act(<?=$u['id']?>,'ban_ip')"><i class="fas fa-ban"></i></button>
                        <button class="btn btn-primary" onclick="loc(<?=$u['id']?>,'<?=htmlspecialchars($u['custom_location']??'')?>')">
                            <i class="fas fa-map-marker-alt"></i>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
        <div class="pagination">
            <?php for($i=1;$i<=$pages;$i++): ?>
            <a href="?page=<?=$i?>" class="page-btn <?=$i==$page?'active':''?>"><?=$i?></a>
            <?php endfor; ?>
        </div>
    </div>
</div>
<script>
function act(uid,act){
    const t = {ban:'封禁？',unban:'解封？',delete:'删除？',ban_ip:'封禁IP？'}[act];
    if(!confirm(t))return;
    const f=document.createElement('form');
    f.method='post';
    f.innerHTML=`<input name=user_id value=${uid}><input name=action value=${act}>`;
    document.body.appendChild(f);f.submit();
}
function loc(uid,loc){
    const v = prompt('自定义位置',loc);
    if(v===null)return;
    const f=document.createElement('form');
    f.method='post';
    f.innerHTML=`<input name=user_id value=${uid}><input name=action value=update_location><input name=custom_location value="${v.replace(/"/g,'')}">`;
    document.body.appendChild(f);f.submit();
}
</script>
</body>
</html>
