<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/reward.php';
requireLogin();

$stmt = $GLOBALS['pdo']->prepare("SELECT points, badge_name FROM rewards WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$rewards = $stmt->fetch(PDO::FETCH_ASSOC);

// Leaderboard (top 10 users by points)
$stmt = $GLOBALS['pdo']->prepare("
    SELECT u.name, r.points, r.badge_name 
    FROM rewards r 
    JOIN users u ON r.user_id = u.user_id 
    ORDER BY r.points DESC 
    LIMIT 3
");
$stmt->execute();
$leaderboard = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate user's rank
$stmt = $GLOBALS['pdo']->prepare("
    SELECT COUNT(*) + 1 as rank 
    FROM rewards 
    WHERE points > (SELECT MAX(points) FROM rewards WHERE user_id = ?)
");
$stmt->execute([$_SESSION['user_id']]);
$user_rank = $stmt->fetchColumn();

// Badge information
$badges = [
    'None' => ['color' => '#9e9e9e', 'icon' => 'fa-user', 'required' => 0],
    'Bronze' => ['color' => '#cd7f32', 'icon' => 'fa-medal', 'required' => 100],
    'Silver' => ['color' => '#c0c0c0', 'icon' => 'fa-medal', 'required' => 500],
    'Gold' => ['color' => '#ffd700', 'icon' => 'fa-medal', 'required' => 1000],
    'Platinum' => ['color' => '#e5e4e2', 'icon' => 'fa-crown', 'required' => 2000],
    'Diamond' => ['color' => '#b9f2ff', 'icon' => 'fa-gem', 'required' => 5000]
];

$current_badge = $rewards['badge_name'] ?? 'None';
$current_points = $rewards['points'] ?? 0;

// Find next badge
$next_badge = null;
$points_to_next = 0;
foreach ($badges as $name => $info) {
    if ($info['required'] > $current_points) {
        $next_badge = $name;
        $points_to_next = $info['required'] - $current_points;
        break;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rewards & Achievements - TrackItPro</title>
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

        /* Rewards Hero Section */
        .rewards-hero {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 40px;
            border-radius: 12px;
            margin-bottom: 30px;
            color: #fff;
            text-align: center;
            animation: scaleIn 0.5s ease-out;
            position: relative;
            overflow: hidden;
        }

        .rewards-hero::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            animation: rotate 20s linear infinite;
        }

        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        @keyframes scaleIn {
            from { transform: scale(0.95); opacity: 0; }
            to { transform: scale(1); opacity: 1; }
        }

        .badge-display {
            position: relative;
            z-index: 1;
            margin-bottom: 20px;
        }

        .badge-icon-large {
            font-size: 80px;
            margin-bottom: 10px;
            filter: drop-shadow(0 4px 8px rgba(0,0,0,0.2));
            animation: float 3s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }

        .badge-name {
            font-size: 32px;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .points-display {
            font-size: 48px;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .rank-display {
            font-size: 18px;
            opacity: 0.9;
        }

        /* Progress Section */
        .progress-section {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 30px;
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

        .progress-bar-container {
            background: #e0e0e0;
            border-radius: 20px;
            height: 30px;
            overflow: hidden;
            margin-bottom: 15px;
            position: relative;
        }

        .progress-bar-fill {
            height: 100%;
            background: linear-gradient(90deg, #76b5c5 0%, #5d9aa9 100%);
            border-radius: 20px;
            transition: width 1s ease-out;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            padding-right: 10px;
            color: #fff;
            font-weight: bold;
            font-size: 14px;
        }

        .next-badge-info {
            text-align: center;
            color: #666;
            font-size: 14px;
        }

        /* Badges Grid */
        .badges-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }

        .badge-card {
            background: #fff;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            transition: all 0.3s ease;
        }

        .badge-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
        }

        .badge-card.unlocked {
            border-color: #76b5c5;
            background: linear-gradient(135deg, #f0f8ff 0%, #e6f0fa 100%);
        }

        .badge-card.current {
            border-color: #667eea;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff;
        }

        .badge-card i {
            font-size: 40px;
            margin-bottom: 10px;
        }

        .badge-card .badge-title {
            font-weight: bold;
            font-size: 16px;
            margin-bottom: 5px;
        }

        .badge-card .badge-requirement {
            font-size: 12px;
            opacity: 0.8;
        }

        /* Leaderboard */
        .leaderboard-section {
            animation: fadeInUp 0.5s ease-out 0.3s both;
        }

        .leaderboard-table {
            background: #fff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        .leaderboard-header {
            background: linear-gradient(135deg, #76b5c5 0%, #5d9aa9 100%);
            color: #fff;
            padding: 15px 20px;
            display: grid;
            grid-template-columns: 60px 1fr 120px 100px;
            font-weight: bold;
        }

        .leaderboard-row {
            padding: 15px 20px;
            display: grid;
            grid-template-columns: 60px 1fr 120px 100px;
            align-items: center;
            border-bottom: 1px solid #e0e0e0;
            transition: background 0.3s ease;
        }

        .leaderboard-row:hover {
            background: #f8f9fa;
        }

        .leaderboard-row.current-user {
            background: linear-gradient(90deg, #fff3cd 0%, #fff 100%);
            border-left: 4px solid #ffc107;
        }

        .rank-number {
            font-size: 24px;
            font-weight: bold;
            color: #76b5c5;
        }

        .rank-number.top-1 { color: #ffd700; }
        .rank-number.top-2 { color: #c0c0c0; }
        .rank-number.top-3 { color: #cd7f32; }

        .user-name {
            font-weight: 600;
            color: #333;
        }

        .user-badge {
            text-align: center;
        }

        .user-points {
            text-align: right;
            font-weight: bold;
            color: #76b5c5;
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
            .rewards-hero {
                padding: 25px;
            }
            .badge-icon-large {
                font-size: 60px;
            }
            .points-display {
                font-size: 36px;
            }
            .leaderboard-header,
            .leaderboard-row {
                grid-template-columns: 50px 1fr 80px;
            }
            .user-badge {
                display: none;
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
                    <a href="rewards.php" class="nav-link active">
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
                    <i class="fas fa-trophy"></i>
                    Rewards & Achievements
                </h1>
            </div>

            <!-- Rewards Hero -->
            <div class="rewards-hero">
                <div class="badge-display">
                    <div class="badge-icon-large">
                        <i class="fas <?php echo $badges[$current_badge]['icon']; ?>" 
                           style="color: <?php echo $badges[$current_badge]['color']; ?>;"></i>
                    </div>
                    <div class="badge-name"><?php echo htmlspecialchars($current_badge); ?> Badge</div>
                    <div class="points-display"><?php echo number_format($current_points); ?> Points</div>
                    <div class="rank-display">
                        <i class="fas fa-ranking-star"></i>
                        Rank #<?php echo $user_rank; ?> Overall
                    </div>
                </div>
            </div>

            <!-- Progress to Next Badge -->
            <?php if ($next_badge): ?>
            <div class="progress-section">
                <h2 class="section-title">
                    <i class="fas fa-target"></i>
                    Next Milestone: <?php echo $next_badge; ?> Badge
                </h2>
                <div class="progress-bar-container">
                    <?php 
                    $current_tier_points = 0;
                    foreach ($badges as $name => $info) {
                        if ($info['required'] <= $current_points) {
                            $current_tier_points = $info['required'];
                        }
                    }
                    $progress_percentage = (($current_points - $current_tier_points) / ($badges[$next_badge]['required'] - $current_tier_points)) * 100;
                    ?>
                    <div class="progress-bar-fill" style="width: <?php echo min($progress_percentage, 100); ?>%">
                        <?php echo round($progress_percentage); ?>%
                    </div>
                </div>
                <div class="next-badge-info">
                    <?php echo number_format($points_to_next); ?> points until <?php echo $next_badge; ?> badge
                </div>
            </div>
            <?php endif; ?>

            <!-- All Badges -->
            <div class="progress-section">
                <h2 class="section-title">
                    <i class="fas fa-medal"></i>
                    All Badges
                </h2>
                <div class="badges-grid">
                    <?php foreach ($badges as $name => $info): ?>
                        <?php if ($name === 'None') continue; ?>
                        <div class="badge-card <?php 
                            echo ($name === $current_badge) ? 'current' : 
                                (($info['required'] <= $current_points) ? 'unlocked' : ''); 
                        ?>">
                            <i class="fas <?php echo $info['icon']; ?>" 
                               style="color: <?php echo ($name === $current_badge || $info['required'] <= $current_points) ? $info['color'] : '#ccc'; ?>;"></i>
                            <div class="badge-title"><?php echo $name; ?></div>
                            <div class="badge-requirement">
                                <?php echo number_format($info['required']); ?> points
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Leaderboard -->
            <div class="leaderboard-section">
                <h2 class="section-title">
                    <i class="fas fa-ranking-star"></i>
                    Top Performers
                </h2>
                <div class="leaderboard-table">
                    <div class="leaderboard-header">
                        <div>Rank</div>
                        <div>User</div>
                        <div>Badge</div>
                        <div>Points</div>
                    </div>
                    <?php foreach ($leaderboard as $index => $entry): 
                        $rank = $index + 1;
                        $is_current = ($entry['name'] === $_SESSION['name']);
                        $rank_class = $rank === 1 ? 'top-1' : ($rank === 2 ? 'top-2' : ($rank === 3 ? 'top-3' : ''));
                    ?>
                        <div class="leaderboard-row <?php echo $is_current ? 'current-user' : ''; ?>">
                            <div class="rank-number <?php echo $rank_class; ?>">
                                <?php if ($rank <= 3): ?>
                                    <i class="fas fa-crown"></i>
                                <?php endif; ?>
                                #<?php echo $rank; ?>
                            </div>
                            <div class="user-name">
                                <?php echo htmlspecialchars($entry['name']); ?>
                                <?php if ($is_current): ?>
                                    <span style="color: #ffc107; margin-left: 5px;">
                                        <i class="fas fa-star"></i> You
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div class="user-badge">
                                <i class="fas <?php echo $badges[$entry['badge_name'] ?? 'None']['icon']; ?>" 
                                   style="color: <?php echo $badges[$entry['badge_name'] ?? 'None']['color']; ?>;"></i>
                                <?php echo htmlspecialchars($entry['badge_name'] ?? 'None'); ?>
                            </div>
                            <div class="user-points">
                                <?php echo number_format($entry['points']); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>