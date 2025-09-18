<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/vendor/autoload.php';

if (!is_logged_in()) {
    http_response_code(403);
    exit;
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $subscription = $input['subscription'] ?? null;
    $user_id = (int)($_SESSION['user_id'] ?? 0);
    
    if (!$subscription || !$user_id) {
        echo json_encode(['success' => false, 'error' => 'Invalid subscription data']);
        exit;
    }
    
    // Store or update push subscription
    $stmt = $conn->prepare("
        INSERT INTO push_subscriptions (user_id, endpoint, p256dh, auth) 
        VALUES (?, ?, ?, ?) 
        ON DUPLICATE KEY UPDATE 
        endpoint = VALUES(endpoint), 
        p256dh = VALUES(p256dh), 
        auth = VALUES(auth),
        updated_at = CURRENT_TIMESTAMP
    ");
    
    $stmt->bind_param('isss', 
        $user_id, 
        $subscription['endpoint'], 
        $subscription['keys']['p256dh'], 
        $subscription['keys']['auth']
    );
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Push subscription saved']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to save subscription']);
    }
    
    $stmt->close();
    
} elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $user_id = (int)($_SESSION['user_id'] ?? 0);
    
    // Remove push subscription
    $stmt = $conn->prepare("DELETE FROM push_subscriptions WHERE user_id = ?");
    $stmt->bind_param('i', $user_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Push subscription removed']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to remove subscription']);
    }
    
    $stmt->close();
    
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
}
