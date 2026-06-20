<?php
define('IN_CHAT', true);
require_once 'config.php';

// 优先检测IP封禁 → 显示图片
$ip = $_SERVER['REMOTE_ADDR'];
$stmt = $pdo->prepare("SELECT * FROM ip_blacklist WHERE ip = ?");
$stmt->execute([$ip]);
if ($stmt->rowCount() > 0) {
    header("Content-Type: image/png");
    readfile("res/ip.png");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $nickname = trim($_POST['nickname']);
    
    try {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            $error = '用户名已存在';
        } else {
            if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
                $ip = $_SERVER['HTTP_CLIENT_IP'];
            } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
            } else {
                $ip = $_SERVER['REMOTE_ADDR'];
            }

            $pdo->beginTransaction();
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt = $pdo->prepare("INSERT INTO users (username, password, nickname, ip) VALUES (?, ?, ?, ?)");
            $stmt->execute([$username, $hashed_password, $nickname, $ip]);
            
            $userId = $pdo->lastInsertId();
            $stmt = $pdo->prepare("INSERT INTO user_locations (user_id, ip, city) VALUES (?, ?, '未知')");
            $stmt->execute([$userId, $ip]);
            
            $pdo->commit();
            header('Location: login.php?registered=1');
            exit;
        }
    } catch (PDOException $e) {
        $error = '注册失败: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>注册 - 在线聊天室</title>
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
            max-width: 440px;
        }
        .card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06);
            overflow: hidden;
            animation: fadeIn 0.4s ease;
        }
        .card-header {
            padding: 28px 20px 18px;
            text-align: center;
            border-bottom: 1px solid #F1F3F5;
        }
        .card-header h4 {
            font-size: 22px;
            font-weight: 600;
            color: #212529;
        }
        .card-body {
            padding: 28px 24px;
        }
        .form-label {
            font-weight: 500;
            color: #333;
            margin-bottom: 8px;
            display: block;
        }
        .form-control {
            width: 100%;
            height: 46px;
            border: 1px solid #DEE2E6;
            border-radius: 10px;
            padding: 0 14px;
            font-size: 15px;
            outline: none;
            transition: border-color 0.2s;
        }
        .form-control:focus {
            border-color: #12B886;
        }
        .form-text {
            font-size: 12px;
            color: #868E96;
            margin-top: 5px;
        }
        .btn {
            height: 46px;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 500;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: 0.2s;
            border: none;
            outline: none;
        }
        .btn-primary {
            background: #12B886;
            color: #fff;
        }
        .btn-primary:hover {
            background: #0CA678;
        }
        .btn-light {
            background: #F1F3F5;
            color: #495057;
        }
        .btn-light:hover {
            background: #E9ECEF;
        }
        .alert {
            padding: 12px 15px;
            border-radius: 8px;
            font-size: 14px;
            background: #FFF1F0;
            color: #C92A2A;
            border: 1px solid #FFC9C7;
            margin-bottom: 20px;
        }
        .mb-3 {
            margin-bottom: 18px;
        }
        .d-grid {
            display: grid;
            gap: 10px;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="card-header">
                <h4 class="mb-0">注册新账号</h4>
            </div>
            <div class="card-body">
                <?php if (isset($error)): ?>
                    <div class="alert"><?php echo $error; ?></div>
                <?php endif; ?>
                <form method="POST" novalidate>
                    <div class="mb-3">
                        <label class="form-label">用户名</label>
                        <input type="text" name="username" class="form-control" required 
                               pattern="[a-zA-Z0-9_]{3,20}">
                        <div class="form-text">只能包含字母、数字和下划线，长度3-20位</div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">密码</label>
                        <input type="password" name="password" class="form-control" required minlength="6">
                        <div class="form-text">密码至少6位</div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">昵称</label>
                        <input type="text" name="nickname" class="form-control" required>
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">注册</button>
                        <a href="login.php" class="btn btn-light">返回登录</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
<script>
(function(){
    var forms = document.querySelectorAll('form');
    forms.forEach(f => {
        f.addEventListener('submit', e => {
            if(!f.checkValidity()){ e.preventDefault(); e.stopPropagation(); }
            f.classList.add('was-validated');
        })
    })
})();
</script>
</body>
</html>
