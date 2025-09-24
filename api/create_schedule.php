<?php
require_once '../config/config.php';
require_once '../lib/push_notifications.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Only admin or authority can create schedules
$role = $_SESSION['role'] ?? '';
if (!in_array($role, ['admin', 'authority'], true) || empty($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON body']);
    exit;
}

$area = trim($data['area'] ?? '');
$street_name = trim($data['street_name'] ?? '');
$collection_day = trim(strtolower($data['collection_day'] ?? ''));
$collection_time = trim($data['collection_time'] ?? '');
$frequency = trim(strtolower($data['frequency'] ?? 'weekly'));
$waste_type = trim(strtolower($data['waste_type'] ?? 'general'));
$assigned_collector = isset($data['assigned_collector']) && $data['assigned_collector'] !== '' ? intval($data['assigned_collector']) : null;

$valid_days = ['monday','tuesday','wednesday','thursday','friday','saturday','sunday'];
$valid_freq = ['daily','weekly','biweekly','monthly'];
$valid_waste = ['general','recyclable','organic','hazardous'];

if (!$area || !$street_name || !in_array($collection_day, $valid_days, true) || !$collection_time) {
    http_response_code(422);
    echo json_encode(['error' => 'Missing or invalid fields']);
    exit;
}

if (!in_array($frequency, $valid_freq, true)) $frequency = 'weekly';
if (!in_array($waste_type, $valid_waste, true)) $waste_type = 'general';

try {
    $stmt = $conn->prepare("INSERT INTO collection_schedules (area, street_name, collection_day, collection_time, frequency, waste_type, assigned_collector, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)");
    $created_by = $_SESSION['user_id'];
    // assigned_collector may be null
    if ($assigned_collector) {
        $stmt->bind_param('ssssssii', $area, $street_name, $collection_day, $collection_time, $frequency, $waste_type, $assigned_collector, $created_by);
    } else {
        // bind as null (use NULL for assigned_collector)
        $null = null;
        $stmt = $conn->prepare("INSERT INTO collection_schedules (area, street_name, collection_day, collection_time, frequency, waste_type, assigned_collector, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, NULL, ?, CURRENT_TIMESTAMP)");
        $stmt->bind_param('ssssssi', $area, $street_name, $collection_day, $collection_time, $frequency, $waste_type, $created_by);
    }

    if (!$stmt->execute()) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create schedule']);
        exit;
    }

    $schedule_id = $conn->insert_id;
    $stmt->close();

    // Persist an in-app notification for the area (for residents to see)
    $notif_title = 'New Collection Scheduled';
    $notif_message = sprintf('%s on %s at %s', $street_name, ucfirst($collection_day), $collection_time);

    // Insert into notifications table for area residents (we'll set user_id NULL for area-wide)
    $stmtN = $conn->prepare("INSERT INTO notifications (user_id, title, message, type, reference_type, reference_id, created_at) VALUES (NULL, ?, ?, 'info', 'schedule', ?, CURRENT_TIMESTAMP)");
    $stmtN->bind_param('ssi', $notif_title, $notif_message, $schedule_id);
    $stmtN->execute();
    $stmtN->close();

    // Queue notification job for assigned collector (if any)
    $stmtJ = $conn->prepare("INSERT INTO notification_jobs (target_type, target_value, title, message, payload, created_at) VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)");
    $payload = json_encode(['schedule_id' => $schedule_id]);
    if ($assigned_collector) {
        $t = 'user'; $v = (string)$assigned_collector;
        $stmtJ->bind_param('sssss', $t, $v, $notif_title, "You have been assigned: {$notif_message}", $payload);
        $stmtJ->execute();
    }

    // Queue an area-wide job
    $t = 'area'; $v = $area;
    $stmtJ->bind_param('sssss', $t, $v, $notif_title, "Collection scheduled for {$street_name} on " . ucfirst($collection_day) . " at {$collection_time}", $payload);
    $stmtJ->execute();
    $stmtJ->close();

    echo json_encode(['success' => true, 'id' => $schedule_id]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error: ' . $e->getMessage()]);
}

?>
