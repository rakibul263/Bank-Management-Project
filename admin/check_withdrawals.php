<?php
require_once '../config.php';
require_once '../includes/functions.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    exit('Unauthorized');
}

// Get notification for new withdrawal requests for this admin
$stmt = $conn->prepare("
    SELECT COUNT(*) as count, MAX(created_at) as latest 
    FROM withdrawal_requests 
    WHERE admin_id = ? AND status = 'pending'
");
$stmt->execute([$_SESSION['admin_id']]);
$notification = $stmt->fetch();
$pending_requests_count = $notification['count'];
$latest_request_time = $notification['latest'] ? strtotime($notification['latest']) : 0;
$has_new_requests = $pending_requests_count > 0 && (time() - $latest_request_time < 86400); // 86400 seconds = 24 hours

// Return JSON response
header('Content-Type: application/json');
echo json_encode([
    'count' => $pending_requests_count,
    'has_new' => $has_new_requests,
    'latest_time' => $latest_request_time
]); 