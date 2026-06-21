<?php
if (file_exists('config.php')) {
    die('yxxsu聊天室已经安装，如需重新安装请先删除 config.php 文件。');
}
if (version_compare(PHP_VERSION, '7.4.0', '<')) {
    die('需要 PHP 7.4 或更高版本');
}
$required_extensions = ['pdo', 'pdo_mysql', 'gd', 'json'];
foreach ($required_extensions as $ext) {
    if (!extension_loaded($ext)) {
        die("需要 {$ext} 扩展");
    }
}
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $db_config = [
        'host' => $_POST['db_host'],
        'username' => $_POST['db_username'],
        'password' => $_POST['db_password'],
        'database' => $_POST['db_database']
    ];
    
    $admin_info = [
        'username' => $_POST['admin_username'],
        'password' => $_POST['admin_password'],
        'nickname' => $_POST['admin_nickname']
    ];
    
    try {
        $pdo = new PDO(
            "mysql:host={$db_config['host']};charset=utf8mb4",
            $db_config['username'],
            $db_config['password']
        );
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $pdo->exec("DROP DATABASE IF EXISTS {$db_config['database']}");
        $pdo->exec("CREATE DATABASE {$db_config['database']} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE {$db_config['database']}");
        
        $pdo->exec("CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            nickname VARCHAR(50),
            avatar VARCHAR(255),
            signature TEXT,
            ip VARCHAR(45),
            status TINYINT DEFAULT 1 COMMENT '1:正常,0:禁用',
            is_admin TINYINT DEFAULT 0,
            custom_location VARCHAR(100) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            last_login TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )");
        
        $pdo->exec("CREATE TABLE IF NOT EXISTS messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            content TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )");
        
        $pdo->exec("CREATE TABLE IF NOT EXISTS user_locations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            ip VARCHAR(45) NOT NULL,
            city VARCHAR(100) NOT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX (user_id),
            INDEX (updated_at),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )");
        
        $pdo->exec("CREATE TABLE IF NOT EXISTS ip_blacklist (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ip VARCHAR(45) NOT NULL,
            reason TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY (ip)
        )");
        
        $pdo->exec("CREATE TABLE IF NOT EXISTS online_users (
            user_id INT PRIMARY KEY,
            last_active TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )");
        
        $config_content = "<?php
if (!defined('IN_CHAT')) {
    die('Access Denied');
}
\$db_host = '{$db_config['host']}';
\$db_name = '{$db_config['database']}';
\$db_user = '{$db_config['username']}';
\$db_pass = '{$db_config['password']}';
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'logs/error.log');
session_start();
try {
    \$pdo = new PDO(\"mysql:host=\$db_host;dbname=\$db_name;charset=utf8mb4\", \$db_user, \$db_pass);
    \$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException \$e) {
    die(\"数据库连接失败: \" . \$e->getMessage());
}
";
        if (file_put_contents('config.php', $config_content) === false) {
            throw new Exception("无法创建配置文件，请检查目录权限");
        }
        
        $directories = ['uploads','uploads/avatars','assets','logs'];
        foreach ($directories as $dir) {
            if (!file_exists($dir)) {
                mkdir($dir, 0777, true);
                chmod($dir, 0777);
            }
        }
        
        $admin_password = password_hash($admin_info['password'], PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (username, password, nickname, is_admin) VALUES (?, ?, ?, 1)");
        $stmt->execute([$admin_info['username'], $admin_password, $admin_info['nickname']]);
        
        file_put_contents('install.lock', date('Y-m-d H:i:s'));
        
        $success = true;
        $message = "安装成功！<br>管理员账号：{$admin_info['username']}<br>密码：{$admin_info['password']}<br><a href='login.php' class='btn btn-green mt-3'>立即登录</a>";
        
    } catch (Exception $e) {
        $error = '安装失败: ' . $e->getMessage();
    }
}
file_put_contents('install.lock', date('Y-m-d H:i:s'));
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>安装 - 在线聊天室-yxxsu</title>
    <style>
        *{box-sizing:border-box;margin:0;padding:0}
        body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;background:#f7f8fa;color:#333;line-height:1.5}
        .container{max-width:800px;margin:40px auto;padding:0 20px}
        .card{background:#fff;border-radius:12px;border:1px solid #e5e7eb;box-shadow:0 2px 8px rgba(0,0,0,0.04)}
        .card-header{padding:18px 24px;border-bottom:1px solid #f0f0f0;background:#fafbfc}
        .card-header h4{font-size:20px;font-weight:600;color:#222}
        .card-body{padding:24px}
        .mb-3{margin-bottom:16px}
        .form-label{font-weight:500;color:#37352f;margin-bottom:6px;display:block}
        .form-control{width:100%;height:42px;padding:8px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:15px;background:#fff}
        .form-control:focus{outline:none;border-color:#10b981;box-shadow:0 0 0 2px rgba(16,185,129,0.15)}
        hr{height:1px;background:#f0f0f0;border:none;margin:24px 0}
        .btn{display:block;width:100%;height:44px;line-height:44px;text-align:center;border:none;border-radius:8px;font-size:15px;font-weight:500;cursor:pointer}
        .btn-green{background:#10b981;color:#fff}
        .btn-green:hover{background:#059669}
        .alert{padding:14px 16px;border-radius:8px;margin-bottom:16px}
        .alert-success{background:#d1fae5;color:#065f46;border:1px solid #6ee7b7}
        .alert-danger{background:#fee2e2;color:#991b1b;border:1px solid #fecaca}
        .mt-3{margin-top:12px}
        h5{font-size:17px;font-weight:600;color:#222;margin-bottom:12px}
    </style>
</head>
<body>
<div class="container">
    <div class="card">
        <div class="card-header">
            <h4>yxxsu聊天室 - 安装向导</h4>
        </div>
        <div class="card-body">
            <?php if (isset($success)): ?>
                <div class="alert alert-success"><?php echo $message; ?></div>
            <?php elseif (isset($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            <?php if (!isset($success)): ?>
            <form method="post">
                <h5>数据库配置</h5>
                <div class="mb-3">
                    <label class="form-label">数据库主机</label>
                    <input type="text" name="db_host" class="form-control" value="localhost" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">数据库用户名</label>
                    <input type="text" name="db_username" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">数据库密码</label>
                    <input type="password" name="db_password" class="form-control">
                </div>
                <div class="mb-3">
                    <label class="form-label">数据库名</label>
                    <input type="text" name="db_database" class="form-control" required>
                </div>
                <hr>
                <h5>管理员账号设置</h5>
                <div class="mb-3">
                    <label class="form-label">管理员用户名</label>
                    <input type="text" name="admin_username" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">管理员密码</label>
                    <input type="password" name="admin_password" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">管理员昵称</label>
                    <input type="text" name="admin_nickname" class="form-control" value="管理员" required>
                </div>
                <button type="submit" class="btn btn-green">开始安装</button>
            </form>
            <?php endif; ?>
        </div>
    </div>
</div>
<script>
(function(){
    var forms=document.querySelectorAll('form');
    forms.forEach(function(f){
        f.addEventListener('submit',function(e){
            if(!f.checkValidity()){e.preventDefault();e.stopPropagation()}
            f.classList.add('was-validated')
        })
    })
})()
</script>
</body>
</html>
