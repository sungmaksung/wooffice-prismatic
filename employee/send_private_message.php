<?php
include '../config/database.php';

if(!isEmployee()) {
    echo json_encode(['success' => false, 'error' => 'Not logged in as employee']);
    exit();
}

$client_id = $_POST['client_id'] ?? 0;
$message = $_POST['message'] ?? '';
$user_id = $_SESSION['user_id'];

if(!$client_id) {
    echo json_encode(['success' => false, 'error' => 'Client ID required']);
    exit();
}

if(trim($message) == '') {
    echo json_encode(['success' => false, 'error' => 'Message cannot be empty']);
    exit();
}

$stmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, message, is_broadcast, is_read) VALUES (?, ?, ?, 0, 0)");
$result = $stmt->execute([$user_id, $client_id, $message]);

if($result) {
    // Add notification for the client
    addNotification($client_id, '💬 Balasan dari CS', substr($message, 0, 100), 'chat', 'client/chat.php');
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
?>