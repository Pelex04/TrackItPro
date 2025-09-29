<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

// Only require reward.php if it exists
if (file_exists(__DIR__ . '/reward.php')) {
    require_once __DIR__ . '/reward.php';
}

/**
 * Add a new habit for a user
 */
function addHabit($user_id, $name, $frequency, $goal) {
    global $pdo;
    
    // Check if created_at column exists, if not just use the basic columns
    try {
        $stmt = $pdo->prepare("INSERT INTO habits (user_id, name, frequency, goal, created_at) VALUES (?, ?, ?, ?, NOW())");
        return $stmt->execute([$user_id, $name, $frequency, $goal]);
    } catch (PDOException $e) {
        // If created_at doesn't exist, try without it
        $stmt = $pdo->prepare("INSERT INTO habits (user_id, name, frequency, goal) VALUES (?, ?, ?, ?)");
        return $stmt->execute([$user_id, $name, $frequency, $goal]);
    }
}

/**
 * Get all habits for a user with enhanced data
 */
function getHabits($user_id) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT 
            h.*,
            COUNT(DISTINCT p.date_completed) as total_completions,
            COUNT(DISTINCT CASE 
                WHEN p.date_completed >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) 
                THEN p.date_completed 
            END) as completions_this_week,
            COUNT(DISTINCT CASE 
                WHEN YEAR(p.date_completed) = YEAR(CURDATE()) 
                AND MONTH(p.date_completed) = MONTH(CURDATE())
                THEN p.date_completed 
            END) as completions_this_month,
            MIN(p.date_completed) as first_completion
        FROM habits h
        LEFT JOIN progress p ON h.habit_id = p.habit_id
        WHERE h.user_id = ?
        GROUP BY h.habit_id
        ORDER BY h.habit_id DESC
    ");
    $stmt->execute([$user_id]);
    $habits = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Enhance each habit with calculated data
    foreach ($habits as &$habit) {
        // Use first_completion as a fallback for created_at
        $startDate = isset($habit['created_at']) ? $habit['created_at'] : 
                     ($habit['first_completion'] ?: date('Y-m-d', strtotime('-30 days')));
        
        $habit['current_streak'] = calculateCurrentStreak($habit['habit_id']);
        $habit['longest_streak'] = calculateLongestStreak($habit['habit_id']);
        $habit['progress'] = calculateHabitProgress($habit['habit_id'], $habit['frequency']);
        $habit['completion_rate'] = calculateCompletionRate($habit['habit_id'], $startDate);
    }
    
    return $habits;
}

/**
 * Get a single habit by ID with full details
 */
function getHabitById($habit_id, $user_id = null) {
    global $pdo;
    $sql = "
        SELECT 
            h.*,
            COUNT(DISTINCT p.date_completed) as total_completions,
            COUNT(DISTINCT CASE 
                WHEN p.date_completed >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) 
                THEN p.date_completed 
            END) as completions_this_week,
            MIN(p.date_completed) as first_completion
        FROM habits h
        LEFT JOIN progress p ON h.habit_id = p.habit_id
        WHERE h.habit_id = ?
    ";
    
    if ($user_id !== null) {
        $sql .= " AND h.user_id = ?";
        $sql .= " GROUP BY h.habit_id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$habit_id, $user_id]);
    } else {
        $sql .= " GROUP BY h.habit_id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$habit_id]);
    }
    
    $habit = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($habit) {
        // Use first_completion as a fallback for created_at
        $startDate = isset($habit['created_at']) ? $habit['created_at'] : 
                     ($habit['first_completion'] ?: date('Y-m-d', strtotime('-30 days')));
        
        $habit['current_streak'] = calculateCurrentStreak($habit_id);
        $habit['longest_streak'] = calculateLongestStreak($habit_id);
        $habit['progress'] = calculateHabitProgress($habit_id, $habit['frequency']);
        $habit['completion_rate'] = calculateCompletionRate($habit_id, $startDate);
    }
    
    return $habit;
}

/**
 * Update an existing habit
 */
function updateHabit($habit_id, $name, $frequency, $goal) {
    global $pdo;
    
    // Check if updated_at column exists
    try {
        $stmt = $pdo->prepare("UPDATE habits SET name = ?, frequency = ?, goal = ?, updated_at = NOW() WHERE habit_id = ?");
        return $stmt->execute([$name, $frequency, $goal, $habit_id]);
    } catch (PDOException $e) {
        // If updated_at doesn't exist, try without it
        $stmt = $pdo->prepare("UPDATE habits SET name = ?, frequency = ?, goal = ? WHERE habit_id = ?");
        return $stmt->execute([$name, $frequency, $goal, $habit_id]);
    }
}

/**
 * Delete a habit and its progress
 */
function deleteHabit($habit_id) {
    global $pdo;
    try {
        $pdo->beginTransaction();
        
        // Delete progress records first
        $stmt = $pdo->prepare("DELETE FROM progress WHERE habit_id = ?");
        $stmt->execute([$habit_id]);
        
        // Delete the habit
        $stmt = $pdo->prepare("DELETE FROM habits WHERE habit_id = ?");
        $stmt->execute([$habit_id]);
        
        $pdo->commit();
        return true;
    } catch (Exception $e) {
        $pdo->rollBack();
        return false;
    }
}

/**
 * Mark a habit as complete for a specific date
 */
function markHabitComplete($habit_id, $date = null) {
    global $pdo;
    
    if ($date === null) {
        $date = date('Y-m-d');
    }
    
    // Check if already completed for this date
    if (isHabitCompletedOnDate($habit_id, $date)) {
        return false; // Already completed
    }
    
    // Try with created_at column first, fallback to without it
    try {
        $stmt = $pdo->prepare("INSERT INTO progress (habit_id, date_completed, created_at) VALUES (?, ?, NOW())");
        $success = $stmt->execute([$habit_id, $date]);
    } catch (PDOException $e) {
        // If created_at column doesn't exist, try without it
        try {
            $stmt = $pdo->prepare("INSERT INTO progress (habit_id, date_completed) VALUES (?, ?)");
            $success = $stmt->execute([$habit_id, $date]);
        } catch (PDOException $e2) {
            // Log error and return false
            error_log("Error marking habit complete: " . $e2->getMessage());
            return false;
        }
    }
    
    if ($success) {
        // Try to award points if the function exists
        if (function_exists('awardPoints')) {
            try {
                awardPoints($habit_id);
            } catch (Exception $e) {
                // If awardPoints fails, continue anyway
                error_log("Error awarding points: " . $e->getMessage());
            }
        }
    }
    
    return $success;
}

/**
 * Unmark a habit completion for a specific date
 */
function unmarkHabitComplete($habit_id, $date = null) {
    global $pdo;
    
    if ($date === null) {
        $date = date('Y-m-d');
    }
    
    $stmt = $pdo->prepare("DELETE FROM progress WHERE habit_id = ? AND date_completed = ?");
    return $stmt->execute([$habit_id, $date]);
}

/**
 * Check if a habit is completed on a specific date
 */
function isHabitCompletedOnDate($habit_id, $date) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM progress WHERE habit_id = ? AND date_completed = ?");
        $stmt->execute([$habit_id, $date]);
        return $stmt->fetchColumn() > 0;
    } catch (PDOException $e) {
        // If there's an error, assume not completed
        return false;
    }
}

/**
 * Calculate current streak for a habit
 */
function calculateCurrentStreak($habit_id) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT date_completed 
        FROM progress 
        WHERE habit_id = ? 
        ORDER BY date_completed DESC
    ");
    $stmt->execute([$habit_id]);
    $dates = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($dates)) {
        return 0;
    }
    
    $streak = 0;
    $currentDate = new DateTime();
    $lastDate = new DateTime($dates[0]);
    
    // Check if the most recent completion is today or yesterday
    $daysDiff = $currentDate->diff($lastDate)->days;
    if ($daysDiff > 1) {
        return 0; // Streak is broken
    }
    
    $streak = 1;
    for ($i = 1; $i < count($dates); $i++) {
        $prevDate = new DateTime($dates[$i - 1]);
        $currDate = new DateTime($dates[$i]);
        $diff = $prevDate->diff($currDate)->days;
        
        if ($diff == 1) {
            $streak++;
        } else {
            break;
        }
    }
    
    return $streak;
}

/**
 * Calculate longest streak for a habit
 */
function calculateLongestStreak($habit_id) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT date_completed 
        FROM progress 
        WHERE habit_id = ? 
        ORDER BY date_completed ASC
    ");
    $stmt->execute([$habit_id]);
    $dates = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($dates)) {
        return 0;
    }
    
    $longestStreak = 1;
    $currentStreak = 1;
    
    for ($i = 1; $i < count($dates); $i++) {
        $prevDate = new DateTime($dates[$i - 1]);
        $currDate = new DateTime($dates[$i]);
        $diff = $currDate->diff($prevDate)->days;
        
        if ($diff == 1) {
            $currentStreak++;
            $longestStreak = max($longestStreak, $currentStreak);
        } else {
            $currentStreak = 1;
        }
    }
    
    return $longestStreak;
}

/**
 * Calculate habit progress percentage based on frequency
 */
function calculateHabitProgress($habit_id, $frequency) {
    global $pdo;
    
    // Get completions in the last 7 days
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM progress 
        WHERE habit_id = ? 
        AND date_completed >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    ");
    $stmt->execute([$habit_id]);
    $completions = $stmt->fetchColumn();
    
    // Determine expected completions based on frequency
    $expected = 7; // Default to daily
    $frequency = strtolower($frequency);
    
    if (strpos($frequency, 'weekly') !== false || strpos($frequency, 'once') !== false) {
        $expected = 1;
    } elseif (strpos($frequency, 'twice') !== false || strpos($frequency, '2') !== false) {
        $expected = 2;
    } elseif (strpos($frequency, 'thrice') !== false || strpos($frequency, '3') !== false) {
        $expected = 3;
    } elseif (strpos($frequency, '4') !== false || strpos($frequency, 'four') !== false) {
        $expected = 4;
    } elseif (strpos($frequency, '5') !== false || strpos($frequency, 'five') !== false) {
        $expected = 5;
    } elseif (strpos($frequency, '6') !== false || strpos($frequency, 'six') !== false) {
        $expected = 6;
    }
    
    $progress = $expected > 0 ? round(($completions / $expected) * 100) : 0;
    return min($progress, 100); // Cap at 100%
}

/**
 * Calculate overall completion rate since habit creation
 */
function calculateCompletionRate($habit_id, $created_at) {
    global $pdo;
    
    $createdDate = new DateTime($created_at);
    $currentDate = new DateTime();
    $daysSinceCreation = $currentDate->diff($createdDate)->days + 1;
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM progress WHERE habit_id = ?");
    $stmt->execute([$habit_id]);
    $totalCompletions = $stmt->fetchColumn();
    
    $rate = $daysSinceCreation > 0 ? round(($totalCompletions / $daysSinceCreation) * 100) : 0;
    return min($rate, 100);
}

/**
 * Get weekly success rate for a user
 */
function getWeeklySuccessRate($user_id) {
    global $pdo;
    
    $habits = getHabits($user_id);
    
    if (empty($habits)) {
        return 0;
    }
    
    $totalCompletions = 0;
    $totalExpected = 0;
    
    foreach ($habits as $habit) {
        $totalCompletions += $habit['completions_this_week'];
        
        // Calculate expected completions
        $frequency = strtolower($habit['frequency']);
        $expected = 7; // Default to daily
        
        if (strpos($frequency, 'weekly') !== false || strpos($frequency, 'once') !== false) {
            $expected = 1;
        } elseif (strpos($frequency, 'twice') !== false || strpos($frequency, '2') !== false) {
            $expected = 2;
        } elseif (strpos($frequency, 'thrice') !== false || strpos($frequency, '3') !== false) {
            $expected = 3;
        } elseif (strpos($frequency, '4') !== false) {
            $expected = 4;
        } elseif (strpos($frequency, '5') !== false) {
            $expected = 5;
        } elseif (strpos($frequency, '6') !== false) {
            $expected = 6;
        }
        
        $totalExpected += $expected;
    }
    
    return $totalExpected > 0 ? round(($totalCompletions / $totalExpected) * 100) : 0;
}

/**
 * Get user statistics
 */
function getUserStats($user_id) {
    $habits = getHabits($user_id);
    
    $stats = [
        'active_habits' => count($habits),
        'weekly_success' => getWeeklySuccessRate($user_id),
        'current_streak' => 0,
        'longest_streak' => 0,
        'goals_achieved' => 0,
        'total_completions' => 0
    ];
    
    foreach ($habits as $habit) {
        $stats['current_streak'] = max($stats['current_streak'], $habit['current_streak']);
        $stats['longest_streak'] = max($stats['longest_streak'], $habit['longest_streak']);
        $stats['total_completions'] += $habit['total_completions'];
        
        // Count as goal achieved if completion rate >= 80% and has at least 30 completions
        if ($habit['completion_rate'] >= 80 && $habit['total_completions'] >= 30) {
            $stats['goals_achieved']++;
        }
    }
    
    return $stats;
}

/**
 * Get habit completion calendar data for a specific month
 */
function getHabitCalendarData($habit_id, $year, $month) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT date_completed 
        FROM progress 
        WHERE habit_id = ? 
        AND YEAR(date_completed) = ? 
        AND MONTH(date_completed) = ?
        ORDER BY date_completed ASC
    ");
    $stmt->execute([$habit_id, $year, $month]);
    
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

/**
 * Get progress trend data for charts
 */
function getProgressTrendData($user_id, $period = 'year') {
    global $pdo;
    
    $data = [];
    
    switch ($period) {
        case 'week':
            // Last 7 days
            for ($i = 6; $i >= 0; $i--) {
                $date = date('Y-m-d', strtotime("-$i days"));
                $dayName = date('D', strtotime($date));
                
                $stmt = $pdo->prepare("
                    SELECT COUNT(DISTINCT p.progress_id) as completions,
                           COUNT(DISTINCT h.habit_id) as total_habits
                    FROM habits h
                    LEFT JOIN progress p ON h.habit_id = p.habit_id AND p.date_completed = ?
                    WHERE h.user_id = ?
                ");
                $stmt->execute([$date, $user_id]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $rate = $result['total_habits'] > 0 ? 
                    round(($result['completions'] / $result['total_habits']) * 100) : 0;
                
                $data['labels'][] = $dayName;
                $data['values'][] = $rate;
            }
            break;
            
        case 'month':
            // Last 4 weeks
            for ($i = 3; $i >= 0; $i--) {
                $weekStart = date('Y-m-d', strtotime("-" . ($i * 7) . " days"));
                $weekEnd = date('Y-m-d', strtotime("-" . ($i * 7 - 6) . " days"));
                
                $stmt = $pdo->prepare("
                    SELECT COUNT(DISTINCT p.progress_id) as completions,
                           COUNT(DISTINCT h.habit_id) * 7 as expected
                    FROM habits h
                    LEFT JOIN progress p ON h.habit_id = p.habit_id 
                        AND p.date_completed BETWEEN ? AND ?
                    WHERE h.user_id = ?
                ");
                $stmt->execute([$weekEnd, $weekStart, $user_id]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $rate = $result['expected'] > 0 ? 
                    round(($result['completions'] / $result['expected']) * 100) : 0;
                
                $data['labels'][] = 'Week ' . (4 - $i);
                $data['values'][] = min($rate, 100);
            }
            break;
            
        case 'year':
        default:
            // Last 12 months
            for ($i = 11; $i >= 0; $i--) {
                $monthDate = date('Y-m', strtotime("-$i months"));
                $monthName = date('M', strtotime($monthDate . '-01'));
                
                $stmt = $pdo->prepare("
                    SELECT COUNT(DISTINCT p.progress_id) as completions,
                           COUNT(DISTINCT h.habit_id) * DAY(LAST_DAY(?)) as expected
                    FROM habits h
                    LEFT JOIN progress p ON h.habit_id = p.habit_id 
                        AND DATE_FORMAT(p.date_completed, '%Y-%m') = ?
                    WHERE h.user_id = ?
                ");
                $stmt->execute([$monthDate . '-01', $monthDate, $user_id]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $rate = $result['expected'] > 0 ? 
                    round(($result['completions'] / $result['expected']) * 100) : 0;
                
                $data['labels'][] = $monthName;
                $data['values'][] = min($rate, 100);
            }
            break;
    }
    
    return $data;
}

/**
 * Get recent activity for a user
 */
function getRecentActivity($user_id, $limit = 10) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT h.name, p.date_completed, p.created_at
        FROM progress p
        JOIN habits h ON p.habit_id = h.habit_id
        WHERE h.user_id = ?
        ORDER BY p.created_at DESC
        LIMIT ?
    ");
    $stmt->execute([$user_id, $limit]);
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get habits that need attention (low completion rate)
 */
function getHabitsNeedingAttention($user_id) {
    $habits = getHabits($user_id);
    
    $needsAttention = [];
    foreach ($habits as $habit) {
        if ($habit['progress'] < 70 && $habit['total_completions'] > 0) {
            $needsAttention[] = $habit;
        }
    }
    
    return $needsAttention;
}

/**
 * Check if user has completed all habits today
 */
function hasCompletedAllHabitsToday($user_id) {
    global $pdo;
    
    $today = date('Y-m-d');
    
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total_habits,
               COUNT(DISTINCT p.habit_id) as completed_today
        FROM habits h
        LEFT JOIN progress p ON h.habit_id = p.habit_id AND p.date_completed = ?
        WHERE h.user_id = ?
    ");
    $stmt->execute([$today, $user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $result['total_habits'] > 0 && 
           $result['total_habits'] == $result['completed_today'];
}
?>