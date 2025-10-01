<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/habit.php';


header('Content-Type: application/json');


if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}


if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}


$habit_id = isset($_POST['habit_id']) ? intval($_POST['habit_id']) : 0;
$date = isset($_POST['date']) ? $_POST['date'] : date('Y-m-d');


if ($habit_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid habit ID']);
    exit;
}


$habit = getHabitById($habit_id, $_SESSION['user_id']);
if (!$habit) {
    echo json_encode(['success' => false, 'message' => 'Habit not found or access denied']);
    exit;
}


if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    echo json_encode(['success' => false, 'message' => 'Invalid date format']);
    exit;
}


$result = markHabitComplete($habit_id, $date);

if ($result) {
    
    $stats = getUserStats($_SESSION['user_id']);
    
    echo json_encode([
        'success' => true, 
        'message' => 'Habit tracked successfully!',
        'stats' => $stats,
        'habit' => [
            'id' => $habit_id,
            'name' => $habit['name'],
            'streak' => calculateCurrentStreak($habit_id)
        ]
    ]);
} else {
    echo json_encode([
        'success' => false, 
        'message' => 'Habit already tracked for this date'
    ]);
}
?>