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
        if (isset($_POST['theme'])) {
            $theme = $_POST['theme'] === 'dark' ? 'dark' : 'light';
            setcookie('theme', $theme, time() + (365 * 24 * 60 * 60), "/");
            $stmt = $GLOBALS['pdo']->prepare("UPDATE users SET preferences = ? WHERE user_id = ?");
            $stmt->execute([json_encode(['theme' => $theme]), $_SESSION['user_id']]);
        }

        // Notifications update
        if (isset($_POST['update_notifications'])) {
            $notifications = isset($_POST['notifications']) ? 1 : 0;
            $stmt = $GLOBALS['pdo']->prepare("UPDATE users SET preferences = JSON_SET(COALESCE(preferences, '{}'), '$.notifications', ?) WHERE user_id = ?");
            $stmt->execute([$notifications, $_SESSION['user_id']]);
            $success = "Notification settings updated!";
        }

        // Profile update
        if (isset($_POST['update_profile']) && !empty($_POST['name']) && !empty($_POST['email'])) {
            $name = trim($_POST['name']);
            $email = trim($_POST['email']);
            
            // Check if email is already taken by another user
            $stmt = $GLOBALS['pdo']->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
            $stmt->execute([$email, $_SESSION['user_id']]);
            if ($stmt->fetch()) {
                $error = "Email is already in use by another account.";
            } else {
                $stmt = $GLOBALS['pdo']->prepare("UPDATE users SET name = ?, email = ? WHERE user_id = ?");
                $stmt->execute([$name, $email, $_SESSION['user_id']]);
                $_SESSION['name'] = $name;
                $success = "Profile updated successfully!";
            }
        }

        // Password update
        if (isset($_POST['update_password'])) {
            $current_password = $_POST['current_password'] ?? '';
            $new_password = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';
            
            if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
                $error = "All password fields are required.";
            } elseif ($new_password !== $confirm_password) {
                $error = "New passwords do not match.";
            } elseif (strlen($new_password) < 6) {
                $error = "Password must be at least 6 characters long.";
            } else {
                // Verify current password
                $stmt = $GLOBALS['pdo']->prepare("SELECT password_hash FROM users WHERE user_id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (password_verify($current_password, $user['password_hash'])) {
                    $hashed = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt = $GLOBALS['pdo']->prepare("UPDATE users SET password_hash= ? WHERE user_id = ?");
                    $stmt->execute([$hashed, $_SESSION['user_id']]);
                    $success = "Password updated successfully!";
                } else {
                    $error = "Current password is incorrect.";
                }
            }
        }
    }
}

$theme = isset($_COOKIE['theme']) ? $_COOKIE['theme'] : 'light';
$stmt = $GLOBALS['pdo']->prepare("SELECT name, email, JSON_EXTRACT(preferences, '$.notifications') as notifications FROM users WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
$notifications = $user['notifications'] ? 1 : 0;
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - TrackItPro</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --bg-primary: #f9f9f9;
            --bg-secondary: #fff;
            --bg-sidebar: #76b5c5;
            --bg-sidebar-hover: #5d9aa9;
            --bg-card: #f8f9fa;
            --text-primary: #333;
            --text-secondary: #555;
            --text-light: #fff;
            --text-muted: #e6f0fa;
            --border-color: #e0e0e0;
            --border-accent: #76b5c5;
            --shadow: rgba(0, 0, 0, 0.1);
            --input-bg: #fff;
            --preview-bg: #f0f8ff;
            --preview-border: #76b5c5;
        }

        [data-theme="dark"] {
            --bg-primary: #1a202c;
            --bg-secondary: #2d3748;
            --bg-sidebar: #2c5364;
            --bg-sidebar-hover: #1f3a47;
            --bg-card: #374151;
            --text-primary: #e2e8f0;
            --text-secondary: #cbd5e0;
            --text-light: #fff;
            --text-muted: #a0aec0;
            --border-color: #4a5568;
            --border-accent: #4299e1;
            --shadow: rgba(0, 0, 0, 0.3);
            --input-bg: #2d3748;
            --preview-bg: #2d3748;
            --preview-border: #4a5568;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--bg-primary);
            color: var(--text-primary);
            min-height: 100vh;
            overflow-x: hidden;
            transition: background-color 0.3s ease, color 0.3s ease;
        }

        .page-container {
            display: flex;
            width: 100%;
            min-height: 100vh;
            padding: 20px;
            animation: fadeIn 0.5s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        /* Sidebar Styles */
        .sidebar {
            width: 250px;
            background-color: var(--bg-sidebar);
            padding: 20px 0;
            box-shadow: 2px 0 5px var(--shadow);
            animation: slideInLeft 0.5s ease-out;
            transition: background-color 0.3s ease;
        }

        @keyframes slideInLeft {
            from { transform: translateX(-100%); }
            to { transform: translateX(0); }
        }

        .logo-section {
            padding: 10px 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
        }

        .logo {
            color: var(--text-light);
            font-size: 24px;
            font-weight: bold;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .logo i {
            font-size: 28px;
        }

        .user-profile {
            padding: 15px 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            text-align: center;
            animation: fadeIn 0.5s ease-out 0.2s both;
        }

        .user-avatar {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 8px;
            font-size: 20px;
            font-weight: bold;
            box-shadow: 0 4px 6px var(--shadow);
        }

        .user-info h4 {
            color: var(--text-light);
            font-size: 16px;
            margin-bottom: 4px;
        }

        .user-info p {
            color: var(--text-muted);
            font-size: 12px;
        }

        .nav-menu {
            padding: 10px 0;
        }

        .nav-item {
            margin: 5px 0;
        }

        .nav-link {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            color: var(--text-light);
            text-decoration: none;
            font-size: 14px;
            transition: all 0.3s ease;
            gap: 12px;
        }

        .nav-link i {
            font-size: 16px;
            width: 20px;
            text-align: center;
        }

        .nav-link:hover {
            background-color: var(--bg-sidebar-hover);
            animation: pulse 0.3s ease;
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }

        .nav-link.active {
            background-color: var(--bg-sidebar-hover);
            border-left: 4px solid #fff;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            padding: 20px;
            background-color: var(--bg-secondary);
            border-radius: 8px;
            box-shadow: 0 2px 5px var(--shadow);
            margin-left: 20px;
            animation: fadeInUp 0.5s ease-out;
            transition: background-color 0.3s ease;
        }

        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--border-color);
            transition: border-color 0.3s ease;
        }

        .page-title {
            font-size: 28px;
            font-weight: bold;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 12px;
            transition: color 0.3s ease;
        }

        .page-title i {
            color: var(--border-accent);
        }

        /* Theme Toggle Button */
        .theme-toggle {
            position: relative;
            width: 60px;
            height: 30px;
            background: var(--border-color);
            border-radius: 30px;
            cursor: pointer;
            transition: background 0.3s ease;
            border: 2px solid var(--border-accent);
        }

        .theme-toggle::before {
            content: '';
            position: absolute;
            top: 3px;
            left: 3px;
            width: 20px;
            height: 20px;
            background: var(--text-light);
            border-radius: 50%;
            transition: transform 0.3s ease;
            box-shadow: 0 2px 4px var(--shadow);
        }

        [data-theme="dark"] .theme-toggle::before {
            transform: translateX(30px);
        }

        .theme-toggle-icon {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            font-size: 14px;
            transition: opacity 0.3s ease;
        }

        .theme-toggle-icon.sun {
            left: 8px;
            color: #ffd700;
        }

        .theme-toggle-icon.moon {
            right: 8px;
            color: #f0e68c;
        }

        [data-theme="light"] .theme-toggle-icon.moon {
            opacity: 0.3;
        }

        [data-theme="dark"] .theme-toggle-icon.sun {
            opacity: 0.3;
        }

        /* Notifications */
        .notification {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideInRight 0.5s ease-out;
        }

        @keyframes slideInRight {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }

        .notification i {
            font-size: 20px;
        }

        .notification.error {
            background-color: #fee;
            color: #c33;
            border-left: 4px solid #c33;
        }

        .notification.success {
            background-color: #e8f5e9;
            color: #2e7d32;
            border-left: 4px solid #4caf50;
        }

        /* Settings Sections */
        .settings-grid {
            display: grid;
            gap: 20px;
        }

        .settings-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-left: 4px solid var(--border-accent);
            border-radius: 12px;
            padding: 25px;
            animation: fadeInUp 0.5s ease-out;
            transition: background-color 0.3s ease, border-color 0.3s ease;
        }

        .card-title {
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 20px;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 10px;
            transition: color 0.3s ease;
        }

        .card-title i {
            color: var(--border-accent);
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: var(--text-secondary);
            margin-bottom: 8px;
            transition: color 0.3s ease;
        }

        .form-input,
        .form-select {
            width: 100%;
            padding: 12px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 14px;
            background: var(--input-bg);
            color: var(--text-primary);
            transition: all 0.3s ease;
        }

        .form-input:focus,
        .form-select:focus {
            outline: none;
            border-color: var(--border-accent);
            box-shadow: 0 0 0 3px rgba(118, 181, 197, 0.1);
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px;
            background: var(--input-bg);
            border-radius: 8px;
            border: 1px solid var(--border-color);
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .checkbox-group:hover {
            border-color: var(--border-accent);
            background: var(--bg-card);
        }

        .checkbox-group input[type="checkbox"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
        }

        .btn-primary {
            padding: 12px 24px;
            background: linear-gradient(135deg, #76b5c5 0%, #5d9aa9 100%);
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(118, 181, 197, 0.3);
        }

        .theme-preview {
            margin-top: 15px;
            padding: 20px;
            background: var(--preview-bg);
            border-radius: 8px;
            text-align: center;
            border: 2px dashed var(--preview-border);
            transition: all 0.3s ease;
        }

        .preview-text {
            font-weight: 600;
            color: var(--text-secondary);
            transition: color 0.3s ease;
        }

        .theme-info {
            margin-top: 10px;
            padding: 10px;
            background: rgba(118, 181, 197, 0.1);
            border-radius: 6px;
            font-size: 13px;
            color: var(--text-secondary);
        }

        @media (max-width: 768px) {
            .page-container {
                flex-direction: column;
                padding: 10px;
            }
            .sidebar {
                width: 100%;
                margin-bottom: 20px;
            }
            .main-content {
                margin-left: 0;
                padding: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="page-container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="logo-section">
                <div class="logo">
                    <i class="fas fa-chart-line"></i>
                    TrackItPro
                </div>
            </div>
            <div class="user-profile">
                <div class="user-avatar">
                    <?php echo strtoupper(substr($_SESSION['name'], 0, 1)); ?>
                </div>
                <div class="user-info">
                    <h4><?php echo htmlspecialchars($_SESSION['name']); ?></h4>
                    <p>Habit Tracker</p>
                </div>
            </div>
            <div class="nav-menu">
                <div class="nav-item">
                    <a href="dashboard.php" class="nav-link">
                        <i class="fas fa-home"></i>
                        Dashboard
                    </a>
                </div>
                <div class="nav-item">
                    <a href="habits.php" class="nav-link">
                        <i class="fas fa-bolt"></i>
                        My Habits
                    </a>
                </div>
                <div class="nav-item">
                    <a href="progress.php" class="nav-link">
                        <i class="fas fa-chart-bar"></i>
                        Progress
                    </a>
                </div>
                <div class="nav-item">
                    <a href="rewards.php" class="nav-link">
                        <i class="fas fa-bullseye"></i>
                        Goals
                    </a>
                </div>
                <div class="nav-item">
                    <a href="achievements.php" class="nav-link">
                        <i class="fas fa-trophy"></i>
                        Achievements
                    </a>
                </div>
                <div class="nav-item">
                    <a href="settings.php" class="nav-link active">
                        <i class="fas fa-cog"></i>
                        Settings
                    </a>
                </div>
                <div class="nav-item">
                    <a href="logout.php" class="nav-link">
                        <i class="fas fa-sign-out-alt"></i>
                        Logout
                    </a>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <div class="page-header">
                <h1 class="page-title">
                    <i class="fas fa-cog"></i>
                    Settings & Preferences
                </h1>
                <!-- Real-time Theme Toggle -->
                <div class="theme-toggle" id="themeToggle" title="Toggle Dark Mode">
                    <i class="fas fa-sun theme-toggle-icon sun"></i>
                    <i class="fas fa-moon theme-toggle-icon moon"></i>
                </div>
            </div>

            <!-- Notifications -->
            <?php if ($error): ?>
                <div class="notification error">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="notification success">
                    <i class="fas fa-check-circle"></i>
                    <span><?php echo htmlspecialchars($success); ?></span>
                </div>
            <?php endif; ?>

            <div class="settings-grid">
                <!-- Profile Settings -->
                <div class="settings-card">
                    <h2 class="card-title">
                        <i class="fas fa-user"></i>
                        Profile Information
                    </h2>
                    <form method="POST" action="">
                        <div class="form-group">
                            <label class="form-label">Full Name</label>
                            <input 
                                type="text" 
                                name="name" 
                                class="form-input" 
                                value="<?php echo htmlspecialchars($user['name']); ?>" 
                                required
                            >
                        </div>
                        <div class="form-group">
                            <label class="form-label">Email Address</label>
                            <input 
                                type="email" 
                                name="email" 
                                class="form-input" 
                                value="<?php echo htmlspecialchars($user['email']); ?>" 
                                required
                            >
                        </div>
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <button type="submit" name="update_profile" class="btn-primary">
                            <i class="fas fa-save"></i>
                            Save Profile
                        </button>
                    </form>
                </div>

                <!-- Password Settings -->
                <div class="settings-card">
                    <h2 class="card-title">
                        <i class="fas fa-lock"></i>
                        Change Password
                    </h2>
                    <form method="POST" action="">
                        <div class="form-group">
                            <label class="form-label">Current Password</label>
                            <input 
                                type="password" 
                                name="current_password" 
                                class="form-input" 
                                placeholder="Enter current password"
                            >
                        </div>
                        <div class="form-group">
                            <label class="form-label">New Password</label>
                            <input 
                                type="password" 
                                name="new_password" 
                                class="form-input" 
                                placeholder="Enter new password (min 6 characters)"
                            >
                        </div>
                        <div class="form-group">
                            <label class="form-label">Confirm New Password</label>
                            <input 
                                type="password" 
                                name="confirm_password" 
                                class="form-input" 
                                placeholder="Confirm new password"
                            >
                        </div>
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <button type="submit" name="update_password" class="btn-primary">
                            <i class="fas fa-key"></i>
                            Update Password
                        </button>
                    </form>
                </div>

                <!-- Notification Settings -->
                <div class="settings-card">
                    <h2 class="card-title">
                        <i class="fas fa-bell"></i>
                        Notifications
                    </h2>
                    <form method="POST" action="">
                        <div class="form-group">
                            <label class="checkbox-group">
                                <input 
                                    type="checkbox" 
                                    name="notifications" 
                                    <?php echo $notifications ? 'checked' : ''; ?>
                                >
                                <span>Enable email notifications for habit reminders</span>
                            </label>
                        </div>
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <button type="submit" name="update_notifications" class="btn-primary">
                            <i class="fas fa-save"></i>
                            Save Preferences
                        </button>
                    </form>
                </div>

                <!-- Theme Settings -->
                <div class="settings-card">
                    <h2 class="card-title">
                        <i class="fas fa-palette"></i>
                        Appearance
                    </h2>
                    <form method="POST" action="">
                        <div class="form-group">
                            <label class="form-label">Theme Mode</label>
                            <select name="theme" class="form-select" id="themeSelect">
                                <option value="light" <?php echo $theme === 'light' ? 'selected' : ''; ?>>
                                    Light Mode
                                </option>
                                <option value="dark" <?php echo $theme === 'dark' ? 'selected' : ''; ?>>
                                    Dark Mode
                                </option>
                            </select>
                        </div>
                        <div class="theme-preview" id="themePreview">
                            <p class="preview-text">
                                <i class="fas fa-eye"></i>
                                Current Theme: <span id="currentThemeText">Light Mode</span>
                            </p>
                            <div class="theme-info">
                                💡 Use the toggle button in the header for instant preview, then click "Save Theme" to persist your choice across sessions.
                            </div>
                        </div>
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <button type="submit" class="btn-primary" style="margin-top: 15px;">
                            <i class="fas fa-save"></i>
                            Save Theme to Account
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Theme Management System
        (function() {
            // Get theme from cookie or default to light
            function getCookie(name) {
                const value = `; ${document.cookie}`;
                const parts = value.split(`; ${name}=`);
                if (parts.length === 2) return parts.pop().split(';').shift();
                return null;
            }

            // Initialize theme from cookie
            const savedTheme = getCookie('theme') || 'light';
            document.documentElement.setAttribute('data-theme', savedTheme);
            
            // Update UI elements to match current theme
            function updateThemeUI(theme) {
                const themeSelect = document.getElementById('themeSelect');
                const currentThemeText = document.getElementById('currentThemeText');
                
                if (themeSelect) {
                    themeSelect.value = theme;
                }
                
                if (currentThemeText) {
                    currentThemeText.textContent = theme === 'dark' ? 'Dark Mode' : 'Light Mode';
                }
            }

            // Set theme and update cookie
            function setTheme(theme) {
                document.documentElement.setAttribute('data-theme', theme);
                // Set cookie for 1 year
                document.cookie = `theme=${theme}; path=/; max-age=${365 * 24 * 60 * 60}`;
                updateThemeUI(theme);
            }

            // Toggle theme
            function toggleTheme() {
                const currentTheme = document.documentElement.getAttribute('data-theme');
                const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
                setTheme(newTheme);
            }

            // Initialize UI on page load
            updateThemeUI(savedTheme);

            // Add event listener to toggle button
            const themeToggle = document.getElementById('themeToggle');
            if (themeToggle) {
                themeToggle.addEventListener('click', toggleTheme);
            }

            // Sync dropdown changes with theme
            const themeSelect = document.getElementById('themeSelect');
            if (themeSelect) {
                themeSelect.addEventListener('change', function() {
                    setTheme(this.value);
                });
            }

            // Add keyboard accessibility
            if (themeToggle) {
                themeToggle.setAttribute('tabindex', '0');
                themeToggle.setAttribute('role', 'button');
                themeToggle.setAttribute('aria-label', 'Toggle dark mode');
                
                themeToggle.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        toggleTheme();
                    }
                });
            }
        })();
    </script>
</body>
</html>