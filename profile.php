<?php
define('IN_CHAT', true);
require_once 'config.php';

// 检查登录
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// 获取用户信息
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// 处理提交
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nickname = trim($_POST['nickname']);
    $signature = trim($_POST['signature']);
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];

    // 头像上传
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $filename = $_FILES['avatar']['name'];
        $filesize = $_FILES['avatar']['size'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if ($filesize > 5 * 1024 * 1024) {
            $error = "头像不能超过5MB";
        } elseif (!in_array($ext, $allowed)) {
            $error = "仅支持 jpg、jpeg、png、gif";
        } else {
            $new_filename = uniqid() . '.' . $ext;
            $upload_path = 'uploads/avatars/' . $new_filename;

            if (move_uploaded_file($_FILES['avatar']['tmp_name'], $upload_path)) {
                if ($user['avatar'] && file_exists($user['avatar'])) {
                    @unlink($user['avatar']);
                }
                $stmt = $pdo->prepare("UPDATE users SET avatar = ? WHERE id = ?");
                $stmt->execute([$upload_path, $_SESSION['user_id']]);
                $success = "头像更新成功";
            } else {
                $error = "头像上传失败";
            }
        }
    }

    // 更新资料
    $stmt = $pdo->prepare("UPDATE users SET nickname = ?, signature = ? WHERE id = ?");
    $stmt->execute([$nickname, $signature, $_SESSION['user_id']]);

    // 修改密码
    if (!empty($current_password) && !empty($new_password)) {
        if (password_verify($current_password, $user['password'])) {
            $hashed = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hashed, $_SESSION['user_id']]);
            $success = "资料与密码已更新";
        } else {
            $error = "当前密码错误";
        }
    } else {
        $success = "资料保存成功";
    }

    // 刷新用户信息
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>个人资料 - 在线聊天室</title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;background:#F8F9FA;padding:20px;min-height:100vh}
.container{max-width:680px;margin:0 auto}
.profile-box{background:#fff;border-radius:20px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,0.06);margin-bottom:30px}
.profile-head{padding:30px 24px;text-align:center;position:relative}
.back-btn{position:absolute;left:20px;top:20px;color:#495057;text-decoration:none;font-size:14px}
.back-btn i{margin-right:6px}
.avatar-area{position:relative;width:110px;height:110px;margin:0 auto 16px}
.avatar{width:100%;height:100%;border-radius:50%;object-fit:cover;border:3px solid #12B886}
.avatar-btn{position:absolute;right:0;bottom:0;width:34px;height:34px;background:#12B886;color:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;cursor:pointer}
.avatar-btn input{display:none}
.username{font-size:20px;font-weight:600;color:#212529;margin-bottom:6px}
.sub-title{font-size:14px;color:#868E96}
.profile-body{padding:24px}
.form-item{margin-bottom:20px}
.form-label{font-size:14px;font-weight:500;color:#333;margin-bottom:8px;display:block}
.form-input{width:100%;height:46px;border:1px solid #DEE2E6;border-radius:12px;padding:0 14px;font-size:15px;outline:none}
.form-input:focus{border-color:#12B886}
textarea.form-input{height:100px;resize:none;padding-top:12px}
.pwd-box{background:#F8F9FA;border-radius:16px;padding:20px;margin-top:20px}
.pwd-title{font-size:16px;font-weight:500;color:#212529;margin-bottom:16px}
.btn-submit{width:100%;height:48px;background:#12B886;color:#fff;border:none;border-radius:12px;font-size:16px;font-weight:500;margin-top:24px;cursor:pointer}
.btn-submit:hover{background:#0CA678}
.alert{padding:12px 16px;border-radius:10px;margin-bottom:20px;font-size:14px}
.alert-success{background:#D1FAE5;color:#065F46;border:1px solid #6EE7B7}
.alert-error{background:#FFF1F0;color:#C92A2A;border:1px solid #FFC9C7}
</style>
</head>
<body>

<div class="container">
  <div class="profile-box">
    <form method="POST" enctype="multipart/form-data">
      <div class="profile-head">
        <a href="chat.php" class="back-btn"><i class="fas fa-arrow-left"></i>返回聊天室</a>
        <div class="avatar-area">
          <img src="<?php echo $user['avatar'] ?: 'assets/default-avatar.png'; ?>" class="avatar" id="preview">
          <label class="avatar-btn">
            <i class="fas fa-camera"></i>
            <input type="file" name="avatar" accept="image/*" onchange="preImg(this)">
          </label>
        </div>
        <div class="username"><?php echo htmlspecialchars($user['username']); ?></div>
        <div class="sub-title">编辑个人资料</div>
      </div>

      <div class="profile-body">
        <?php if (isset($success)): ?>
        <div class="alert alert-success"><i class="fas fa-check me-1"></i><?php echo $success; ?></div>
        <?php endif; ?>
        <?php if (isset($error)): ?>
        <div class="alert alert-error"><i class="fas fa-exclamation-circle me-1"></i><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="form-item">
          <label class="form-label">昵称</label>
          <input type="text" name="nickname" class="form-input" value="<?php echo htmlspecialchars($user['nickname']); ?>">
        </div>

        <div class="form-item">
          <label class="form-label">个性签名</label>
          <textarea name="signature" class="form-input"><?php echo htmlspecialchars($user['signature']); ?></textarea>
        </div>

        <div class="pwd-box">
          <div class="pwd-title">修改密码（留空不修改）</div>
          <div class="form-item">
            <label class="form-label">当前密码</label>
            <input type="password" name="current_password" class="form-input">
          </div>
          <div class="form-item">
            <label class="form-label">新密码</label>
            <input type="password" name="new_password" class="form-input">
          </div>
        </div>

        <button type="submit" class="btn-submit"><i class="fas fa-save me-1"></i>保存修改</button>
      </div>
    </form>
  </div>
</div>

<script>
function preImg(input){
  if(input.files&&input.files[0]){
    let r=new FileReader();
    r.onload=function(e){document.getElementById('preview').src=e.target.result}
    r.readAsDataURL(input.files[0]);
  }
}
</script>
</body>
</html>
