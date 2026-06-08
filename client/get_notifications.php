<?php
include '../config/database.php';
if(!isClient()) exit(json_encode([]));

$stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? AND is_read = 0 ORDER BY created_at DESC");
$stmt->execute([$_SESSION['user_id']]);
$notifications = $stmt->fetchAll();

echo json_encode(['count' => count($notifications), 'notifications' => $notifications]);
?>