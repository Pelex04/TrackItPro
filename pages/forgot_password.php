<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../vendor/autoload.php';



// Generate CSRF token if not set
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Invalid request.";
    } else {
        $email = trim($_POST['email'] ?? '');

        if (empty($email)) {
            $error = "Email is required.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Invalid email format.";
        } else {
            // Look up user
            $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                $token = bin2hex(random_bytes(32));
                $expires = date("Y-m-d H:i:s", strtotime("+1 hour"));

                $stmt = $pdo->prepare("UPDATE users SET reset_token=?, reset_expires=? WHERE user_id=?");
                $stmt->execute([$token, $expires, $user['user_id']]);

                $reset_link = APP_URL . "/pages/reset_password.php?token=" . urlencode($token);

                // TODO: Use PHPMailer for production


              $mail = new PHPMailer(true);

              try {
                  $mail->isSMTP();
                  $mail->Host       = 'smtp.gmail.com';
                  $mail->SMTPAuth   = true;
                  $mail->Username   = ''; // your Gmail
                  $mail->Password   = '';   // your 16-char App Password
                  $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                  $mail->Port       = 587;

                  $mail->setFrom('', 'TrackItPro');
                  $mail->addAddress($email);

                  $mail->isHTML(true);
                  $mail->Subject = "Password Reset - TrackItPro";
                  $mail->Body    = "Click <a href='$reset_link'>here</a> to reset your password.<br><br>
                      This link will expire in 1 hour.";

                  $mail->send();
                  $success = "If this email is registered, you will receive a reset link.";
              } 
              catch (Exception $e) {
                    $error = "Email could not be sent. Error: {$mail->ErrorInfo}";
                }


            }

            // Always success message (no info leak)
            $success = "If this email is registered, you will receive a reset link.";
        }
    }

    // Refresh CSRF
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Forgot Password - TrackItPro</title>
  <style>
    body { font-family: Arial, sans-serif; background:#f3f4f6; display:flex; justify-content:center; align-items:center; height:100vh; }
    .container { background:white; padding:30px; border-radius:10px; box-shadow:0 5px 20px rgba(0,0,0,0.15); width:350px; }
    h2 { margin-bottom:15px; color:#1f2937; }
    .form-group { margin-bottom:15px; }
    label { display:block; margin-bottom:5px; font-size:14px; }
    input[type=email], input[type=text] { width:100%; padding:10px; border:1px solid #d1d5db; border-radius:5px; }
    button { width:100%; padding:12px; background:#76b5c5; color:white; border:none; border-radius:7px; cursor:pointer; }
    button:hover { background:#1e3a3a; }
    .message { margin-top:10px; font-size:14px; }
    .error { color:red; }
    .success { color:green; }
    a { display:block; margin-top:15px; text-align:center; font-size:13px; color:#2d5a5a; }
  </style>
</head>
<body>
  <div class="container">
    <h2>Forgot Password</h2>
    <p>Enter your registered email address to receive a reset link.</p>

    <?php if ($error): ?><p class="message error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
    <?php if ($success): ?><p class="message success"><?= htmlspecialchars($success) ?></p><?php endif; ?>

    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>">
      <div class="form-group">
        <label for="email">Email</label>
        <input type="email" name="email" id="email" required>
      </div>
      <button type="submit">Send Reset Link</button>
    </form>

    <a href="login.php">Back to Login</a>
  </div>
</body>
</html>
