<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';


$token = $_GET['token'] ?? '';
$error = '';
$success = '';

if (!$token) {
    die("Invalid reset link.");
}


$stmt = $pdo->prepare("SELECT user_id, reset_expires FROM users WHERE reset_token=?");
$stmt->execute([$token]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || strtotime($user['reset_expires']) < time()) {
    die("This reset link is invalid or has expired.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    if (strlen($password) < 6) {
        $error = "Password must be at least 6 characters.";
    } elseif ($password !== $confirm) {
        $error = "Passwords do not match.";
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password_hash=?, reset_token=NULL, reset_expires=NULL WHERE user_id=?");
        $stmt->execute([$hash, $user['user_id']]);
        $success = "Password has been reset successfully. <a href='login.php'>Login here</a>";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Reset Password - TrackItPro</title>
  <style>
    body { font-family: Arial, sans-serif; background:#f3f4f6; display:flex; justify-content:center; align-items:center; height:100vh; }
    .container { background:white; padding:30px; border-radius:10px; box-shadow:0 5px 20px rgba(0,0,0,0.15); width:350px; }
    h2 { margin-bottom:15px; color:#1f2937; }
    .form-group { margin-bottom:15px; }
    label { display:block; margin-bottom:5px; font-size:14px; }
    input[type=password] { width:100%; padding:10px; border:1px solid #d1d5db; border-radius:5px; }
    button { width:100%; padding:12px; background:#76b5c5; color:white; border:none; border-radius:7px; cursor:pointer; }
    button:hover { background:#1e3a3a; }
    .message { margin-top:10px; font-size:14px; }
    .error { color:red; }
    .success { color:green; }
  </style>
</head>
<body>
  <div class="container">
    <h2>Reset Password</h2>
    <?php if ($error): ?><p class="message error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
    <?php if ($success): ?><p class="message success"><?= $success ?></p><?php else: ?>
    <form method="POST">
      <div class="form-group">
        <label for="password">New Password</label>
        <input type="password" name="password" id="password" required>
      </div>
      <div class="form-group">
        <label for="confirm_password">Confirm Password</label>
        <input type="password" name="confirm_password" id="confirm_password" required>
      </div>
      <button type="submit">Reset Password</button>
    </form>
    <?php endif; ?>
  </div>
</body>
</html>
