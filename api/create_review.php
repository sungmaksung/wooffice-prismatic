<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse('error', 'Method not allowed', null, 405);
}

$payload = authenticate();
$user_id = $payload['user_id'];

$input = json_decode(file_get_contents('php://input'), true);

$order_id = $input['order_id'] ?? null;
$rating = $input['rating'] ?? null;
$review = $input['review'] ?? '';

if (!$order_id || !$rating || empty($review)) {
    sendResponse('error', 'Order ID, rating, and review required', null, 400);
}

if ($rating < 1 || $rating > 5) {
    sendResponse('error', 'Rating must be between 1-5', null, 400);
}

$stmt = $pdo->prepare("SELECT id, package_id, status, can_review FROM orders WHERE id = ? AND client_id = ?");
$stmt->execute([$order_id, $user_id]);
$order = $stmt->fetch();

if (!$order) {
    sendResponse('error', 'Order not found', null, 404);
}

if ($order['status'] !== 'approved') {
    sendResponse('error', 'You can only review after wedding is completed', null, 400);
}

if ($order['can_review'] == 1) {
    sendResponse('error', 'You have already reviewed this order', null, 400);
}

$stmt = $pdo->prepare("INSERT INTO reviews (order_id, client_id, package_id, rating, review, status, created_at) VALUES (?, ?, ?, ?, ?, 'pending', NOW())");
$stmt->execute([$order_id, $user_id, $order['package_id'], $rating, $review]);

$stmt = $pdo->prepare("UPDATE orders SET can_review = 1, reviewed = 1 WHERE id = ?");
$stmt->execute([$order_id]);

$stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type, link) SELECT id, '⭐ Ulasan Baru', CONCAT('Client memberi ulasan bintang ', ?), 'review', 'employee/reviews.php' FROM users WHERE role = 'employee'");
$stmt->execute([$rating]);

sendResponse('success', 'Review submitted successfully', [
    'rating' => $rating,
    'status' => 'pending'
]);
?>