<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Invalid CSRF token.";
    } else {
        // Theme update
        $theme = $_POST['theme'] === 'dark' ? 'dark' : 'light';
        setcookie('theme', $theme, time() + (365 * 24 * 60 * 60), "/");
        $stmt = $pdo->prepare("UPDATE users SET preferences = ? WHERE user_id = ?");
        $stmt->execute([json_encode(['theme' => $theme]), $_SESSION['user_id']]);

        // Notifications update
        $notifications = isset($_POST['notifications']) ? 1 : 0;
        $stmt = $pdo->prepare("UPDATE users SET preferences = JSON_SET(COALESCE(preferences, '{}'), '$.notifications', ?) WHERE user_id = ?");
        $stmt->execute([$notifications, $_SESSION['user_id']]);

        // Profile update
        if (!empty($_POST['name']) && !empty($_POST['email'])) {
            $name = trim($_POST['name']);
            $email = trim($_POST['email']);
            $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ? WHERE user_id = ?");
            $stmt->execute([$name, $email, $_SESSION['user_id']]);
            $_SESSION['name'] = $name; // Update session
            $success = "Profile updated successfully!";
        } elseif (!empty($_POST['name']) || !empty($_POST['email'])) {
            $error = "Both name and email are required for profile update.";
        }

        header("Location: settings.php");
        exit;
    }
}

$theme = isset($_COOKIE['theme']) ? $_COOKIE['theme'] : 'light';
$stmt = $pdo->prepare("SELECT name, email, JSON_EXTRACT(preferences, '$.notifications') as notifications FROM users WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
$notifications = $user['notifications'] ? 1 : 0;
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Settings - TrackItPro</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f7fa;
            color: #2d3748;
            padding: 30px;
            animation: softReveal 0.6s ease-out;
            transition: background-color 0.3s ease, color 0.3s ease;
        }

        body.dark {
            background-color: #1a202c;
            color: #e2e8f0;
        }

        @keyframes softReveal {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        h1 {
            font-size: 32px;
            font-weight: 600;
            margin-bottom: 30px;
            color: #2d3748;
            text-align: center;
            animation: slideInCenter 0.7s ease-out;
        }

        body.dark h1 {
            color: #e2e8f0;
        }

        @keyframes slideInCenter {
            from { transform: translateY(-20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .settings-container {
            max-width: 900px;
            margin: 0 auto;
        }

        .settings-section {
            background-color: #ffffff;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.05);
            border-left: 4px solid #76b5c5;
            animation: fadeAndLift 0.7s ease-out;
        }

        body.dark .settings-section {
            background-color: #2d3748;
            border-left-color: #5d9aa9;
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.2);
        }

        @keyframes fadeAndLift {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .error, .success {
            margin-bottom: 15px;
            padding: 10px;
            border-radius: 6px;
            animation: messageFade 0.5s ease-out;
        }

        .error {
            color: #e53e3e;
            background-color: #fed7d7;
        }

        body.dark .error {
            color: #feb2b2;
            background-color: #631919;
        }

        .success {
            color: #48bb78;
            background-color: #c6f6d5;
        }

        body.dark .success {
            color: #9ae6b4;
            background-color: #22543d;
        }

        @keyframes messageFade {
            from { opacity: 0; transform: translateX(-10px); }
            to { opacity: 1; transform: translateX(0); }
        }

        label {
            font-size: 18px;
            color: #4a5568;
            margin-bottom: 8px;
            display: block;
        }

        body.dark label {
            color: #a0aec0;
        }

        select, input[type="text"], input[type="email"] {
            width: 100%;
            padding: 10px;
            font-size: 16px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            background-color: #ffffff;
            color: #2d3748;
            margin-bottom: 15px;
            transition: border-color 0.3s ease, background-color 0.3s ease;
        }

        body.dark select, body.dark input[type="text"], body.dark input[type="email"] {
            background-color: #4a5568;
            border-color: #4a5568;
            color: #e2e8f0;
        }

        select:focus, input[type="text"]:focus, input[type="email"]:focus {
            outline: none;
            border-color: #76b5c5;
            box-shadow: 0 0 5px rgba(118, 181, 197, 0.3);
        }

        .preview-card {
            background-color: #edf2f7; /* Lighter contrast in light mode */
            padding: 15px;
            border-radius: 8px;
            margin-top: 20px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
            text-align: center;
            animation: previewFade 0.7s ease-out;
        }

        body.dark .preview-card {
            background-color: #2f3b4c; /* Softer, more appealing dark shade */
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
        }

        @keyframes previewFade {
            from { opacity: 0; transform: scale(0.95); }
            to { opacity: 1; transform: scale(1); }
        }

        button {
            padding: 12px 25px;
            background-color: #76b5c5;
            color: #ffffff;
            border: none;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.4s ease;
            box-shadow: 0 3px 8px rgba(0, 0, 0, 0.1);
            animation: buttonAppear 0.7s ease-out 0.2s backwards;
        }

        body.dark button {
            background-color: #5d9aa9;
        }

        @keyframes buttonAppear {
            from { opacity: 0; transform: scale(0.95); }
            to { opacity: 1; transform: scale(1); }
        }

        button:hover {
            background-color: #5d9aa9;
            transform: translateY(-2px) scale(1.02);
            box-shadow: 0 5px 12px rgba(93, 154, 169, 0.2);
        }

        a {
            display: inline-block;
            padding: 12px 25px;
            background-color: #76b5c5;
            color: #ffffff;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 500;
            margin-top: 15px;
            transition: all 0.4s ease;
            animation: buttonAppear 0.7s ease-out 0.3s backwards;
            box-shadow: 0 3px 8px rgba(0, 0, 0, 0.1);
        }

        body.dark a {
            background-color: #5d9aa9;
        }

        a:hover {
            background-color: #4f8795;
            transform: translateY(-2px) scale(1.02);
            box-shadow: 0 5px 12px rgba(79, 135, 149, 0.2);
        }

        @media (max-width: 768px) {
            body { padding: 15px; }
            .settings-section { padding: 15px; }
            h1 { font-size: 26px; }
            label { font-size: 16px; }
            select, input[type="text"], input[type="email"] { font-size: 14px; }
            button, a { padding: 10px 20px; }
            .preview-card { margin-top: 15px; }
        }
    </style>
</head>
<body class="<?php echo $theme; ?>">
    <div class="settings-container">
        <h1>Settings</h1>
        <?php if (isset($error)) echo "<div class='error'>" . htmlspecialchars($error) . "</div>"; ?>
        <?php if (isset($success)) echo "<div class='success'>" . htmlspecialchars($success) . "</div>"; ?>
        <div class="settings-section">
            <form method="POST" action="">
                <label>Theme:</label>
                <select name="theme" onchange="document.body.className = this.value; updatePreview(this.value)">
                    <option value="light" <?php echo $theme === 'light' ? 'selected' : ''; ?>>Light</option>
                    <option value="dark" <?php echo $theme === 'dark' ? 'selected' : ''; ?>>Dark</option>
                </select>

                <label>Notifications:</label>
                <input type="checkbox" name="notifications" <?php echo $notifications ? 'checked' : ''; ?>>

                <label>Profile:</label>
                <input type="text" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" placeholder="Name" required>
                <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" placeholder="Email" required>

                <div class="preview-card">This is a preview of your theme. Switch to see the change!</div>

                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <button type="submit">Save</button>
            </form>
        </div>
        <a href="dashboard.php">Back to Dashboard</a>
    </div>

    <script>
        function updatePreview(theme) {
            const preview = document.querySelector('.preview-card');
            if (theme === 'dark') {
                preview.style.backgroundColor = '#2f3b4c';
                preview.style.boxShadow = '0 4px 10px rgba(0, 0, 0, 0.2)';
                preview.style.color = '#e2e8f0';
            } else {
                preview.style.backgroundColor = '#edf2f7';
                preview.style.boxShadow = '0 4px 10px rgba(0, 0, 0, 0.05)';
                preview.style.color = '#2d3748';
            }
        }
        document.addEventListener('DOMContentLoaded', () => updatePreview('<?php echo $theme; ?>'));
    </script>
</body>
</html>