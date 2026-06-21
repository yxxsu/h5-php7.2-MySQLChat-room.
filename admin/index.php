<?php
define('IN_CHAT', true);
require_once '../config.php';

if (!isset($_SESSION['user_id']) || !$_SESSION['is_admin']) {
    header("Location: ../login.php");
    exit;
}

$stats = [
    'total_users' => $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
    'active_users' => $pdo->query("SELECT COUNT(*) FROM users WHERE status = 1")->fetchColumn(),
    'banned_users' => $pdo->query("SELECT COUNT(*) FROM users WHERE status = 0")->fetchColumn(),
    'total_messages' => $pdo->query("SELECT COUNT(*) FROM messages")->fetchColumn(),
    'banned_ips' => $pdo->query("SELECT COUNT(*) FROM ip_blacklist")->fetchColumn(),
    'today_messages' => $pdo->query("SELECT COUNT(*) FROM messages WHERE DATE(created_at) = CURDATE()")->fetchColumn(),
    'today_users' => $pdo->query("SELECT COUNT(DISTINCT user_id) FROM messages WHERE DATE(created_at) = CURDATE()")->fetchColumn()
];

// 系统日志（纯文本 Linux 风格）
$log = [];
$now = date('Y-m-d H:i:s');
$log[] = "[$now] 管理员查看仪表盘";
$log[] = "[$now] 在线用户：{$stats['active_users']} 人";
$log[] = "[$now] 今日消息：{$stats['today_messages']} 条";
$log[] = "[$now] 今日发言用户：{$stats['today_users']} 人";
$log[] = "[$now] 系统运行正常";
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>管理后台</title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;background:#F8F9FA;color:#212529}
/* 布局 */
.wrap{display:flex;min-height:100vh}
/* 侧边栏 */
.sidebar{width:240px;background:#212529;padding:24px 0;flex-shrink:0}
.side-title{color:#fff;text-align:center;margin-bottom:24px;font-size:18px}
.side-menu{list-style:none;padding:0}
.side-item{margin-bottom:4px}
.side-link{display:flex;align-items:center;gap:12px;padding:12px 20px;color:#e9ecef;text-decoration:none;border-radius:8px;margin:0 8px}
.side-link:hover{background:#343a40;color:#fff}
.side-link.active{background:#12B886;color:#fff}
/* 主内容 */
.main{flex:1;padding:24px}
.page-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:24px}
.page-title{font-size:22px;font-weight:600}
.time{color:#6c757d}
/* 统计卡片 */
.card-box{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:24px}
.card{background:#fff;border-radius:12px;padding:20px;box-shadow:0 2px 8px rgba(0,0,0,0.04);border-left:4px solid #12B886}
.card-title{font-size:14px;color:#6c757d;margin-bottom:8px}
.card-num{font-size:24px;font-weight:700;color:#212529}
.card-icon{font-size:22px;color:#12B886;opacity:0.8}
.card-body{display:flex;justify-content:space-between;align-items:center}
/* 日志面板 */
.log-box{background:#1e1e1e;color:#dcdcdc;border-radius:12px;padding:20px; height:280px;overflow-y:auto;font-family:Consolas,Monaco,monospace;font-size:13px;line-height:1.6}
.log-item{white-space:pre}
</style>
</head>
<body>
<div class="wrap">
    <!-- 侧边栏 -->
    <div class="sidebar">
        <h3 class="side-title">管理后台</h3>
        <ul class="side-menu">
            <li class="side-item"><a href="index.php" class="side-link active"><i class="fas fa-tachometer-alt"></i> 仪表盘</a></li>
            <li class="side-item"><a href="users.php" class="side-link"><i class="fas fa-users"></i> 用户管理</a></li>
            <li class="side-item"><a href="ip_blacklist.php" class="side-link"><i class="fas fa-ban"></i> IP封禁</a></li>
            <li class="side-item"><a href="../chat.php" class="side-link"><i class="fas fa-comments"></i> 返回聊天室</a></li>
        </ul>
    </div>

    <!-- 主内容 -->
    <div class="main">
        <div class="page-head">
            <div class="page-title">系统概况</div>
            <div class="time"><i class="far fa-clock"></i> <?php echo date('Y-m-d H:i:s') ?></div>
        </div>

        <!-- 统计 -->
        <div class="card-box">
            <div class="card">
                <div class="card-body">
                    <div>
                        <div class="card-title">总用户数</div>
                        <div class="card-num"><?=$stats['total_users']?></div>
                    </div>
                    <i class="fas fa-users card-icon"></i>
                </div>
            </div>
            <div class="card">
                <div class="card-body">
                    <div>
                        <div class="card-title">活跃用户</div>
                        <div class="card-num"><?=$stats['active_users']?></div>
                    </div>
                    <i class="fas fa-user-check card-icon"></i>
                </div>
            </div>
            <div class="card">
                <div class="card-body">
                    <div>
                        <div class="card-title">封禁用户</div>
                        <div class="card-num"><?=$stats['banned_users']?></div>
                    </div>
                    <i class="fas fa-user-slash card-icon"></i>
                </div>
            </div>
            <div class="card">
                <div class="card-body">
                    <div>
                        <div class="card-title">IP黑名单</div>
                        <div class="card-num"><?=$stats['banned_ips']?></div>
                    </div>
                    <i class="fas fa-ban card-icon"></i>
                </div>
            </div>
            <div class="card">
                <div class="card-body">
                    <div>
                        <div class="card-title">总消息</div>
                        <div class="card-num"><?=$stats['total_messages']?></div>
                    </div>
                    <i class="fas fa-comments card-icon"></i>
                </div>
            </div>
            <div class="card">
                <div class="card-body">
                    <div>
                        <div class="card-title">今日消息</div>
                        <div class="card-num"><?=$stats['today_messages']?></div>
                    </div>
                    <i class="fas fa-comment-dots card-icon"></i>
                </div>
            </div>
            <div class="card">
                <div class="card-body">
                    <div>
                        <div class="card-title">今日活跃</div>
                        <div class="card-num"><?=$stats['today_users']?></div>
                    </div>
                    <i class="fas fa-user-clock card-icon"></i>
                </div>
            </div>
        </div>

        <!-- 系统日志（Linux 风格） -->
        <div class="page-title mb-2">系统日志</div>
        <div class="log-box">
<?php foreach($log as $line): ?>
<div class="log-item"><?=$line?></div>
<?php endforeach; ?>
        </div>
    </div>
</div>
</body>
</html>
