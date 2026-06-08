<?php
include '../config/database.php';
if (!isClient()) exit();

$order_id = $_GET['id'] ?? 0;

$order = $pdo->prepare("
    SELECT o.*, p.name as package_name, p.description as package_desc 
    FROM orders o 
    JOIN packages p ON o.package_id = p.id 
    WHERE o.id = ? AND o.client_id = ?
");
$order->execute([$order_id, $_SESSION['user_id']]);
$order = $order->fetch();

if (!$order) {
    echo '<div class="text-center text-red-500">Order tidak ditemukan</div>';
    exit();
}

// CEK apakah payment sudah diverifikasi (status = 'verified')
$verified_payment = $pdo->prepare("SELECT * FROM payments WHERE order_id = ? AND status = 'verified' LIMIT 1");
$verified_payment->execute([$order_id]);
$verified_payment = $verified_payment->fetch();

// Jika belum diverifikasi, tampilkan pesan error
if (!$verified_payment) {
    echo '
    <div class="text-center py-8">
        <div class="text-5xl mb-4">📄</div>
        <h3 class="font-serif text-xl font-bold text-[#1A1215] mb-2">Invoice Belum Tersedia</h3>
        <p class="text-[#6B5B62]">Invoice hanya tersedia setelah pembayaran Anda <span class="font-semibold text-[#C9556A]">diverifikasi dan lunas</span>.</p>
        <p class="text-sm text-[#C9A96E] mt-2">Silakan tunggu konfirmasi dari admin atau hubungi CS.</p>
    </div>';
    exit();
}

$user = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$user->execute([$_SESSION['user_id']]);
$user = $user->fetch();

$payment = $verified_payment;
?>

<div class="invoice-content">
    <div class="text-center mb-6 pb-4 border-b">
        <div class="text-5xl mb-2">💕</div>
        <h2 class="font-serif text-3xl font-bold text-[#1A1215]">WO Office</h2>
        <p class="text-sm text-[#6B5B62]">Wedding Organizer Premium</p>
        <p class="text-xs text-[#C9A96E] mt-1">Jl. Wedding Bliss No. 123, Bandung</p>
    </div>
    
    <div class="flex justify-between mb-6 pb-3 border-b">
        <div>
            <p class="text-xs text-[#6B5B62]">INVOICE NUMBER</p>
            <p class="font-mono font-bold text-[#1A1215]"><?= $order['order_number'] ?></p>
        </div>
        <div>
            <p class="text-xs text-[#6B5B62]">INVOICE DATE</p>
            <p class="font-medium text-[#1A1215]"><?= date('d F Y', strtotime($order['created_at'])) ?></p>
        </div>
        <div>
            <p class="text-xs text-[#6B5B62]">PAYMENT DATE</p>
            <p class="font-medium text-[#1A1215]"><?= date('d F Y', strtotime($payment['verified_at'])) ?></p>
        </div>
    </div>
    
    <div class="mb-6">
        <p class="font-semibold text-[#1A1215] mb-1">Bill To:</p>
        <p class="font-medium"><?= htmlspecialchars($user['full_name']) ?></p>
        <p class="text-sm text-[#6B5B62]"><?= $user['email'] ?></p>
        <p class="text-sm text-[#6B5B62]"><?= $user['phone'] ?></p>
        <?php if($user['couple_name']): ?>
        <p class="text-sm text-[#C9556A] mt-1">💕 <?= htmlspecialchars($user['couple_name']) ?></p>
        <?php endif; ?>
    </div>
    
    <table class="w-full mb-6">
        <thead class="border-y border-[#F2C4CE]/30">
            <tr class="text-left">
                <th class="py-2 text-[#1A1215]">Description</th>
                <th class="py-2 text-right text-[#1A1215]">Amount</th>
             </table>
        </thead>
        <tbody>
            <tr>
                <td class="py-3">
                    <p class="font-semibold text-[#1A1215]"><?= $order['package_name'] ?> Package</p>
                    <p class="text-sm text-[#6B5B62]">Wedding Date: <?= date('d F Y', strtotime($order['wedding_date'])) ?></p>
                    <p class="text-sm text-[#6B5B62]">Venue: <?= htmlspecialchars($order['venue']) ?></p>
                    <?php if($order['guest_count']): ?>
                    <p class="text-sm text-[#6B5B62]">Guests: <?= $order['guest_count'] ?> pax</p>
                    <?php endif; ?>
                </td>
                <td class="py-3 text-right font-bold text-[#C9556A]">Rp <?= number_format($order['total_price'], 0, ',', '.') ?></td>
             </tr>
            <tr class="border-t border-[#F2C4CE]/30">
                <td class="py-2 text-green-600">✓ Payment Received (<?= strtoupper($payment['method']) ?>)</td>
                <td class="py-2 text-right text-green-600">- Rp <?= number_format($payment['amount'], 0, ',', '.') ?></td>
             </tr>
            <tr class="border-t border-[#F2C4CE]/30 font-bold">
                <td class="py-2">Balance Due</td>
                <td class="py-2 text-right text-[#C9556A]">Rp <?= number_format($order['total_price'] - $payment['amount'], 0, ',', '.') ?></td>
              </tr>
        </tbody>
    </table>
    
    <div class="text-center text-sm text-[#6B5B62] pt-4 border-t">
        <p class="font-serif italic">"Every love story deserves a beautiful wedding"</p>
        <p class="text-xs mt-2">💕 Thank you for choosing WO Office</p>
    </div>
</div>

<style>
    .invoice-content {
        font-family: 'DM Sans', sans-serif;
        color: #1A1215;
    }
    .border-b { border-bottom: 1px solid #F2C4CE; }
    .border-t { border-top: 1px solid #F2C4CE; }
</style>