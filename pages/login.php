<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config.php';

// Generate CSRF token if not set
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = '';
$success = '';
$action = 'login'; 

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
   
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Invalid CSRF token.";
    } else {
        $form_action = isset($_POST['action']) ? $_POST['action'] : '';

        if ($form_action === 'register') {
            $name = trim($_POST['name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';

            
            if (empty($name) || empty($email) || empty($password)) {
                $error = "All fields are required.";
                $action = 'register';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = "Invalid email format.";
                $action = 'register';
            } elseif (strlen($password) < 6) {
                $error = "Password must be at least 6 characters.";
                $action = 'register';
            } else {
               
                global $pdo;
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
                $stmt->execute([$email]);
                if ($stmt->fetchColumn() > 0) {
                    $error = "Email already registered.";
                    $action = 'register';
                } else {
                    if (register($name, $email, $password)) {
                        $success = "Registration successful! Please log in.";
                        $action = 'login';
                    } else {
                        $error = "Registration failed.";
                        $action = 'register';
                    }
                }
            }
        } elseif ($form_action === 'login') {
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';

            if (empty($email) || empty($password)) {
                $error = "All fields are required.";
                $action = 'login';
            } elseif (login($email, $password)) {
                header("Location: " . APP_URL . "/pages/dashboard.php");
                exit;
            } else {
                $error = "Invalid credentials.";
                $action = 'login';
            }
        }
        
    }

  
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TrackItPro</title>
    <style>
       
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: #e5e7eb;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            overflow: hidden;
        }

        .auth-container {
            position: relative;
            width: 100%;
            max-width: 920px;
            height: 640px;
            border-radius: 20px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            overflow: hidden;
            background: white;
        }

        /* Sliding Panel System */
        .sliding-panels {
            position: relative;
            width: 200%;
            height: 100%;
            display: flex;
            transition: transform 0.8s cubic-bezier(0.25, 0.46, 0.45, 0.94);
        }

        .auth-container.show-register .sliding-panels {
            transform: translateX(-50%);
        }

        .panel {
            width: 50%;
            height: 100%;
            display: flex;
            position: relative;
        }

        /* Form Sections */
        .form-section {
            flex: 0 0 45%;
            padding: 35px 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            background: white;
            position: relative;
            overflow: hidden;
        }

        /* Content Animation Containers */
        .form-content {
            opacity: 1;
            transform: translateX(0);
            transition: all 0.6s cubic-bezier(0.25, 0.46, 0.45, 0.94);
        }

        .auth-container.transitioning .panel:first-child .form-content {
            opacity: 0;
            transform: translateX(-30px);
        }

        .auth-container.transitioning .panel:last-child .form-content {
            opacity: 0;
            transform: translateX(30px);
        }

        /* Staggered Animation for Form Elements */
        .form-element {
            opacity: 1;
            transform: translateY(0);
            transition: all 0.4s ease;
        }

        .auth-container.transitioning .form-element {
            opacity: 0;
            transform: translateY(20px);
        }

        .form-element:nth-child(1) { transition-delay: 0s; }
        .form-element:nth-child(2) { transition-delay: 0.05s; }
        .form-element:nth-child(3) { transition-delay: 0.1s; }
        .form-element:nth-child(4) { transition-delay: 0.15s; }
        .form-element:nth-child(5) { transition-delay: 0.2s; }
        .form-element:nth-child(6) { transition-delay: 0.25s; }
        .form-element:nth-child(7) { transition-delay: 0.3s; }
        .form-element:nth-child(8) { transition-delay: 0.35s; }

        /* Reverse animation timing for incoming content */
        .auth-container.show-register .panel:last-child .form-element:nth-child(1) { transition-delay: 0.4s; }
        .auth-container.show-register .panel:last-child .form-element:nth-child(2) { transition-delay: 0.45s; }
        .auth-container.show-register .panel:last-child .form-element:nth-child(3) { transition-delay: 0.5s; }
        .auth-container.show-register .panel:last-child .form-element:nth-child(4) { transition-delay: 0.55s; }
        .auth-container.show-register .panel:last-child .form-element:nth-child(5) { transition-delay: 0.6s; }
        .auth-container.show-register .panel:last-child .form-element:nth-child(6) { transition-delay: 0.65s; }
        .auth-container.show-register .panel:last-child .form-element:nth-child(7) { transition-delay: 0.7s; }
        .auth-container.show-register .panel:last-child .form-element:nth-child(8) { transition-delay: 0.75s; }

        /* Logo and Branding */
        .logo-section {
            display: flex;
            align-items: center;
            margin-bottom: 25px;
        }

        .logo-circle {
            width: 26px;
            height: 26px;
            background: #2d5a5a;
            border-radius: 50%;
            margin-right: 8px;
            position: relative;
            transition: all 0.3s ease;
        }

        .logo-circle::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 10px;
            height: 10px;
            background: white;
            border-radius: 2px;
            transition: all 0.3s ease;
        }

        .logo-text {
            font-size: 17px;
            font-weight: 700;
            color: #1f2937;
            letter-spacing: -0.5px;
        }

        .form-title {
            font-size: 22px;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 8px;
            letter-spacing: -0.5px;
        }

        .form-subtitle {
            font-size: 14px;
            color: #6b7280;
            margin-bottom: 24px;
            line-height: 1.5;
        }

        /* Form Styling */
        .form-group {
            margin-bottom: 18px;
        }

        .form-label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #374151;
            font-size: 13px;
        }

        .form-input {
            width: 100%;
            padding: 12px 14px;
            border: 1.5px solid #e5e7eb;
            border-radius: 7px;
            font-size: 14px;
            background: #f9fafb;
            transition: all 0.3s ease;
            color: #1f2937;
        }

        .form-input:focus {
            outline: none;
            border-color: #2d5a5a;
            background: white;
            box-shadow: 0 0 0 3px rgba(45, 90, 90, 0.1);
            transform: scale(1.02);
        }

        .form-input::placeholder {
            color: #9ca3af;
            font-size: 14px;
        }

        /* Interactive Elements */
        .form-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 20px 0 24px 0;
        }

        .checkbox-container {
            display: flex;
            align-items: flex-start;
            gap: 8px;
            margin: 18px 0 20px 0;
        }

        .checkbox-input, .remember-checkbox {
            width: 16px;
            height: 16px;
            margin-top: 1px;
            accent-color: #2d5a5a;
        }

        .checkbox-label, .remember-label {
            font-size: 12px;
            color: #6b7280;
            line-height: 1.4;
            margin: 0;
        }

        .remember-me {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .forgot-password {
            color: #2d5a5a;
            text-decoration: none;
            font-size: 12px;
            font-weight: 600;
            transition: all 0.2s ease;
        }

        .forgot-password:hover {
            text-decoration: underline;
            transform: scale(1.05);
        }

        /* Buttons */
        .submit-button {
            width: 100%;
            padding: 12px;
            background: #2d5a5a;
            color: white;
            border: none;
            border-radius: 7px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.25, 0.46, 0.45, 0.94);
            margin-bottom: 20px;
            position: relative;
            overflow: hidden;
        }

        .submit-button::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }

        .submit-button:hover {
            background: #1e3a3a;
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(45, 90, 90, 0.3);
        }

        .submit-button:hover::before {
            left: 100%;
        }

        .submit-button:active {
            transform: translateY(0);
        }

        /* Social Buttons */
        .social-buttons {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }

        .social-button {
            flex: 1;
            padding: 9px;
            border: 1.5px solid #e5e7eb;
            background: white;
            border-radius: 7px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            font-size: 12px;
            font-weight: 500;
            color: #374151;
            position: relative;
            overflow: hidden;
        }

        .social-button::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 0;
            height: 100%;
            background: rgba(45, 90, 90, 0.05);
            transition: width 0.3s ease;
        }

        .social-button:hover {
            border-color: #2d5a5a;
            transform: translateY(-1px);
        }

        .social-button:hover::before {
            width: 100%;
        }

        /* Divider */
        .divider-section {
            text-align: center;
            margin: 18px 0;
            position: relative;
        }

        .divider-line {
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 1px;
            background: #e5e7eb;
            transform: scaleX(1);
            transition: transform 0.5s ease;
        }

        .divider-text {
            background: white;
            padding: 0 16px;
            color: #9ca3af;
            font-size: 11px;
            position: relative;
            transition: all 0.3s ease;
        }

        /* Page Switch Links */
        .page-switch {
            text-align: center;
            font-size: 12px;
            color: #6b7280;
        }

        .switch-link {
            color: #2d5a5a;
            text-decoration: none;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            position: relative;
        }

        .switch-link:hover {
            text-decoration: underline;
            transform: scale(1.05);
        }

        /* Hero Section */
        .hero-section {
            flex: 0 0 55%;
            background: linear-gradient(135deg, #2d5a5a 0%, #1e3a3a 100%);
            padding: 40px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }

        /* Animated Background Elements */
        .hero-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-image: 
                radial-gradient(circle at 20% 30%, rgba(255,255,255,0.05) 1px, transparent 1px),
                radial-gradient(circle at 80% 70%, rgba(255,255,255,0.04) 2px, transparent 2px),
                radial-gradient(circle at 60% 20%, rgba(255,255,255,0.03) 1.5px, transparent 1.5px);
            background-size: 50px 50px, 80px 80px, 100px 100px;
            animation: backgroundFloat 20s ease-in-out infinite;
        }

        @keyframes backgroundFloat {
            0%, 100% { transform: translate(0, 0); }
            33% { transform: translate(-10px, -5px); }
            66% { transform: translate(5px, -10px); }
        }

        .hero-section::after {
            content: '';
            position: absolute;
            top: 10%;
            right: 15%;
            width: 40px;
            height: 40px;
            background: rgba(255,255,255,0.06);
            border-radius: 8px;
            transform: rotate(45deg);
            animation: floatingShape1 6s ease-in-out infinite;
        }

        @keyframes floatingShape1 {
            0%, 100% { transform: rotate(45deg) translate(0, 0); }
            50% { transform: rotate(45deg) translate(-10px, 10px); }
        }

        .floating-shape-1 {
            position: absolute;
            top: 60%;
            left: 10%;
            width: 24px;
            height: 24px;
            background: rgba(255,255,255,0.04);
            border-radius: 4px;
            transform: rotate(30deg);
            animation: floatingShape2 8s ease-in-out infinite;
        }

        @keyframes floatingShape2 {
            0%, 100% { transform: rotate(30deg) translate(0, 0); }
            50% { transform: rotate(30deg) translate(15px, -5px); }
        }

        .floating-shape-2 {
            position: absolute;
            bottom: 15%;
            right: 25%;
            width: 16px;
            height: 16px;
            background: rgba(255,255,255,0.05);
            border-radius: 50%;
            animation: floatingShape3 10s ease-in-out infinite;
        }

        @keyframes floatingShape3 {
            0%, 100% { transform: translate(0, 0); }
            50% { transform: translate(-8px, 12px); }
        }

        /* Analytics Dashboard */
        .analytics-dashboard {
            background: rgba(255, 255, 255, 0.98);
            border-radius: 14px;
            padding: 20px;
            margin-bottom: 28px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
            position: relative;
            z-index: 2;
            width: 100%;
            max-width: 280px;
            transition: all 0.3s ease;
            animation: dashboardFloat 4s ease-in-out infinite;
        }

        @keyframes dashboardFloat {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-5px); }
        }

        .analytics-dashboard:hover {
            transform: translateY(-10px);
            box-shadow: 0 30px 60px rgba(0,0,0,0.2);
        }

        .analytics-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
        }

        .analytics-title {
            font-size: 15px;
            font-weight: 600;
            color: #1f2937;
        }

        .time-tabs {
            display: flex;
            gap: 12px;
            font-size: 11px;
            color: #9ca3af;
        }

        .time-tab {
            cursor: pointer;
            transition: all 0.2s ease;
            padding: 2px 4px;
            border-radius: 4px;
        }

        .time-tab.active {
            color: #2d5a5a;
            font-weight: 600;
            background: rgba(45, 90, 90, 0.1);
        }

        .time-tab:not(.active):hover {
            color: #2d5a5a;
            background: rgba(45, 90, 90, 0.05);
        }

        /* Charts Container */
        .charts-container {
            display: flex;
            gap: 14px;
            margin-bottom: 16px;
        }

        .line-chart {
            flex: 1;
            height: 70px;
            background: #f8fafc;
            border-radius: 7px;
            position: relative;
            overflow: hidden;
        }

        .line-chart svg {
            width: 100%;
            height: 100%;
        }

        .chart-line {
            fill: none;
            stroke: #2d5a5a;
            stroke-width: 2;
            stroke-linecap: round;
            stroke-dasharray: 200;
            stroke-dashoffset: 200;
            animation: drawLine 2s ease-in-out forwards, pulseLine 3s ease-in-out infinite 2s;
        }

        @keyframes drawLine {
            to {
                stroke-dashoffset: 0;
            }
        }

        @keyframes pulseLine {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }

        .chart-area {
            fill: url(#gradient);
            opacity: 0;
            animation: fillArea 1s ease-in-out forwards 1s;
        }

        @keyframes fillArea {
            to {
                opacity: 0.1;
            }
        }

        /* Circular Progress Chart */
        .circular-chart {
            flex: 0 0 70px;
            height: 70px;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .circular-progress {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: conic-gradient(#2d5a5a 0deg 0deg, #e5e7eb 0deg 360deg);
            display: flex;
            align-items: center;
            justify-content: center;
            animation: progressFill 2s ease-in-out forwards 0.5s;
        }

        @keyframes progressFill {
            to {
                background: conic-gradient(#2d5a5a 0deg 223deg, #e5e7eb 223deg 360deg);
            }
        }

        .circular-progress::before {
            content: '';
            width: 42px;
            height: 42px;
            background: white;
            border-radius: 50%;
            position: absolute;
        }

        .progress-text {
            position: relative;
            z-index: 1;
            font-size: 16px;
            font-weight: 700;
            color: #2d5a5a;
            opacity: 0;
            animation: fadeInScale 0.5s ease-in-out forwards 2s;
        }

        @keyframes fadeInScale {
            to {
                opacity: 1;
                transform: scale(1.1);
            }
        }

        .metrics-display {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .metric-value {
            font-size: 24px;
            font-weight: 700;
            color: #2d5a5a;
            line-height: 1;
            animation: countUp 1s ease-out forwards 2.5s;
        }

        .metric-change {
            font-size: 13px;
            color: #059669;
            font-weight: 600;
            opacity: 0;
            animation: slideInRight 0.5s ease-out forwards 3s;
        }

        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(-10px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        /* Hero Content */
        .hero-content {
            text-align: center;
            color: white;
            position: relative;
            z-index: 2;
        }

        .hero-title {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 12px;
            line-height: 1.3;
            letter-spacing: -0.5px;
            opacity: 0;
            animation: fadeInUp 0.8s ease-out forwards 3.5s;
        }

        .hero-description {
            font-size: 14px;
            line-height: 1.6;
            opacity: 0;
            
            text-align: center;
            animation: fadeInUp 0.8s ease-out forwards 4s;
        }

        @keyframes fadeInUp {
            to {
                opacity: 0.9;
                transform: translateY(0);
            }
            from {
                opacity: 0;
                transform: translateY(20px);
            }
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            body {
                padding: 10px;
            }
            
            .auth-container {
                height: auto;
                min-height: 500px;
            }
            
            .sliding-panels {
                width: 100%;
                flex-direction: column;
            }
            
            .panel {
                width: 100%;
            }
            
            .hero-section {
                order: -1;
                min-height: 250px;
                padding: 25px;
            }
            
            .form-section {
                padding: 25px 20px;
            }
        }

        /* Loading Animation */
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }

        .loading {
            animation: pulse 1s ease-in-out infinite;
        }
    
    </style>
</head>
<body>
    
    <div class="auth-container" id="authContainer" <?php echo $action === 'register' ? 'class="auth-container show-register"' : ''; ?>>
        <div class="sliding-panels">
            <!-- Login Panel -->
            <div class="panel">
                <div class="form-section">
                    <div class="form-content">
                        <div class="logo-section form-element">
                            <div class="logo-circle"></div>
                            <div class="logo-text">TrackItPro</div>
                        </div>
                        
                        <div class="form-element">
                            <h1 class="form-title">Welcome back</h1>
                            <p class="form-subtitle">Sign in to your account to continue</p>
                        </div>
                        
                        <?php if ($action === 'login' && $error): ?>
                            <p class="error" style="color: red;"><?php echo htmlspecialchars($error); ?></p>
                        <?php endif; ?>
                        <?php if ($action === 'login' && $success): ?>
                            <p class="success" style="color: green;"><?php echo htmlspecialchars($success); ?></p>
                        <?php endif; ?>
                        
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="login">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                            <div class="form-group form-element">
                                <label for="login-email" class="form-label">Email</label>
                                <input type="email" id="login-email" name="email" placeholder="Enter your email" class="form-input" required>
                            </div>
                            
                            <div class="form-group form-element">
                                <label for="login-password" class="form-label">Password</label>
                                <input type="password" id="login-password" name="password" placeholder="Enter your password" class="form-input" required>
                            </div>
                            
                            <div class="form-options form-element">
                                <div class="remember-me">
                                    <input type="checkbox" id="remember" name="remember" class="remember-checkbox">
                                    <label for="remember" class="remember-label">Remember me</label>
                                </div>
                                <a href="forgot_password.php" class="forgot-password">Forgot password?</a>
                            </div>
                            
                            <button type="submit" class="submit-button form-element">Sign in</button>
                        </form>
                        
                        <div class="divider-section form-element">
                            <div class="divider-line"></div>
                            <span class="divider-text">OR</span>
                        </div>
                        
                    <div class="social-buttons form-element">
                            <button class="social-button">
                                <svg width="16" height="16" viewBox="0 0 24 24">
                                    <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
                                    <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
                                    <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/>
                                    <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
                                </svg>
                                Google
                            </button>
                            <button class="social-button">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="#1877F2">
                                    <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                                </svg>
                                Facebook
                            </button>
                        </div>
                        
                        <div class="page-switch form-element">
                            Don't have an account? <span class="switch-link" onclick="switchToRegister()">Sign up</span>
                        </div>
                    </div>
                </div>
                
                 <div class="hero-section">
                    <div class="floating-shape-1"></div>
                    <div class="floating-shape-2"></div>
                    
                    <div class="analytics-dashboard">
                        <div class="analytics-header">
                            <div class="analytics-title">Analytics</div>
                            <div class="time-tabs">
                                <div class="time-tab">Weekly</div>
                                <div class="time-tab">Monthly</div>
                                <div class="time-tab active">Yearly</div>
                            </div>
                        </div>
                        
                        <div class="charts-container">
                            <div class="line-chart">
                                <svg viewBox="0 0 120 60">
                                    <defs>
                                        <linearGradient id="gradient" x1="0%" y1="0%" x2="0%" y2="100%">
                                            <stop offset="0%" style="stop-color:#2d5a5a;stop-opacity:0.3" />
                                            <stop offset="100%" style="stop-color:#2d5a5a;stop-opacity:0" />
                                        </linearGradient>
                                    </defs>
                                    <path d="M10,45 Q20,35 30,40 T50,30 T70,35 T90,25 T110,30" class="chart-line"/>
                                    <path d="M10,45 Q20,35 30,40 T50,30 T70,35 T90,25 T110,30 L110,50 L10,50 Z" class="chart-area"/>
                                </svg>
                            </div>
                            
                            <div class="circular-chart">
                                <div class="circular-progress">
                                    <div class="progress-text">62%</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="metrics-display">
                            <div>
                                <div class="metric-value">62%</div>
                                <div class="metric-change">+2.1%</div>
                            </div>
                        </div>
                    </div>
                    
                <div class="hero-content">
                    <h2 class="hero-title">Build Lasting Habits with Gamified Motivation</h2>
                    <p class="hero-description">Welcome back to TrackItPro! Track your daily progress, earn badges for streaks, and turn routines into rewarding achievements.</p>
                </div>
                </div>
            </div>
            
            <!-- Register Panel -->
            <div class="panel">
                <div class="form-section">
                    <div class="form-content">
                        <div class="logo-section form-element">
                            <div class="logo-circle"></div>
                            <div class="logo-text">TrackItPro</div>
                        </div>
                        
                        <div class="form-element">
                            <h1 class="form-title">Create an account</h1>
                            <p class="form-subtitle">Join TrackItPro and start managing your habits</p>
                        </div>
                        
                        <?php if ($action === 'register' && $error): ?>
                            <p class="error" style="color: red;"><?php echo htmlspecialchars($error); ?></p>
                        <?php endif; ?>
                        
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="register">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                            <div class="form-group form-element">
                                <label for="register-name" class="form-label">Name</label>
                                <input type="text" id="register-name" name="name" placeholder="Enter your name" class="form-input" required>
                            </div>
                            
                            <div class="form-group form-element">
                                <label for="register-email" class="form-label">Email</label>
                                <input type="email" id="register-email" name="email" placeholder="Enter your email" class="form-input" required>
                            </div>
                            
                            <div class="form-group form-element">
                                <label for="register-password" class="form-label">Password</label>
                                <input type="password" id="register-password" name="password" placeholder="Enter your password" class="form-input" required>
                            </div>
                            
                            <div class="checkbox-container form-element">
                                <input type="checkbox" id="terms" name="terms" class="checkbox-input" required>
                                <label for="terms" class="checkbox-label">I agree to all the Terms & Conditions</label>
                            </div>
                            
                            <button type="submit" class="submit-button form-element">Sign up</button>
                        </form>
                        
                        <div class="divider-section form-element">
                            <div class="divider-line"></div>
                            <span class="divider-text">OR</span>
                        </div>
                        
                        
                        
                        <div class="page-switch form-element">
                            Already have an account? <span class="switch-link" onclick="switchToLogin()">Log in</span>
                        </div>
                    </div>
                </div>
                
                <div class="hero-section">
                    <div class="floating-shape-1"></div>
                    <div class="floating-shape-2"></div>
                    
                    <div class="analytics-dashboard">
                        <div class="analytics-header">
                            <div class="analytics-title">Analytics</div>
                            <div class="time-tabs">
                                <div class="time-tab">Weekly</div>
                                <div class="time-tab">Monthly</div>
                                <div class="time-tab active">Yearly</div>
                            </div>
                        </div>
                        
                        <div class="charts-container">
                            <div class="line-chart">
                                <svg viewBox="0 0 120 60">
                                    <defs>
                                        <linearGradient id="gradient2" x1="0%" y1="0%" x2="0%" y2="100%">
                                            <stop offset="0%" style="stop-color:#2d5a5a;stop-opacity:0.3" />
                                            <stop offset="100%" style="stop-color:#2d5a5a;stop-opacity:0" />
                                        </linearGradient>
                                    </defs>
                                    <path d="M10,45 Q20,35 30,40 T50,30 T70,35 T90,25 T110,30" class="chart-line"/>
                                    <path d="M10,45 Q20,35 30,40 T50,30 T70,35 T90,25 T110,30 L110,50 L10,50 Z" class="chart-area" fill="url(#gradient2)"/>
                                </svg>
                            </div>
                            
                            <div class="circular-chart">
                                <div class="circular-progress">
                                    <div class="progress-text">62%</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="metrics-display">
                            <div>
                                <div class="metric-value">62%</div>
                                <div class="metric-change">+2.1%</div>
                            </div>
                        </div>
                    </div>
                    
                <div class="hero-content">
                    <h2 class="hero-title">Build Lasting Habits with Gamified Motivation</h2>
                    <p class="hero-description">Welcome back to TrackItPro! Track your daily progress, earn badges for streaks, and turn routines into rewarding achievements.</p>
                </div>
                </div>
            </div>
        </div>
    </div>
  

    <script>
       let isTransitioning = false;

        function switchToRegister() {
            if (isTransitioning) return;
            
            isTransitioning = true;
            const container = document.getElementById('authContainer');
            
        
            container.classList.add('transitioning');
            
        
            setTimeout(() => {
                container.classList.add('show-register');
            }, 400);
            
       
            setTimeout(() => {
                container.classList.remove('transitioning');
                isTransitioning = false;
             
                restartAnimations();
            }, 1200);
        }

        function switchToLogin() {
            if (isTransitioning) return;
            
            isTransitioning = true;
            const container = document.getElementById('authContainer');
            
        
            container.classList.add('transitioning');
            
      
            setTimeout(() => {
                container.classList.remove('show-register');
            }, 400);
 
            setTimeout(() => {
                container.classList.remove('transitioning');
                isTransitioning = false;
                
            
                restartAnimations();
            }, 1200);
        }

        function restartAnimations() {
            
            const animatedElements = document.querySelectorAll('.chart-line, .chart-area, .circular-progress, .progress-text, .metric-value, .metric-change, .hero-title, .hero-description');
            
          
            animatedElements.forEach(element => {
                const parent = element.parentNode;
                const newElement = element.cloneNode(true);
                parent.replaceChild(newElement, element);
            });
        }

      
        document.querySelectorAll('.time-tab').forEach(tab => {
            tab.addEventListener('click', function() {
                
                const container = this.closest('.time-tabs');
                container.querySelectorAll('.time-tab').forEach(t => t.classList.remove('active'));
                
             
                this.classList.add('active');
                
             
                restartAnimations();
            });
        });

       
        document.querySelectorAll('.form-input').forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.style.transform = 'scale(1.02)';
                this.parentElement.style.transition = 'transform 0.2s ease';
            });
            
            input.addEventListener('blur', function() {
                this.parentElement.style.transform = 'scale(1)';
            });
        });

  
        document.querySelectorAll('.submit-button, .social-button').forEach(button => {
            button.addEventListener('click', function(e) {
              
                const ripple = document.createElement('span');
                const rect = this.getBoundingClientRect();
                const size = Math.max(rect.width, rect.height);
                const x = e.clientX - rect.left - size / 2;
                const y = e.clientY - rect.top - size / 2;
                
                ripple.style.cssText = `
                    position: absolute;
                    width: ${size}px;
                    height: ${size}px;
                    left: ${x}px;
                    top: ${y}px;
                    background: rgba(255, 255, 255, 0.3);
                    border-radius: 50%;
                    transform: scale(0);
                    animation: ripple 0.6s linear;
                    pointer-events: none;
                `;
                
                this.style.position = 'relative';
                this.style.overflow = 'hidden';
                this.appendChild(ripple);
                
                setTimeout(() => {
                    ripple.remove();
                }, 600);
            });
        });

      
        const style = document.createElement('style');
        style.textContent = `
            @keyframes ripple {
                to {
                    transform: scale(4);
                    opacity: 0;
                }
            }
        `;
        document.head.appendChild(style);

       
        window.addEventListener('load', function() {
            const container = document.getElementById('authContainer');
            container.style.opacity = '0';
            container.style.transform = 'scale(0.95)';
            
            setTimeout(() => {
                container.style.transition = 'all 0.8s cubic-bezier(0.25, 0.46, 0.45, 0.94)';
                container.style.opacity = '1';
                container.style.transform = 'scale(1)';
            }, 100);
        });

       
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Tab' && !isTransitioning) {
               
                setTimeout(() => {
                    const focusedElement = document.activeElement;
                    if (focusedElement && focusedElement.classList.contains('form-input')) {
                        focusedElement.style.transform = 'scale(1.02)';
                    }
                }, 10);
            }
        });


        if (window.innerWidth <= 768) {
            document.body.style.scrollBehavior = 'smooth';
        }
        
    </script>
</body>
</html>