<?php
require_once '../config/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// require_login(); // Uncomment in production

$user_role = $_SESSION['role'] ?? '';
$collector_id = $_SESSION['user_id'] ?? null;

if ($user_role !== 'collector' || !$collector_id) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

$input = $_POST;
$task_id = isset($input['task_id']) ? intval($input['task_id']) : 0;
$new_status = trim($input['status'] ?? ''); // expected: in_progress|completed|pending
$valid_statuses = ['pending','in_progress','completed'];

if ($task_id <= 0 || !in_array($new_status, $valid_statuses, true)) {
    http_response_code(422);
    echo json_encode(['error' => 'Invalid task_id or status']);
    exit;
}

try {
    // Verify this task belongs to the collector
    $stmt = $conn->prepare("SELECT id FROM collection_schedules WHERE id = ? AND assigned_collector = ?");
    $stmt->bind_param('ii', $task_id, $collector_id);
    $stmt->execute();
    $exists = $stmt->get_result()->fetch_assoc();
    if (!$exists) {
        http_response_code(404);
        echo json_encode(['error' => 'Task not found']);
        exit;
    }

    // Update schedule status
    $stmt = $conn->prepare("UPDATE collection_schedules SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
    $stmt->bind_param('si', $new_status, $task_id);
    $stmt->execute();

    // Record history entry when completed
    if ($new_status === 'completed') {
        $stmt = $conn->prepare("INSERT INTO collection_history (collector_id, schedule_id, status, collection_date) VALUES (?, ?, 'completed', NOW())");
        $stmt->bind_param('ii', $collector_id, $task_id);
        $stmt->execute();
    }

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error: ' . $e->getMessage()]);
}
?>


