<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/habit.php';
requireLogin();

$habits = getHabits($_SESSION['user_id']);
$progress_data = [];

foreach ($habits as $habit) {
    $stmt = $GLOBALS['pdo']->prepare("
        SELECT DATE_FORMAT(date_completed, '%Y-%m-%d') as date, 
               COUNT(*) as completions 
        FROM progress 
        WHERE habit_id = ? 
        GROUP BY date_completed 
        ORDER BY date_completed
    ");
    $stmt->execute([$habit['habit_id']]);
    $completions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate weekly/monthly completion rates
    $weekly_completions = 0;
    $monthly_completions = 0;
    $today = new DateTime();
    $week_ago = (new DateTime())->modify('-7 days');
    $month_ago = (new DateTime())->modify('-30 days');
    
    foreach ($completions as $comp) {
        $date = new DateTime($comp['date']);
        if ($date >= $week_ago) $weekly_completions += $comp['completions'];
        if ($date >= $month_ago) $monthly_completions += $comp['completions'];
    }
    
    // Best/worst days
    $best_day = $completions ? max(array_column($completions, 'completions')) : 0;
    $worst_day = $completions ? min(array_column($completions, 'completions')) : 0;
    $best_date = 'N/A';
    $worst_date = 'N/A';
    
    if ($completions) {
        foreach ($completions as $comp) {
            if ($comp['completions'] == $best_day) {
                $best_date = $comp['date'];
                break;
            }
        }
        foreach ($completions as $comp) {
            if ($comp['completions'] == $worst_day) {
                $worst_date = $comp['date'];
                break;
            }
        }
    }
    
    $progress_data[$habit['habit_id']] = [
        'name' => $habit['name'],
        'streak' => $habit['current_streak'],
        'completions' => $completions,
        'weekly_rate' => $weekly_completions,
        'monthly_rate' => $monthly_completions,
        'total_completions' => $habit['total_completions'],
        'best_day' => ['date' => $best_date, 'count' => $best_day],
        'worst_day' => ['date' => $worst_date, 'count' => $worst_day],
        'completion_rate' => $habit['completion_rate']
    ];
}

// Get overall user stats
$stats = getUserStats($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Progress & Analytics - TrackItPro</title>
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

        /* Overview Stats */
        .overview-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
            animation: fadeInUp 0.5s ease-out 0.2s both;
        }

        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
            border-radius: 12px;
            color: #fff;
            text-align: center;
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-card i {
            font-size: 32px;
            margin-bottom: 10px;
            opacity: 0.9;
        }

        .stat-card .value {
            font-size: 28px;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .stat-card .label {
            font-size: 14px;
            opacity: 0.9;
        }

        /* Progress Items */
        .progress-section {
            animation: fadeInUp 0.5s ease-out 0.3s both;
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

        .progress-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(450px, 1fr));
            gap: 20px;
        }

        .progress-card {
            background: #fff;
            border: 1px solid #e0e0e0;
            border-left: 4px solid #76b5c5;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
        }

        .progress-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
        }

        .habit-header {
            margin-bottom: 20px;
        }

        .habit-name {
            font-size: 20px;
            font-weight: bold;
            color: #333;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .stats-row {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }

        .mini-stat {
            text-align: center;
            padding: 12px;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .mini-stat .value {
            font-size: 20px;
            font-weight: bold;
            color: #76b5c5;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
        }

        .mini-stat .label {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }

        .chart-container {
            height: 250px;
            margin-top: 20px;
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
        }

        .insights {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #e0e0e0;
        }

        .insight-item {
            padding: 10px;
            background: #e3f2fd;
            border-radius: 8px;
            font-size: 13px;
        }

        .insight-label {
            font-weight: 600;
            color: #1976d2;
            margin-bottom: 3px;
        }

        .insight-value {
            color: #555;
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

        .empty-btn {
            display: inline-block;
            padding: 12px 24px;
            background: linear-gradient(135deg, #76b5c5 0%, #5d9aa9 100%);
            color: #fff;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .empty-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(118, 181, 197, 0.3);
        }

        @media (max-width: 1024px) {
            .progress-grid {
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
            .overview-stats {
                grid-template-columns: 1fr;
            }
            .stats-row {
                grid-template-columns: 1fr;
            }
            .insights {
                grid-template-columns: 1fr;
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
                    <a href="progress.php" class="nav-link active">
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
                    <i class="fas fa-chart-bar"></i>
                    Progress & Analytics
                </h1>
            </div>

            <!-- Overview Stats -->
            <div class="overview-stats">
                <div class="stat-card">
                    <i class="fas fa-fire"></i>
                    <div class="value"><?php echo $stats['current_streak']; ?></div>
                    <div class="label">Current Streak</div>
                </div>
                <div class="stat-card">
                    <i class="fas fa-check-circle"></i>
                    <div class="value"><?php echo $stats['total_completions']; ?></div>
                    <div class="label">Total Completions</div>
                </div>
                <div class="stat-card">
                    <i class="fas fa-percentage"></i>
                    <div class="value"><?php echo $stats['weekly_success']; ?>%</div>
                    <div class="label">Weekly Success</div>
                </div>
                <div class="stat-card">
                    <i class="fas fa-star"></i>
                    <div class="value"><?php echo $stats['goals_achieved']; ?></div>
                    <div class="label">Goals Achieved</div>
                </div>
            </div>

            <!-- Progress Section -->
            <div class="progress-section">
                <h2 class="section-title">
                    <i class="fas fa-chart-area"></i>
                    Detailed Habit Analytics
                </h2>

                <?php if (empty($progress_data)): ?>
                    <div class="empty-state">
                        <div class="empty-icon">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <div class="empty-title">No Progress Data Yet</div>
                        <div class="empty-text">Start tracking your habits to see detailed analytics and insights!</div>
                        <a href="habits.php" class="empty-btn">
                            <i class="fas fa-plus"></i>
                            Create Your First Habit
                        </a>
                    </div>
                <?php else: ?>
                    <div class="progress-grid">
                        <?php foreach ($progress_data as $habit_id => $data): ?>
                            <div class="progress-card">
                                <div class="habit-header">
                                    <h3 class="habit-name">
                                        <i class="fas fa-bolt"></i>
                                        <?php echo htmlspecialchars($data['name']); ?>
                                    </h3>
                                </div>

                                <div class="stats-row">
                                    <div class="mini-stat">
                                        <div class="value">
                                            <i class="fas fa-fire"></i>
                                            <?php echo $data['streak']; ?>
                                        </div>
                                        <div class="label">Day Streak</div>
                                    </div>
                                    <div class="mini-stat">
                                        <div class="value">
                                            <?php echo $data['weekly_rate']; ?>
                                        </div>
                                        <div class="label">This Week</div>
                                    </div>
                                    <div class="mini-stat">
                                        <div class="value">
                                            <?php echo $data['total_completions']; ?>
                                        </div>
                                        <div class="label">All Time</div>
                                    </div>
                                </div>

                                <div class="chart-container">
                                    <canvas id="chart_<?php echo $habit_id; ?>"></canvas>
                                </div>

                                <div class="insights">
                                    <div class="insight-item">
                                        <div class="insight-label">
                                            <i class="fas fa-calendar-check"></i>
                                            Monthly Completions
                                        </div>
                                        <div class="insight-value"><?php echo $data['monthly_rate']; ?> completions</div>
                                    </div>
                                    <div class="insight-item">
                                        <div class="insight-label">
                                            <i class="fas fa-percentage"></i>
                                            Success Rate
                                        </div>
                                        <div class="insight-value"><?php echo $data['completion_rate']; ?>% overall</div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        const progressData = <?php echo json_encode($progress_data); ?>;

        document.addEventListener('DOMContentLoaded', () => {
            for (const [habit_id, data] of Object.entries(progressData)) {
                const ctx = document.getElementById(`chart_${habit_id}`);
                if (!ctx) continue;

                // Prepare data for the last 30 days or available data
                const labels = data.completions.length > 0 ? 
                    data.completions.slice(-30).map(c => {
                        const date = new Date(c.date);
                        return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
                    }) : [];
                
                const values = data.completions.length > 0 ?
                    data.completions.slice(-30).map(c => c.completions) : [];

                new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: 'Completions',
                            data: values,
                            borderColor: '#76b5c5',
                            backgroundColor: 'rgba(118, 181, 197, 0.1)',
                            fill: true,
                            tension: 0.4,
                            borderWidth: 3,
                            pointRadius: 4,
                            pointHoverRadius: 6,
                            pointBackgroundColor: '#5d9aa9',
                            pointBorderColor: '#fff',
                            pointBorderWidth: 2
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { 
                            legend: { display: false },
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
                                ticks: { 
                                    color: '#666',
                                    font: { size: 11 }
                                },
                                grid: {
                                    color: 'rgba(0, 0, 0, 0.05)'
                                }
                            },
                            x: { 
                                ticks: { 
                                    color: '#666',
                                    font: { size: 10 },
                                    maxRotation: 45,
                                    minRotation: 45
                                },
                                grid: {
                                    display: false
                                }
                            }
                        },
                        animation: {
                            duration: 1500,
                            easing: 'easeOutQuart'
                        }
                    }
                });
            }
        });
    </script>
</body>
</html>