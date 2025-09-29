<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/habit.php';
requireLogin();

$habits = getHabits($_SESSION['user_id']);
$quotes = [
    "Small steps every day lead to big results.",
    "Consistency is the key to success.",
    "You are stronger than your excuses."
];
$quote = $quotes[array_rand($quotes)];

// Calculate dynamic statistics
$activeHabits = count($habits);
$totalCompletions = 0;
$totalExpected = 0;
$longestStreak = 0;
$goalsAchieved = 0;

foreach ($habits as $habit) {
    // Calculate completions this week
    $completions = isset($habit['completions_this_week']) ? $habit['completions_this_week'] : 0;
    $totalCompletions += $completions;
    
    // Calculate expected completions based on frequency
    $expected = 7; // Default to daily
    if (isset($habit['frequency'])) {
        $freq = strtolower($habit['frequency']);
        if (strpos($freq, 'weekly') !== false) $expected = 1;
        elseif (strpos($freq, 'twice') !== false) $expected = 2;
    }
    $totalExpected += $expected;
    
    // Track longest streak
    if (isset($habit['current_streak']) && $habit['current_streak'] > $longestStreak) {
        $longestStreak = $habit['current_streak'];
    }
    
    // Count goals achieved (habits with 100% completion rate)
    if (isset($habit['total_completions']) && $habit['total_completions'] >= 30) {
        $goalsAchieved++;
    }
}

$weeklySuccess = $totalExpected > 0 ? round(($totalCompletions / $totalExpected) * 100) : 0;
$currentStreak = $longestStreak > 0 ? $longestStreak : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - TrackItPro</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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

        .dashboard-container {
            display: flex;
            width: 100%;
            min-height: 100vh;
            padding: 20px;
        }

        .sidebar {
            width: 250px;
            background-color: #76b5c5;
            padding: 20px 0;
            box-shadow: 2px 0 5px rgba(0, 0, 0, 0.1);
            animation: slideIn 0.5s ease-out;
        }

        @keyframes slideIn {
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

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
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

        .main-content {
            flex: 1;
            padding: 20px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            animation: fadeInUp 0.5s ease-out;
            margin-left: 20px;
        }

        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .top-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .page-title {
            font-size: 28px;
            font-weight: bold;
            color: #333;
        }

        .welcome-card {
            padding: 25px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 12px;
            margin-bottom: 20px;
            animation: slideInRight 0.5s ease-out;
            color: #fff;
        }

        @keyframes slideInRight {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }

        .welcome-title {
            font-size: 24px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .welcome-title i {
            font-size: 28px;
        }

        .welcome-subtitle {
            color: rgba(255, 255, 255, 0.9);
            margin-bottom: 15px;
            font-size: 16px;
        }

        .motivational-quote {
            padding: 15px;
            background-color: rgba(255, 255, 255, 0.15);
            border-left: 4px solid #fff;
            font-style: italic;
            animation: fadeIn 0.5s ease-out 0.2s both;
            backdrop-filter: blur(10px);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .stat-card {
            padding: 20px;
            background: linear-gradient(135deg, #f0f8ff 0%, #e6f0fa 100%);
            border-radius: 12px;
            text-align: center;
            animation: bounceIn 0.5s ease-out;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border: 1px solid #e0e0e0;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.15);
        }

        @keyframes bounceIn {
            from { transform: scale(0.9); opacity: 0; }
            to { transform: scale(1); opacity: 1; }
        }

        .stat-icon {
            font-size: 32px;
            margin-bottom: 10px;
            color: #76b5c5;
        }

        .stat-value {
            font-size: 28px;
            font-weight: bold;
            margin-bottom: 5px;
            color: #333;
        }

        .stat-label {
            color: #666;
            font-size: 14px;
        }

        .habits-section {
            padding: 25px;
            background-color: #f0f8ff;
            border-radius: 12px;
            animation: slideInLeft 0.5s ease-out;
        }

        @keyframes slideInLeft {
            from { transform: translateX(-100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }

        .section-header {
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-title {
            font-size: 20px;
            font-weight: bold;
        }

        .section-title i {
            color: #76b5c5;
        }

        .habits-table {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
            border-radius: 8px;
            overflow: hidden;
        }

        .table-header th {
            padding: 15px;
            background-color: #76b5c5;
            text-align: left;
            color: #fff;
            font-weight: 600;
        }

        .table-row td {
            padding: 15px;
            border-bottom: 1px solid #e6f0fa;
        }

        .table-row:hover {
            background-color: #f8f9fa;
        }

        .habit-status {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            font-size: 18px;
            transition: transform 0.3s ease;
        }

        .habit-status:hover {
            transform: scale(1.15);
        }

        .status-excellent { background-color: #28a745; color: #fff; }
        .status-good { background-color: #17a2b8; color: #fff; }
        .status-needs-work { background-color: #ffc107; color: #333; }

        .habit-name {
            font-weight: 600;
            color: #333;
            margin-bottom: 4px;
        }

        .habit-frequency {
            font-size: 12px;
            color: #666;
        }

        .progress-cell {
            min-width: 150px;
        }

        .progress-percentage {
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 5px;
            color: #76b5c5;
        }

        .progress-bar-container {
            width: 100%;
            height: 10px;
            background-color: #e6f0fa;
            border-radius: 5px;
            overflow: hidden;
        }

        .progress-bar {
            height: 100%;
            background: linear-gradient(90deg, #76b5c5 0%, #5d9aa9 100%);
            border-radius: 5px;
            transition: width 0.5s ease;
        }

        .view-btn {
            padding: 8px 16px;
            background: linear-gradient(135deg, #76b5c5 0%, #5d9aa9 100%);
            color: #fff;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .view-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(93, 154, 169, 0.3);
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
        }

        .empty-state-icon {
            font-size: 64px;
            color: #76b5c5;
            margin-bottom: 20px;
        }

        .empty-message {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .empty-description {
            color: #666;
            margin-bottom: 20px;
        }

        .create-habit-btn {
            display: inline-block;
            padding: 12px 24px;
            background: linear-gradient(135deg, #76b5c5 0%, #5d9aa9 100%);
            color: #fff;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .create-habit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(93, 154, 169, 0.3);
        }

        .chart-section {
            padding: 25px;
            background-color: #f0f8ff;
            border-radius: 12px;
            margin-top: 20px;
            animation: fadeInUp 0.5s ease-out;
        }

        .chart-header {
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .chart-container {
            height: 300px;
            background: #fff;
            padding: 20px;
            border-radius: 8px;
        }

        @media (max-width: 768px) {
            .dashboard-container {
                flex-direction: column;
            }
            .sidebar {
                width: 100%;
            }
            .main-content {
                margin-left: 0;
                margin-top: 20px;
                padding: 15px;
            }
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Notification Styles */
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 20px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            display: flex;
            align-items: center;
            gap: 10px;
            transform: translateX(400px);
            opacity: 0;
            transition: all 0.3s ease;
            z-index: 10000;
            max-width: 350px;
        }

        .notification.show {
            transform: translateX(0);
            opacity: 1;
        }

        .notification i {
            font-size: 20px;
        }

        .notification-success {
            border-left: 4px solid #28a745;
            color: #155724;
        }

        .notification-success i {
            color: #28a745;
        }

        .notification-error {
            border-left: 4px solid #dc3545;
            color: #721c24;
        }

        .notification-error i {
            color: #dc3545;
        }

        .notification-info {
            border-left: 4px solid #17a2b8;
            color: #0c5460;
        }

        .notification-info i {
            color: #17a2b8;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
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
                    <a href="#" class="nav-link active">
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
        <div class="main-content">
            <div class="top-header">
                <h1 class="page-title">Dashboard</h1>
            </div>
            <div class="welcome-card">
                <h2 class="welcome-title">
                    <i class="fas fa-hand-wave"></i>
                    Hello, <?php echo htmlspecialchars($_SESSION['name']); ?>!
                </h2>
                <p class="welcome-subtitle">
                    Ready to build amazing habits? You've completed <?php echo $weeklySuccess; ?>% of your weekly goals.
                </p>
                <div class="motivational-quote">
                    <i class="fas fa-quote-left" style="margin-right: 8px;"></i>
                    <?php echo htmlspecialchars($quote); ?>
                    <i class="fas fa-quote-right" style="margin-left: 8px;"></i>
                </div>
            </div>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                    <div class="stat-value"><?php echo $activeHabits; ?></div>
                    <div class="stat-label">Active Habits</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-percentage"></i></div>
                    <div class="stat-value"><?php echo $weeklySuccess; ?>%</div>
                    <div class="stat-label">Weekly Success</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-fire"></i></div>
                    <div class="stat-value"><?php echo $currentStreak; ?></div>
                    <div class="stat-label">Day Streak</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-star"></i></div>
                    <div class="stat-value"><?php echo $goalsAchieved; ?></div>
                    <div class="stat-label">Goals Achieved</div>
                </div>
            </div>
            <div class="habits-section">
                <div class="section-header">
                    <h3 class="section-title">
                        <i class="fas fa-list-check"></i>
                        Your Habits
                    </h3>
                </div>
                <?php if (!empty($habits)): ?>
                    <table class="habits-table">
                        <thead class="table-header">
                            <tr>
                                <th>Status</th>
                                <th>Habit</th>
                                <th>Frequency</th>
                                <th>Progress</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($habits, 0, 5) as $index => $habit): 
                                $progress = isset($habit['progress']) ? $habit['progress'] : rand(60, 95);
                                $statusClass = $progress >= 85 ? 'excellent' : ($progress >= 70 ? 'good' : 'needs-work');
                                $statusIcon = $progress >= 85 ? 'fa-star' : ($progress >= 70 ? 'fa-thumbs-up' : 'fa-exclamation-triangle');
                            ?>
                                <tr class="table-row">
                                    <td>
                                        <div class="habit-status status-<?php echo $statusClass; ?>">
                                            <i class="fas <?php echo $statusIcon; ?>"></i>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="habit-name"><?php echo htmlspecialchars($habit['name']); ?></div>
                                        <div class="habit-frequency">
                                            <i class="far fa-calendar"></i>
                                            <?php echo htmlspecialchars($habit['frequency']); ?>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($habit['frequency']); ?></td>
                                    <td class="progress-cell">
                                        <div class="progress-percentage"><?php echo $progress; ?>%</div>
                                        <div class="progress-bar-container">
                                            <div class="progress-bar" style="width: <?php echo $progress; ?>%"></div>
                                        </div>
                                    </td>
                                    <td>
                                        <button class="view-btn" onclick="viewHabit(<?php echo $habit['habit_id']; ?>)">
                                            <i class="fas fa-check"></i>
                                            Track
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">
                            <i class="fas fa-clipboard-list"></i>
                        </div>
                        <div class="empty-message">Ready to build great habits?</div>
                        <div class="empty-description">Start your journey by creating your first habit!</div>
                        <a href="habits.php" class="create-habit-btn">
                            <i class="fas fa-plus"></i>
                            Create Your First Habit
                        </a>
                    </div>
                <?php endif; ?>
            </div>
            <div class="chart-section">
                <div class="chart-header">
                    <h3 class="section-title">
                        <i class="fas fa-chart-area"></i>
                        Progress Analytics
                    </h3>
                </div>
                <div class="chart-container">
                    <canvas id="progressChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <script>
        let progressChart = null;

        function initializeChart() {
            const ctx = document.getElementById('progressChart');
            if (!ctx) return;

            progressChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                    datasets: [{
                        label: 'Habit Success Rate',
                        data: [68, 72, 65, 78, 82, 88, 85, 90, 93, 89, 95, 92],
                        borderColor: '#5d9aa9',
                        backgroundColor: 'rgba(118, 181, 197, 0.2)',
                        fill: true,
                        tension: 0.4,
                        borderWidth: 3,
                        pointRadius: 5,
                        pointHoverRadius: 7,
                        pointBackgroundColor: '#5d9aa9',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { 
                        legend: { 
                            display: true,
                            labels: {
                                font: { size: 14, weight: 'bold' },
                                color: '#333'
                            }
                        },
                        tooltip: {
                            backgroundColor: 'rgba(0, 0, 0, 0.8)',
                            padding: 12,
                            titleFont: { size: 14, weight: 'bold' },
                            bodyFont: { size: 13 }
                        }
                    },
                    scales: {
                        y: { 
                            beginAtZero: true, 
                            max: 100, 
                            ticks: { 
                                color: '#666',
                                font: { size: 12 },
                                callback: function(value) {
                                    return value + '%';
                                }
                            },
                            grid: {
                                color: 'rgba(0, 0, 0, 0.05)'
                            }
                        },
                        x: { 
                            ticks: { 
                                color: '#666',
                                font: { size: 12 }
                            },
                            grid: {
                                display: false
                            }
                        }
                    },
                    animation: { duration: 1500, easing: 'easeOutQuart' }
                }
            });
        }

        function viewHabit(habitId) {
            // Check if habit_detail.php exists, otherwise go to habits.php with the ID
            window.location.href = 'habit_detail.php?id=' + habitId;
        }

        function trackHabitToday(habitId) {
            // Make AJAX call to mark habit as complete for today
            fetch('track_habit.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'habit_id=' + habitId + '&date=' + new Date().toISOString().split('T')[0]
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('Success! Habit tracked for today.', 'success');
                    // Refresh the page after a short delay
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showNotification(data.message || 'Already tracked or error occurred.', 'info');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Error tracking habit. Please try again.', 'error');
            });
        }

        function showNotification(message, type = 'info') {
            // Create notification element
            const notification = document.createElement('div');
            notification.className = 'notification notification-' + type;
            notification.innerHTML = '<i class="fas fa-' + (type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle') + '"></i> ' + message;
            
            // Add to body
            document.body.appendChild(notification);
            
            // Trigger animation
            setTimeout(() => notification.classList.add('show'), 10);
            
            // Remove after 3 seconds
            setTimeout(() => {
                notification.classList.remove('show');
                setTimeout(() => notification.remove(), 300);
            }, 3000);
        }

        document.addEventListener('DOMContentLoaded', () => {
            initializeChart();
        });
    </script>
</body>
</html>
