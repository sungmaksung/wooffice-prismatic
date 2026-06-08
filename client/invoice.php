<?php
include '../config/database.php';
redirectIfNotLoggedIn();
if (!isClient()) { header('Location: ../login.php?role=client'); exit(); }

$order_id = $_GET['id'] ?? 0;

// Get order data dengan JOIN ke tables terkait
$order = $pdo->prepare("
    SELECT o.*, p.name as package_name, p.description as package_desc, 
           u.full_name as client_name, u.email, u.phone, u.couple_name
    FROM orders o 
    JOIN packages p ON o.package_id = p.id 
    JOIN users u ON o.client_id = u.id 
    WHERE o.id = ? AND o.client_id = ?
");
$order->execute([$order_id, $_SESSION['user_id']]);
$order = $order->fetch();

if (!$order) {
    header('Location: orders.php');
    exit();
}

// Get payment info (verified payment)
$payment = $pdo->prepare("SELECT * FROM payments WHERE order_id = ? AND status = 'verified' LIMIT 1");
$payment->execute([$order_id]);
$payment = $payment->fetch();

// Hitung hari menuju pernikahan
$days_remaining = ceil((strtotime($order['wedding_date']) - time()) / 86400);
$wedding_status = $days_remaining > 0 ? "H-$days_remaining" : ($days_remaining == 0 ? "Hari H!" : "Sudah lewat " . abs($days_remaining) . " hari");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice - Prismatic Organizer</title>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,300;1,400;1,600&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        * { font-family: 'DM Sans', sans-serif; }
        .font-serif { font-family: 'Cormorant Garamond', serif; }
        .main-content { margin-left: 280px; padding: 32px; min-height: 100vh; background: #0A0A0A; }
        .invoice-card { background: #1A1A1A; border: 1px solid #2A2A2A; }
        @media (max-width: 768px) { .main-content { margin-left: 0; padding: 80px 20px 32px; } }
        @media print {
            .sidebar-fixed, .no-print, .mobile-menu-toggle { display: none; }
            .main-content { margin: 0; padding: 20px; background: white; }
            .invoice-card { box-shadow: none; border: 1px solid #ddd; background: white; color: black; }
            .invoice-card .text-gray-400, .invoice-card .text-gray-500 { color: #666 !important; }
            .invoice-card .text-white { color: black !important; }
            .invoice-card .text-yellow-400 { color: #DAA520 !important; }
            .invoice-card .bg-gradient-to-r { background: none !important; color: black !important; }
        }
    </style>
</head>
<body class="bg-[#0A0A0A]">

<?php include 'includes/sidebar.php'; ?>

<div class="main-content">
    <div class="max-w-4xl mx-auto">
        <div class="flex justify-between items-center mb-6 no-print">
            <a href="orders.php" class="text-[#FFD700] hover:underline flex items-center gap-2">
                <span>←</span> Kembali ke Pesanan Saya
            </a>
            <button onclick="window.print()" class="bg-gradient-to-r from-[#FFD700] to-[#DAA520] text-[#0A0A0A] font-bold px-5 py-2 rounded-full hover:shadow-lg transition flex items-center gap-2">
                🖨️ Cetak Invoice
            </button>
        </div>
        
        <!-- Invoice Card -->
        <div class="invoice-card rounded-2xl overflow-hidden shadow-2xl">
            <!-- Header -->
            <div class="bg-gradient-to-r from-[#FFD700] to-[#DAA520] p-6 text-[#0A0A0A] text-center">
                <div class="flex justify-center mb-3">
                    <span class="text-5xl">💕</span>
                </div>
                <h1 class="font-serif text-3xl font-bold">Prismatic Organizer</h1>
                <p class="text-[#0A0A0A]/70 text-sm mt-1">Wedding & Event Organizer</p>
                <p class="text-[#0A0A0A]/60 text-xs mt-2">Cimasuk Residence, Blok G-5, Daerah Suci, Karangpawitan, Garut | +62 822-1907-4421 | hello@prismatic-organizer.com</p>
            </div>
            
            <!-- Invoice Info -->
            <div class="p-6 border-b border-[#2A2A2A] flex justify-between flex-wrap gap-4">
                <div>
                    <p class="text-sm text-gray-400">INVOICE NUMBER</p>
                    <p class="font-mono font-bold text-white text-lg"><?= $order['order_number'] ?></p>
                </div>
                <div>
                    <p class="text-sm text-gray-400">INVOICE DATE</p>
                    <p class="font-medium text-white"><?= date('d F Y', strtotime($order['created_at'])) ?></p>
                </div>
                <div>
                    <p class="text-sm text-gray-400">DUE DATE</p>
                    <p class="font-medium text-white"><?= date('d F Y', strtotime($order['wedding_date'] . ' -14 days')) ?></p>
                </div>
                <div>
                    <p class="text-sm text-gray-400">WEDDING DATE</p>
                    <p class="font-medium text-white"><?= date('d F Y', strtotime($order['wedding_date'])) ?> 
                        <span class="text-xs text-gray-400 ml-1">(<?= $wedding_status ?>)</span>
                    </p>
                </div>
            </div>
            
            <!-- Bill To & Event Details -->
            <div class="p-6 border-b border-[#2A2A2A] grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <h3 class="font-semibold text-white mb-3 flex items-center gap-2">
                        <span class="w-1 h-4 bg-[#FFD700] rounded-full"></span> Bill To:
                    </h3>
                    <p class="font-medium text-white"><?= htmlspecialchars($_SESSION['user_name']) ?></p>
                    <p class="text-gray-400 text-sm"><?= $_SESSION['user_email'] ?></p>
                    <p class="text-gray-400 text-sm"><?= $_SESSION['user_phone'] ?></p>
                    <?php if($order['couple_name'] && $order['couple_name'] != $_SESSION['user_name']): ?>
                    <p class="text-[#FFD700] text-sm mt-2">💕 <?= htmlspecialchars($order['couple_name']) ?></p>
                    <?php endif; ?>
                </div>
                <div>
                    <h3 class="font-semibold text-white mb-3 flex items-center gap-2">
                        <span class="w-1 h-4 bg-[#FFD700] rounded-full"></span> Event Details:
                    </h3>
                    <p class="text-gray-300"><span class="text-gray-400">Venue:</span> <?= htmlspecialchars($order['venue']) ?></p>
                    <p class="text-gray-300"><span class="text-gray-400">Guests:</span> <?= number_format($order['guest_count']) ?> pax</p>
                    <?php if($order['notes']): ?>
                    <p class="text-gray-300 mt-2"><span class="text-gray-400">Notes:</span> <?= htmlspecialchars($order['notes']) ?></p>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Items Table -->
            <div class="p-6">
                <h3 class="font-semibold text-white mb-4 flex items-center gap-2">
                    <span class="w-1 h-4 bg-[#FFD700] rounded-full"></span> Order Summary:
                </h3>
                <table class="w-full">
                    <thead class="border-b border-[#2A2A2A]">
                        <tr class="text-left text-gray-400 text-sm">
                            <th class="pb-3">Description</th>
                            <th class="pb-3 text-right">Qty</th>
                            <th class="pb-3 text-right">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="border-b border-[#2A2A2A]">
                            <td class="py-4">
                                <p class="font-semibold text-white"><?= $order['package_name'] ?> Package</p>
                                <p class="text-sm text-gray-400 mt-1">Wedding Date: <?= date('d F Y', strtotime($order['wedding_date'])) ?></p>
                                <p class="text-sm text-gray-400">Venue: <?= htmlspecialchars($order['venue']) ?></p>
                                <p class="text-sm text-gray-400">Guests: <?= number_format($order['guest_count']) ?> pax</p>
                                <?php if($order['custom_options']): ?>
                                <p class="text-sm text-[#FFD700] mt-1">✨ Custom Package</p>
                                <?php endif; ?>
                            </td>
                            <td class="py-4 text-right text-white">1</td>
                            <td class="py-4 text-right text-[#FFD700] font-semibold">Rp <?= number_format($order['total_price'], 0, ',', '.') ?></td>
                        </tr>
                        <?php if($payment): ?>
                        <tr class="border-b border-[#2A2A2A]">
                            <td class="py-3 text-green-400">✓ Payment Received (<?= strtoupper($payment['method']) ?>)</td>
                            <td class="py-3 text-right"></td>
                            <td class="py-3 text-right text-green-400">- Rp <?= number_format($payment['amount'], 0, ',', '.') ?></td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                    <tfoot class="border-t border-[#2A2A2A]">
                        <tr class="font-bold">
                            <td class="pt-4 text-white text-right" colspan="2">Total:</td>
                            <td class="pt-4 text-right text-[#FFD700] text-xl">Rp <?= number_format($order['total_price'] - ($payment['amount'] ?? 0), 0, ',', '.') ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            
            <!-- Payment Status -->
            <div class="p-6 bg-[#0F172A] border-t border-[#2A2A2A] flex justify-between items-center flex-wrap gap-4">
                <div>
                    <p class="text-sm text-gray-400">Payment Status</p>
                    <span class="inline-block px-3 py-1 rounded-full text-sm font-medium <?= $order['payment_status'] == 'paid' ? 'bg-green-500/20 text-green-400 border border-green-500/30' : 'bg-yellow-500/20 text-yellow-400 border border-yellow-500/30' ?>">
                        <?= $order['payment_status'] == 'paid' ? '✓ PAID' : '⏳ ' . ucfirst($order['payment_status']) ?>
                    </span>
                </div>
                <div class="text-right">
                    <p class="text-xs text-gray-400">Thank you for choosing Prismatic Organizer!</p>
                    <p class="text-xs text-[#FFD700] mt-1">💕 Every love story deserves a beautiful wedding</p>
                    <p class="text-xs text-gray-500 mt-2">Truly Fantastic</p>
                </div>
            </div>
        </div>
        
        <!-- Payment Proof (if any) -->
        <?php if($payment && $payment['proof_image']): ?>
        <div class="mt-6 bg-[#1A1A1A] rounded-2xl p-5 border border-[#2A2A2A] no-print">
            <h3 class="font-semibold text-white mb-3 flex items-center gap-2">
                <span class="w-1 h-4 bg-[#FFD700] rounded-full"></span> 📎 Bukti Pembayaran:
            </h3>
            <div class="flex items-center gap-4 flex-wrap">
                <img src="../<?= $payment['proof_image'] ?>" class="max-h-32 rounded-lg border border-[#2A2A2A] cursor-pointer" onclick="window.open(this.src, '_blank')">
                <div>
                    <p class="text-gray-400 text-sm">Transfer via: <span class="text-white font-medium"><?= strtoupper($payment['method']) ?></span></p>
                    <p class="text-gray-400 text-sm">Pengirim: <span class="text-white"><?= htmlspecialchars($payment['sender_name']) ?></span></p>
                    <p class="text-gray-400 text-sm">Tanggal: <?= date('d F Y H:i', strtotime($payment['created_at'])) ?></p>
                    <p class="text-gray-400 text-sm">Status: 
                        <span class="<?= $payment['status'] == 'verified' ? 'text-green-400' : 'text-yellow-400' ?>">
                            <?= $payment['status'] == 'verified' ? '✓ Diverifikasi' : '⏳ Menunggu Verifikasi' ?>
                        </span>
                    </p>
                    <?php if($payment['verified_at']): ?>
                    <p class="text-gray-400 text-sm">Diverifikasi pada: <?= date('d F Y H:i', strtotime($payment['verified_at'])) ?></p>
                    <?php endif; ?>
                    <button onclick="window.open('../<?= $payment['proof_image'] ?>', '_blank')" class="mt-2 text-[#FFD700] text-sm hover:text-[#DAA520]">🔍 Lihat Fullscreen</button>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="text-center mt-6 no-print">
            <a href="orders.php" class="text-gray-400 text-sm hover:text-[#FFD700] transition">← Kembali ke Pesanan Saya</a>
        </div>
    </div>
</div>

<script>
    function toggleMobileSidebar() {
        document.querySelector('.sidebar-fixed').classList.toggle('mobile-open');
    }
</script>
</body>
</html>