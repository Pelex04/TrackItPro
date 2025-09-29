<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/habit.php';

// Set JSON header
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Get parameters
$habit_id = isset($_POST['habit_id']) ? intval($_POST['habit_id']) : 0;
$date = isset($_POST['date']) ? $_POST['date'] : date('Y-m-d');

// Validate habit_id
if ($habit_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid habit ID']);
    exit;
}

// Verify that the habit belongs to the logged-in user
$habit = getHabitById($habit_id, $_SESSION['user_id']);
if (!$habit) {
    echo json_encode(['success' => false, 'message' => 'Habit not found or access denied']);
    exit;
}

// Validate date format
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    echo json_encode(['success' => false, 'message' => 'Invalid date format']);
    exit;
}

// Try to mark the habit as complete
$result = markHabitComplete($habit_id, $date);

if ($result) {
    // Get updated stats
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