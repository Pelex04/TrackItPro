<?php
require_once __DIR__ . '/db.php';

function awardPoints($habit_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT user_id FROM habits WHERE habit_id = ?");
    $stmt->execute([$habit_id]);
    $user_id = $stmt->fetchColumn();

    $stmt = $pdo->prepare("INSERT INTO rewards (user_id, points) VALUES (?, 10) ON DUPLICATE KEY UPDATE points = points + 10");
    $stmt->execute([$user_id]);

    
    $streak = getStreak($habit_id);
    if ($streak == 7) {
        $stmt = $pdo->prepare("UPDATE rewards SET badge_name = '7-Day Streak' WHERE user_id = ?");
        $stmt->execute([$user_id]);
    }
}

function getStreak($habit_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT date_completed FROM progress WHERE habit_id = ? ORDER BY date_completed DESC");
    $stmt->execute([$habit_id]);
    $dates = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $streak = 0;
    $today = new DateTime();
    foreach ($dates as $date) {
        $d = new DateTime($date);
        if ($d->format('Y-m-d') == $today->format('Y-m-d')) {
            $streak++;
            $today->modify('-1 day');
        } else {
            break;
        }
    }
    return $streak;
}
?>