<?php
include '../config/database.php';
redirectIfNotLoggedIn();
if (!isClient()) { header('Location: ../login.php?role=client'); exit(); }

$order_id = $_POST['order_id'] ?? 0;
$method = $_POST['method'] ?? '';
$sender_name = $_POST['sender_name'] ?? '';

if (!$order_id || !$method || !$sender_name) {
    header('Location: orders.php?msg=error');
    exit();
}

// Get order details
$order = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND client_id = ?");
$order->execute([$order_id, $_SESSION['user_id']]);
$order = $order->fetch();

if (!$order) {
    header('Location: orders.php');
    exit();
}

$proof_image = null;
$status = 'pending';

// WAJIB UPLOAD BUKTI UNTUK SEMUA METODE (termasuk QRIS)
if (isset($_FILES['proof_image']) && $_FILES['proof_image']['error'] === 0) {
    $upload_dir = '../uploads/payments/';
    if (!file_exists($upload_dir)) mkdir($upload_dir, 0777, true);
    $filename = time() . '_' . basename($_FILES['proof_image']['name']);
    move_uploaded_file($_FILES['proof_image']['tmp_name'], $upload_dir . $filename);
    $proof_image = 'uploads/payments/' . $filename;
} else {
    // Jika tidak upload bukti, return error
    header("Location: orders.php?msg=upload_required");
    exit();
}

// Simpan payment
$stmt = $pdo->prepare("INSERT INTO payments (order_id, amount, method, proof_image, sender_name, status) VALUES (?, ?, ?, ?, ?, ?)");
$stmt->execute([$order_id, $order['total_price'], $method, $proof_image, $sender_name, $status]);

// Notify employees
$employees = $pdo->query("SELECT id FROM users WHERE role = 'employee'")->fetchAll();
foreach($employees as $emp) {
    addNotification($emp['id'], '💳 Pembayaran Baru', $_SESSION['user_name'] . ' melakukan pembayaran untuk order #' . $order['order_number'], 'payment', 'employee/payment_requests.php');
}

header("Location: orders.php?msg=payment_pending");
exit();
?>