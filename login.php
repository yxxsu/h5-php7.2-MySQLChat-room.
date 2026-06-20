<?php
// 检查是否已安装
if (!file_exists('config.php')) {
    header("Location: install.php");
    exit;
}
define('IN_CHAT', true);
require_once 'config.php';

// ======================================
// 第一步：优先检测IP是否封禁
// ======================================
$ip = $_SERVER['REMOTE_ADDR'];
$stmt = $pdo->prepare("SELECT * FROM ip_blacklist WHERE ip = ?");
$stmt->execute([$ip]);

if ($stmt->rowCount() > 0) {
    // IP被封禁 → 直接显示图片，停止执行
    header("Content-Type: image/png");
    readfile("res/ip.png");
    exit;
}

// 下面是原有登录逻辑（不受影响）
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    // 验证用户
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND status = 1");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['is_admin'] = $user['is_admin'];
        
        // 更新最后登录时间
        $stmt = $pdo->prepare("UPDATE users SET last_login = NOW(), ip = ? WHERE id = ?");
        $stmt->execute([$ip, $user['id']]);
        
        header("Location: chat.php");
        exit;
    } else {
        $error = "用户名或密码错误";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>登录 - 在线聊天室</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: #F8F9FA;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            width: 100%;
            max-width: 420px;
        }
        .card {
            background: #FFFFFF;
            border-radius: 16px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.06);
            overflow: hidden;
            animation: fadeIn 0.4s ease;
        }
        .card-header {
            padding: 30px 20px 20px;
            text-align: center;
            border-bottom: 1px solid #F1F3F5;
        }
        .card-header i {
            color: #12B886;
            margin-bottom: 12px;
        }
        .card-header h4 {
            font-size: 22px;
            font-weight: 600;
            color: #212529;
        }
        .card-body {
            padding: 30px;
        }
        .input-group {
            display: flex;
            align-items: center;
            border: 1px solid #DEE2E6;
            border-radius: 10px;
            padding: 0 12px;
            height: 48px;
        }
        .input-group-text {
            color: #868E96;
            font-size: 16px;
            padding-right: 10px;
        }
        .form-control {
            flex: 1;
            border: none;
            outline: none;
            font-size: 15px;
            background: transparent;
        }
        .form-control:focus {
            outline: none;
        }
        .btn {
            width: 100%;
            height: 48px;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: 0.2s;
        }
        .btn-primary {
            background: #12B886;
            color: #fff;
            border: none;
        }
        .btn-primary:hover {
            background: #0CA678;
        }
        .btn-link {
            color: #495057;
            text-decoration: none;
            font-size: 14px;
        }
        .btn-link:hover {
            color: #212529;
            text-decoration: underline;
        }
        .alert {
            padding: 12px 15px;
            border-radius: 8px;
            font-size: 14px;
            display: flex;
            align-items: center;
            background: #FFF1F0;
            color: #C92A2A;
            border: 1px solid #FFC9C7;
            margin-bottom: 20px;
        }
        .alert i {
            margin-right: 8px;
        }
        .mb-4 {
            margin-bottom: 20px;
        }
        .text-center {
            text-align: center;
        }
        .mt-4 {
            margin-top: 24px;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @media (max-width: 768px) {
            .card-body {
                padding: 24px 20px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="card-header text-center">
                <i class="fas fa-user-circle fa-2x"></i>
                <h4 class="mb-0">用户登录</h4>
            </div>
            <div class="card-body">
                <?php if (isset($error)): ?>
                    <div class="alert">
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>
                <form method="POST">
                    <div class="mb-4">
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-user"></i>
                            </span>
                            <input type="text" name="username" class="form-control" placeholder="请输入用户名" required>
                        </div>
                    </div>
                    <div class="mb-4">
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-lock"></i>
                            </span>
                            <input type="password" name="password" class="form-control" placeholder="请输入密码" required>
                        </div>
                    </div>
                    <div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-sign-in-alt me-2"></i>登录
                        </button>
                    </div>
                    <div class="text-center mt-4">
                        <a href="register.php" class="btn btn-link">
                            <i class="fas fa-user-plus me-1"></i>没有账号？去注册
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
