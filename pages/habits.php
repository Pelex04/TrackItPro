<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/habit.php';
requireLogin();

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errors[] = "Invalid CSRF token.";
    } else {
        if (isset($_POST['add'])) {
            $name = trim($_POST['name']);
            $frequency = $_POST['frequency'];
            $goal = (int)$_POST['goal'];
            if (empty($name) || $goal < 1) {
                $errors[] = "Invalid input. Please check your entries.";
            } else {
                if (addHabit($_SESSION['user_id'], $name, $frequency, $goal)) {
                    $success = "Habit added successfully!";
                    $_SESSION['success_message'] = $success;
                    header("Location: habits.php");
                    exit;
                } else {
                    $errors[] = "Failed to add habit. Please try again.";
                }
            }
        } elseif (isset($_POST['mark_complete'])) {
            if (markHabitComplete($_POST['habit_id'], date('Y-m-d'))) {
                $success = "Habit marked as complete!";
                $_SESSION['success_message'] = $success;
            } else {
                $errors[] = "Habit already completed today or error occurred.";
            }
            header("Location: habits.php");
            exit;
        } elseif (isset($_POST['delete'])) {
            if (deleteHabit($_POST['habit_id'])) {
                $success = "Habit deleted successfully!";
                $_SESSION['success_message'] = $success;
            } else {
                $errors[] = "Failed to delete habit.";
            }
            header("Location: habits.php");
            exit;
        }
    }
}

// Check for success message from redirect
if (isset($_SESSION['success_message'])) {
    $success = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

$habits = getHabits($_SESSION['user_id']);
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Habits - TrackItPro</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f9f9f9;
            color: #333;
            min-height: 100vh;
            overflow-x: hidden;
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
            background-color: #76b5c5;
            padding: 20px 0;
            box-shadow: 2px 0 5px rgba(0, 0, 0, 0.1);
            animation: slideInLeft 0.5s ease-out;
        }

        @keyframes slideInLeft {
            from { transform: translateX(-100%); }
            to { transform: translateX(0); }
        }

        .logo-section {
            padding: 10px 20px;
            text-align: center;
            border-bottom: 1px solid #b0e0e6;
        }

        .logo {
            color: #fff;
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
            border-bottom: 1px solid #b0e0e6;
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
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .user-info h4 {
            color: #fff;
            font-size: 16px;
            margin-bottom: 4px;
        }

        .user-info p {
            color: #e6f0fa;
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
            color: #fff;
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
            background-color: #5d9aa9;
            animation: pulse 0.3s ease;
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }

        .nav-link.active {
            background-color: #5d9aa9;
            border-left: 4px solid #fff;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            padding: 20px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            margin-left: 20px;
            animation: fadeInUp 0.5s ease-out;
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
            border-bottom: 2px solid #e0e0e0;
        }

        .page-title {
            font-size: 28px;
            font-weight: bold;
            color: #333;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .page-title i {
            color: #76b5c5;
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
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
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

        /* Add Habit Form */
        .add-habit-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 30px;
            color: #fff;
            animation: scaleIn 0.5s ease-out;
        }

        @keyframes scaleIn {
            from { transform: scale(0.95); opacity: 0; }
            to { transform: scale(1); opacity: 1; }
        }

        .form-title {
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr auto;
            gap: 15px;
            align-items: end;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .form-label {
            font-size: 14px;
            font-weight: 600;
            color: rgba(255, 255, 255, 0.9);
        }

        .form-input,
        .form-select {
            padding: 12px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            background: rgba(255, 255, 255, 0.95);
            color: #333;
            transition: all 0.3s ease;
        }

        .form-input:focus,
        .form-select:focus {
            outline: none;
            background: #fff;
            box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.3);
        }

        .btn-primary {
            padding: 12px 24px;
            background: #fff;
            color: #667eea;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
            white-space: nowrap;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.2);
        }

        /* Habits List */
        .habits-container {
            animation: fadeInUp 0.5s ease-out 0.2s both;
        }

        .section-title {
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 20px;
            color: #333;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-title i {
            color: #76b5c5;
        }

        .habits-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
        }

        .habit-card {
            background: #fff;
            border: 1px solid #e0e0e0;
            border-left: 4px solid #76b5c5;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
            animation: fadeInUp 0.5s ease-out;
        }

        .habit-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
        }

        .habit-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 15px;
        }

        .habit-name {
            font-size: 18px;
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
        }

        .habit-badge {
            padding: 4px 12px;
            background: #e3f2fd;
            color: #1976d2;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }

        .habit-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin: 15px 0;
            padding: 15px 0;
            border-top: 1px solid #e0e0e0;
            border-bottom: 1px solid #e0e0e0;
        }

        .stat-item {
            text-align: center;
        }

        .stat-value {
            font-size: 20px;
            font-weight: bold;
            color: #76b5c5;
        }

        .stat-label {
            font-size: 11px;
            color: #666;
            margin-top: 2px;
        }

        .habit-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }

        .btn-action {
            flex: 1;
            padding: 10px;
            border: none;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }

        .btn-complete {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: #fff;
        }

        .btn-complete:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(40, 167, 69, 0.3);
        }

        .btn-view {
            background: linear-gradient(135deg, #76b5c5 0%, #5d9aa9 100%);
            color: #fff;
        }

        .btn-view:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(118, 181, 197, 0.3);
        }

        .btn-delete {
            background: #f44336;
            color: #fff;
            padding: 10px 15px;
        }

        .btn-delete:hover {
            background: #d32f2f;
            transform: translateY(-2px);
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: #f8f9fa;
            border-radius: 12px;
            margin-top: 20px;
        }

        .empty-icon {
            font-size: 64px;
            color: #76b5c5;
            margin-bottom: 20px;
        }

        .empty-title {
            font-size: 22px;
            font-weight: bold;
            color: #333;
            margin-bottom: 10px;
        }

        .empty-text {
            color: #666;
            margin-bottom: 20px;
        }

        @media (max-width: 1024px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
            .habits-grid {
                grid-template-columns: 1fr;
            }
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
            .page-title {
                font-size: 22px;
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
                    <a href="habits.php" class="nav-link active">
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
                    <a href="settings.php" class="nav-link">
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
                    <i class="fas fa-bolt"></i>
                    My Habits
                </h1>
            </div>

            <!-- Notifications -->
            <?php if ($errors): ?>
                <div class="notification error">
                    <i class="fas fa-exclamation-circle"></i>
                    <div>
                        <?php foreach ($errors as $error): ?>
                            <div><?php echo htmlspecialchars($error); ?></div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="notification success">
                    <i class="fas fa-check-circle"></i>
                    <span><?php echo htmlspecialchars($success); ?></span>
                </div>
            <?php endif; ?>

            <!-- Add Habit Form -->
            <div class="add-habit-section">
                <h2 class="form-title">
                    <i class="fas fa-plus-circle"></i>
                    Create New Habit
                </h2>
                <form method="POST" action="">
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Habit Name</label>
                            <input 
                                type="text" 
                                name="name" 
                                class="form-input" 
                                placeholder="E.g., Morning Exercise, Read 20 pages..." 
                                required
                            >
                        </div>
                        <div class="form-group">
                            <label class="form-label">Frequency</label>
                            <select name="frequency" class="form-select" required>
                                <option value="Daily">Daily</option>
                                <option value="Weekly">Weekly</option>
                                <option value="Twice Weekly">Twice Weekly</option>
                                <option value="3x Weekly">3x Weekly</option>
                                <option value="4x Weekly">4x Weekly</option>
                                <option value="5x Weekly">5x Weekly</option>
                                <option value="6x Weekly">6x Weekly</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Goal (Days)</label>
                            <input 
                                type="number" 
                                name="goal" 
                                class="form-input" 
                                placeholder="30" 
                                min="1" 
                                required
                            >
                        </div>
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <button type="submit" name="add" class="btn-primary">
                            <i class="fas fa-plus"></i>
                            Add Habit
                        </button>
                    </div>
                </form>
            </div>

            <!-- Habits List -->
            <div class="habits-container">
                <h2 class="section-title">
                    <i class="fas fa-list-check"></i>
                    Your Active Habits (<?php echo count($habits); ?>)
                </h2>

                <?php if (empty($habits)): ?>
                    <div class="empty-state">
                        <div class="empty-icon">
                            <i class="fas fa-clipboard-list"></i>
                        </div>
                        <div class="empty-title">No Habits Yet!</div>
                        <div class="empty-text">Start building great habits today. Create your first habit using the form above.</div>
                    </div>
                <?php else: ?>
                    <div class="habits-grid">
                        <?php foreach ($habits as $habit): ?>
                            <div class="habit-card">
                                <div class="habit-header">
                                    <div>
                                        <div class="habit-name"><?php echo htmlspecialchars($habit['name']); ?></div>
                                        <span class="habit-badge">
                                            <i class="far fa-calendar"></i>
                                            <?php echo htmlspecialchars($habit['frequency']); ?>
                                        </span>
                                    </div>
                                </div>

                                <div class="habit-stats">
                                    <div class="stat-item">
                                        <div class="stat-value">
                                            <i class="fas fa-fire"></i>
                                            <?php echo $habit['current_streak']; ?>
                                        </div>
                                        <div class="stat-label">Streak</div>
                                    </div>
                                    <div class="stat-item">
                                        <div class="stat-value">
                                            <?php echo $habit['total_completions']; ?>
                                        </div>
                                        <div class="stat-label">Total</div>
                                    </div>
                                    <div class="stat-item">
                                        <div class="stat-value">
                                            <?php echo $habit['progress']; ?>%
                                        </div>
                                        <div class="stat-label">Progress</div>
                                    </div>
                                </div>

                                <div class="habit-actions">
                                    <form method="POST" action="" style="flex: 1;">
                                        <input type="hidden" name="habit_id" value="<?php echo $habit['habit_id']; ?>">
                                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                        <button type="submit" name="mark_complete" class="btn-action btn-complete">
                                            <i class="fas fa-check"></i>
                                            Complete Today
                                        </button>
                                    </form>
                                    <button 
                                        onclick="window.location.href='habit_detail.php?id=<?php echo $habit['habit_id']; ?>'" 
                                        class="btn-action btn-view"
                                    >
                                        <i class="fas fa-eye"></i>
                                        View Details
                                    </button>
                                    <form method="POST" action="" onsubmit="return confirm('Are you sure you want to delete this habit?');">
                                        <input type="hidden" name="habit_id" value="<?php echo $habit['habit_id']; ?>">
                                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                        <button type="submit" name="delete" class="btn-action btn-delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>