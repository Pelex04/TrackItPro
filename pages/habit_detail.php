<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/habit.php';
requireLogin();

$habit_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($habit_id <= 0) {
    header('Location: habits.php');
    exit;
}

$habit = getHabitById($habit_id, $_SESSION['user_id']);

if (!$habit) {
    header('Location: habits.php');
    exit;
}


$currentYear = date('Y');
$currentMonth = date('m');
$calendarDates = getHabitCalendarData($habit_id, $currentYear, $currentMonth);


$stmt = $GLOBALS['pdo']->prepare("
    SELECT date_completed 
    FROM progress 
    WHERE habit_id = ? 
    ORDER BY date_completed DESC 
    LIMIT 10
");
$stmt->execute([$habit_id]);
$recentDates = $stmt->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($habit['name']); ?> - TrackItPro</title>
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
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 30px;
        }

        .back-btn {
            padding: 10px 20px;
            background: #76b5c5;
            color: #fff;
            border: none;
            border-radius: 8px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }

        .back-btn:hover {
            background: #5d9aa9;
            transform: translateX(-5px);
        }

        .habit-title {
            font-size: 32px;
            font-weight: bold;
            color: #333;
        }

        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-box {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 25px;
            border-radius: 12px;
            color: #fff;
            text-align: center;
        }

        .stat-box i {
            font-size: 32px;
            margin-bottom: 10px;
            opacity: 0.9;
        }

        .stat-box .value {
            font-size: 36px;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .stat-box .label {
            font-size: 14px;
            opacity: 0.9;
        }

        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
        }

        .card {
            background: #fff;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .card-title {
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            color: #76b5c5;
        }

        .track-btn {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 18px;
            font-weight: bold;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: all 0.3s ease;
        }

        .track-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(40, 167, 69, 0.3);
        }

        .track-btn:disabled {
            background: #6c757d;
            cursor: not-allowed;
            transform: none;
        }

        .calendar {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 10px;
            margin-top: 15px;
        }

        .calendar-day {
            aspect-ratio: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            background: #f0f8ff;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .calendar-day.header {
            background: #76b5c5;
            color: #fff;
            font-size: 12px;
        }

        .calendar-day.completed {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: #fff;
        }

        .calendar-day.today {
            border: 3px solid #667eea;
        }

        .recent-list {
            list-style: none;
        }

        .recent-item {
            padding: 12px;
            background: #f0f8ff;
            border-radius: 8px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .recent-item i {
            color: #28a745;
            font-size: 18px;
        }

        .habit-info {
            background: #f0f8ff;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #e0e0e0;
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-label {
            font-weight: 600;
            color: #666;
        }

        @media (max-width: 768px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
            .stats-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <a href="dashboard.php" class="back-btn">
                <i class="fas fa-arrow-left"></i>
                Back to Dashboard
            </a>
            <h1 class="habit-title"><?php echo htmlspecialchars($habit['name']); ?></h1>
        </div>

        <div class="stats-row">
            <div class="stat-box">
                <i class="fas fa-fire"></i>
                <div class="value"><?php echo $habit['current_streak']; ?></div>
                <div class="label">Current Streak</div>
            </div>
            <div class="stat-box">
                <i class="fas fa-trophy"></i>
                <div class="value"><?php echo $habit['longest_streak']; ?></div>
                <div class="label">Longest Streak</div>
            </div>
            <div class="stat-box">
                <i class="fas fa-check-circle"></i>
                <div class="value"><?php echo $habit['total_completions']; ?></div>
                <div class="label">Total Completions</div>
            </div>
            <div class="stat-box">
                <i class="fas fa-percentage"></i>
                <div class="value"><?php echo $habit['progress']; ?>%</div>
                <div class="label">Weekly Progress</div>
            </div>
        </div>

        <div class="content-grid">
            <div class="card">
                <h3 class="card-title">
                    <i class="fas fa-calendar-alt"></i>
                    <?php echo date('F Y'); ?> Calendar
                </h3>
                
                <div class="calendar">
                    <?php
                    $daysOfWeek = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
                    foreach ($daysOfWeek as $day) {
                        echo '<div class="calendar-day header">' . $day . '</div>';
                    }

                    $firstDay = date('w', strtotime("$currentYear-$currentMonth-01"));
                    $daysInMonth = date('t', strtotime("$currentYear-$currentMonth-01"));
                    

                    for ($i = 0; $i < $firstDay; $i++) {
                        echo '<div class="calendar-day"></div>';
                    }
             
                    for ($day = 1; $day <= $daysInMonth; $day++) {
                        $dateStr = sprintf('%s-%s-%02d', $currentYear, $currentMonth, $day);
                        $isCompleted = in_array($dateStr, $calendarDates);
                        $isToday = $dateStr === date('Y-m-d');
                        
                        $classes = 'calendar-day';
                        if ($isCompleted) $classes .= ' completed';
                        if ($isToday) $classes .= ' today';
                        
                        echo '<div class="' . $classes . '">';
                        if ($isCompleted) {
                            echo '<i class="fas fa-check"></i>';
                        } else {
                            echo $day;
                        }
                        echo '</div>';
                    }
                    ?>
                </div>
            </div>

            <div>
                <div class="card" style="margin-bottom: 20px;">
                    <h3 class="card-title">
                        <i class="fas fa-crosshairs"></i>
                        Quick Track
                    </h3>
                    
                    <?php
                    $today = date('Y-m-d');
                    $isCompletedToday = isHabitCompletedOnDate($habit_id, $today);
                    ?>
                    
                    <button 
                        class="track-btn" 
                        onclick="trackHabit(<?php echo $habit_id; ?>)"
                        <?php echo $isCompletedToday ? 'disabled' : ''; ?>
                    >
                        <i class="fas fa-<?php echo $isCompletedToday ? 'check-double' : 'check'; ?>"></i>
                        <?php echo $isCompletedToday ? 'Completed Today!' : 'Mark Complete'; ?>
                    </button>

                    <div class="habit-info" style="margin-top: 20px;">
                        <div class="info-row">
                            <span class="info-label">Frequency:</span>
                            <span><?php echo htmlspecialchars($habit['frequency']); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Goal:</span>
                            <span><?php echo htmlspecialchars($habit['goal']); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Success Rate:</span>
                            <span><?php echo $habit['completion_rate']; ?>%</span>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <h3 class="card-title">
                        <i class="fas fa-history"></i>
                        Recent Activity
                    </h3>
                    
                    <?php if (!empty($recentDates)): ?>
                        <ul class="recent-list">
                            <?php foreach ($recentDates as $date): ?>
                                <li class="recent-item">
                                    <i class="fas fa-check-circle"></i>
                                    <span><?php echo date('M j, Y', strtotime($date)); ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p style="text-align: center; color: #666;">No activity yet. Start tracking today!</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        function trackHabit(habitId) {
            const btn = event.target.closest('button');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Tracking...';

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
                    btn.innerHTML = '<i class="fas fa-check-double"></i> Completed Today!';
                    setTimeout(() => location.reload(), 1000);
                } else {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-check"></i> Mark Complete';
                    alert(data.message || 'Error tracking habit');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-check"></i> Mark Complete';
                alert('Error tracking habit. Please try again.');
            });
        }
    </script>
</body>
</html>