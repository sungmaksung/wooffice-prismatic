<?php
include '../config/database.php';
redirectIfNotLoggedIn();
if (!isClient()) { header('Location: ../login.php?role=client'); exit(); }

$user_id = $_SESSION['user_id'];

// Get all orders
$orders = $pdo->prepare("
    SELECT o.*, p.name as package_name, p.description as package_desc 
    FROM orders o 
    JOIN packages p ON o.package_id = p.id 
    WHERE o.client_id = ? 
    ORDER BY o.created_at DESC
");
$orders->execute([$user_id]);

// Get success message
$msg = $_GET['msg'] ?? '';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pesanan Saya - Prismatic Organizer</title>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,300;1,400;1,600&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        * { font-family: 'DM Sans', sans-serif; }
        .font-serif { font-family: 'Cormorant Garamond', serif; }
        .main-content { margin-left: 280px; padding: 32px; min-height: 100vh; background: #0A0A0A; }
        .order-card { background: #1A1A1A; border: 1px solid #2A2A2A; transition: all 0.3s ease; }
        .order-card:hover { transform: translateY(-2px); border-color: rgba(255,215,0,0.3); box-shadow: 0 8px 24px rgba(0,0,0,0.3); }
        .modal { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.85); backdrop-filter: blur(12px); z-index: 1000; align-items: center; justify-content: center; }
        .modal.active { display: flex; }
        @media (max-width: 768px) { .main-content { margin-left: 0; padding: 80px 20px 32px; } }
    </style>
</head>
<body class="bg-[#0A0A0A]">

<?php include 'includes/sidebar.php'; ?>

<div class="main-content">
    <!-- Header -->
    <div class="mb-8">
        <h1 class="font-serif text-3xl font-semibold text-[#FFD700]">📋 Pesanan Saya</h1>
        <p class="text-[#9CA3AF] mt-1">Lihat riwayat pesanan dan status pembayaran</p>
    </div>
    
    <?php if($msg == 'payment_success'): ?>
    <div class="mb-6 bg-green-900 border border-green-700 text-green-300 px-4 py-3 rounded-xl flex justify-between items-center">
        <span>✅ Pembayaran berhasil! Admin akan segera memverifikasi pembayaran Anda.</span>
        <button onclick="this.parentElement.style.display='none'" class="text-green-300">✕</button>
    </div>
    <?php endif; ?>
    
    <?php if($msg == 'payment_pending'): ?>
    <div class="mb-6 bg-yellow-900 border border-yellow-700 text-yellow-300 px-4 py-3 rounded-xl flex justify-between items-center">
        <span>⏳ Bukti pembayaran telah diupload! Menunggu verifikasi dari admin.</span>
        <button onclick="this.parentElement.style.display='none'" class="text-yellow-300">✕</button>
    </div>
    <?php endif; ?>
    
    <?php if($msg == 'order_success'): ?>
    <div class="mb-6 bg-green-900 border border-green-700 text-green-300 px-4 py-3 rounded-xl flex justify-between items-center">
        <span>✅ Pesanan berhasil dibuat! Silakan lanjutkan ke pembayaran.</span>
        <button onclick="this.parentElement.style.display='none'" class="text-green-300">✕</button>
    </div>
    <?php endif; ?>
    
    <?php if($orders->rowCount() == 0): ?>
        <!-- Empty State -->
        <div class="bg-[#1A1A1A] rounded-2xl p-12 text-center border border-[#2A2A2A]">
            <span class="text-6xl mb-4 block">🎁</span>
            <h3 class="text-xl font-serif mb-2 text-white">Belum ada pesanan</h3>
            <p class="text-[#9CA3AF] mb-6">Yuk, mulai rencanakan pernikahan impianmu!</p>
            <a href="packages.php" class="bg-gradient-to-r from-[#FFD700] to-[#DAA520] text-[#0A0A0A] font-bold px-6 py-3 rounded-full inline-block hover:shadow-lg transition">
                Lihat Paket Wedding →
            </a>
        </div>
    <?php else: ?>
        <!-- Orders List -->
        <div class="space-y-6">
            <?php while($order = $orders->fetch()): 
                // Get verified payment (LUNAS)
                $verified_payment = $pdo->prepare("SELECT * FROM payments WHERE order_id = ? AND status = 'verified' LIMIT 1");
                $verified_payment->execute([$order['id']]);
                $verified_payment = $verified_payment->fetch();
                
                // Get pending payment (menunggu verifikasi)
                $pending_payment = $pdo->prepare("SELECT * FROM payments WHERE order_id = ? AND status = 'pending' LIMIT 1");
                $pending_payment->execute([$order['id']]);
                $pending_payment = $pending_payment->fetch();
                
                // Status badge class
                $status_class = '';
                $status_text = '';
                if ($order['status'] == 'approved') {
                    $status_class = 'bg-green-900 text-green-300';
                    $status_text = '✓ Disetujui';
                } elseif ($order['status'] == 'pending') {
                    $status_class = 'bg-yellow-900 text-yellow-300';
                    $status_text = '⏳ Menunggu Verifikasi';
                } else {
                    $status_class = 'bg-red-900 text-red-300';
                    $status_text = '✗ Ditolak';
                }
                
                // Payment status
                $payment_status_class = '';
                $payment_status_text = '';
                $can_view_invoice = false;
                
                if ($verified_payment) {
                    $payment_status_class = 'bg-green-900 text-green-300';
                    $payment_status_text = '✓ Lunas';
                    $can_view_invoice = true;
                } elseif ($pending_payment) {
                    $payment_status_class = 'bg-orange-900 text-orange-300';
                    $payment_status_text = '⏳ Menunggu Konfirmasi';
                    $can_view_invoice = false;
                } else {
                    $payment_status_class = 'bg-red-900 text-red-300';
                    $payment_status_text = '❌ Belum Dibayar';
                    $can_view_invoice = false;
                }
            ?>
            <div class="order-card rounded-2xl overflow-hidden">
                <!-- Order Header -->
                <div class="p-5 border-b border-[#2A2A2A] bg-gradient-to-r from-[#1A1A1A] to-[#252525]">
                    <div class="flex justify-between items-center flex-wrap gap-3">
                        <div>
                            <span class="text-sm text-[#9CA3AF]">Order #</span>
                            <span class="font-mono font-semibold text-white"><?= $order['order_number'] ?></span>
                        </div>
                        <div>
                            <span class="text-sm text-[#9CA3AF]">Tanggal Pesan</span>
                            <span class="ml-2 text-white"><?= date('d/m/Y H:i', strtotime($order['created_at'])) ?></span>
                        </div>
                        <div>
                            <span class="px-3 py-1 rounded-full text-xs font-medium <?= $status_class ?>">
                                <?= $status_text ?>
                            </span>
                        </div>
                    </div>
                </div>
                
                <!-- Order Body -->
                <div class="p-5">
                    <div class="flex flex-col md:flex-row justify-between gap-4">
                        <div class="flex-1">
                            <h3 class="font-serif text-xl font-semibold text-white"><?= $order['package_name'] ?></h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-2 mt-3 text-sm">
                                <div class="flex items-center gap-2 text-[#9CA3AF]">
                                    <span>📅</span> <?= date('d F Y', strtotime($order['wedding_date'])) ?>
                                </div>
                                <div class="flex items-center gap-2 text-[#9CA3AF]">
                                    <span>📍</span> <?= $order['venue'] ?>
                                </div>
                                <?php if($order['guest_count']): ?>
                                <div class="flex items-center gap-2 text-[#9CA3AF]">
                                    <span>👥</span> <?= $order['guest_count'] ?> tamu
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php if($order['notes']): ?>
                            <div class="mt-3 p-2 bg-[#252525] rounded-lg text-sm text-[#9CA3AF]">
                                <span class="font-medium">📝 Catatan:</span> <?= htmlspecialchars($order['notes']) ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="text-right md:text-left">
                            <p class="text-2xl font-serif font-bold text-[#FFD700]">Rp <?= number_format($order['total_price'], 0, ',', '.') ?></p>
                            <p class="text-xs text-[#9CA3AF] mt-1">Total Pembayaran</p>
                            <div class="mt-2">
                                <span class="inline-block px-2 py-1 rounded-full text-xs font-medium <?= $payment_status_class ?>">
                                    <?= $payment_status_text ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Action Buttons -->
                    <div class="mt-5 pt-4 border-t border-[#2A2A2A] flex flex-wrap gap-3 justify-end">
                        <!-- Detail Invoice Button (Hanya jika sudah lunas) -->
                        <?php if($can_view_invoice): ?>
                        <button onclick="showInvoiceModal(<?= $order['id'] ?>)" 
                                class="text-[#FFD700] border border-[#FFD700] px-4 py-2 rounded-full text-sm hover:bg-[#FFD700] hover:text-[#0A0A0A] transition">
                            📄 Lihat Invoice
                        </button>
                        <?php else: ?>
                        <button onclick="showInvoiceNotAvailable()" 
                                class="text-gray-500 border border-gray-600 px-4 py-2 rounded-full text-sm cursor-not-allowed">
                            📄 Invoice (Tersedia setelah lunas)
                        </button>
                        <?php endif; ?>
                        
                        <!-- Payment Button (if not paid yet) -->
                        <?php if(!$verified_payment && !$pending_payment && $order['status'] != 'rejected'): ?>
                        <button onclick="showPaymentModal(<?= $order['id'] ?>, <?= $order['total_price'] ?>)" 
                                class="bg-gradient-to-r from-[#FFD700] to-[#DAA520] text-[#0A0A0A] font-bold px-5 py-2 rounded-full text-sm hover:shadow-lg transition">
                            💳 Bayar Sekarang
                        </button>
                        <?php elseif($pending_payment && $order['status'] != 'rejected'): ?>
                        <div class="text-sm text-yellow-500 bg-yellow-900/30 px-4 py-2 rounded-full">
                            ⏳ Pembayaran sedang diverifikasi
                        </div>
                        <?php endif; ?>
                        
                        <!-- Re-order button for rejected orders -->
                        <?php if($order['status'] == 'rejected'): ?>
                        <a href="packages.php" class="bg-gray-700 text-white px-5 py-2 rounded-full text-sm hover:bg-gray-600 transition">
                            📦 Pesan Ulang
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Payment Modal -->
<div id="paymentModal" class="modal">
    <div class="bg-[#1A1A1A] rounded-2xl p-6 max-w-md w-full mx-4 border border-[#2A2A2A]">
        <div class="text-center mb-4">
            <span class="text-4xl">💳</span>
            <h2 class="font-serif text-xl font-bold text-white mt-2">Konfirmasi Pembayaran</h2>
        </div>
        <form action="process_payment.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="order_id" id="payment_order_id">
            <input type="hidden" name="amount" id="payment_amount">
            
            <div class="mb-4">
                <label class="block text-sm text-white mb-1">Nama Pengirim</label>
                <input type="text" name="sender_name" value="<?= $_SESSION['user_name'] ?>" 
                       class="w-full px-4 py-2 border border-[#2A2A2A] rounded-xl bg-[#0A0A0A] text-white focus:outline-none focus:border-[#FFD700]">
            </div>
            
            <div class="mb-4">
                <label class="block text-sm text-white mb-1">Metode Pembayaran</label>
                <select name="method" id="payment_method" class="w-full px-4 py-2 border border-[#2A2A2A] rounded-xl bg-[#0A0A0A] text-white focus:outline-none focus:border-[#FFD700]" onchange="togglePaymentMethod()">
                    <option value="bca">🏦 BCA - 1234567890 a.n Prismatic Organizer</option>
                    <option value="bni">🏦 BNI - 9876543210 a.n Prismatic Organizer</option>
                    <option value="qris">📱 QRIS - Scan QR Code</option>
                </select>
            </div>
            
            <div id="upload_area" class="mb-4">
                <label class="block text-sm text-white mb-1">Upload Bukti Transfer</label>
                <input type="file" name="proof_image" accept="image/*" class="w-full text-white">
                <p class="text-xs text-[#9CA3AF] mt-1">Upload screenshot bukti transfer (JPG/PNG) - WAJIB diisi untuk semua metode pembayaran</p>
            </div>
            
            <div id="qris_area" class="hidden text-center py-4 mb-4">
                <div class="bg-[#252525] p-4 rounded-xl">
                    <img id="qris_img" src="" class="w-48 mx-auto mb-3 rounded-xl shadow-md">
                    <p class="text-sm text-[#9CA3AF]">Scan QR Code menggunakan mobile banking atau e-wallet</p>
                    <p class="text-xs text-[#FFD700] mt-2">* Setelah scan, upload bukti pembayaran di atas</p>
                </div>
            </div>
            
            <button type="submit" class="w-full bg-gradient-to-r from-[#FFD700] to-[#DAA520] text-[#0A0A0A] font-bold py-2.5 rounded-full mt-3 hover:shadow-lg transition">
                Konfirmasi Pembayaran
            </button>
            <button type="button" onclick="closeModal('paymentModal')" class="w-full mt-2 text-[#9CA3AF] text-sm">Batal</button>
        </form>
    </div>
</div>

<!-- Invoice Modal - Menggunakan invoice.php yang baru -->
<div id="invoiceModal" class="modal">
    <div class="bg-[#1A1A1A] rounded-2xl p-6 max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto border border-[#2A2A2A]">
        <div id="invoice_content" class="text-center py-8 text-[#9CA3AF]">Memuat...</div>
        <div class="flex gap-3 mt-4">
            <button onclick="printInvoice()" class="flex-1 bg-[#FFD700] text-[#0A0A0A] font-bold py-2 rounded-full hover:shadow-lg transition">
                🖨️ Cetak Invoice
            </button>
            <button onclick="closeModal('invoiceModal')" class="flex-1 border border-[#2A2A2A] text-[#9CA3AF] py-2 rounded-full hover:bg-[#2A2A2A] transition">
                Tutup
            </button>
        </div>
    </div>
</div>

<!-- Invoice Not Available Modal -->
<div id="invoiceNotAvailableModal" class="modal">
    <div class="bg-[#1A1A1A] rounded-2xl p-6 max-w-sm w-full mx-4 text-center border border-[#2A2A2A]">
        <div class="text-5xl mb-4">📄</div>
        <h2 class="font-serif text-xl font-bold text-white mb-2">Invoice Belum Tersedia</h2>
        <p class="text-[#9CA3AF] mb-4">
            Invoice hanya tersedia setelah pembayaran Anda <span class="font-semibold text-[#FFD700]">diverifikasi dan lunas</span>.
        </p>
        <p class="text-sm text-[#FFD700] mb-6">Silakan selesaikan pembayaran terlebih dahulu.</p>
        <button onclick="closeModal('invoiceNotAvailableModal')" class="bg-gradient-to-r from-[#FFD700] to-[#DAA520] text-[#0A0A0A] font-bold px-6 py-2 rounded-full">
            Tutup
        </button>
    </div>
</div>

<script>
    let currentInvoiceHtml = '';
    
    function showPaymentModal(orderId, amount) {
        document.getElementById('payment_order_id').value = orderId;
        document.getElementById('payment_amount').value = amount;
        document.getElementById('paymentModal').classList.add('active');
        togglePaymentMethod();
    }
    
    function togglePaymentMethod() {
        const method = document.getElementById('payment_method').value;
        const uploadArea = document.getElementById('upload_area');
        const qrisArea = document.getElementById('qris_area');
        const qrisImg = document.getElementById('qris_img');
        
        uploadArea.classList.remove('hidden');
        
        if (method === 'qris') {
            qrisArea.classList.remove('hidden');
            const timestamp = Date.now();
            qrisImg.src = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=PRISMATIC' + timestamp;
        } else {
            qrisArea.classList.add('hidden');
        }
    }
    
    function showInvoiceModal(orderId) {
        const modal = document.getElementById('invoiceModal');
        const contentDiv = document.getElementById('invoice_content');
        contentDiv.innerHTML = '<div class="text-center py-8">📄 Memuat invoice...</div>';
        modal.classList.add('active');
        
        // Gunakan file invoice.php yang baru (bukan get_invoice.php)
        fetch('invoice.php?id=' + orderId)
            .then(res => res.text())
            .then(data => {
                // Ekstrak hanya bagian invoice (tanpa sidebar dan header)
                const parser = new DOMParser();
                const doc = parser.parseFromString(data, 'text/html');
                const invoiceContent = doc.querySelector('.invoice-card');
                if (invoiceContent) {
                    currentInvoiceHtml = invoiceContent.outerHTML;
                    contentDiv.innerHTML = currentInvoiceHtml;
                } else {
                    contentDiv.innerHTML = '<div class="text-center py-8 text-red-500">❌ Gagal memuat invoice</div>';
                }
            })
            .catch(err => {
                console.error(err);
                contentDiv.innerHTML = '<div class="text-center py-8 text-red-500">❌ Gagal memuat invoice</div>';
            });
    }
    
    function showInvoiceNotAvailable() {
        document.getElementById('invoiceNotAvailableModal').classList.add('active');
    }
    
    function printInvoice() {
        const printWindow = window.open('', '_blank');
        printWindow.document.write('<html><head><title>Invoice - Prismatic Organizer</title>');
        printWindow.document.write('<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">');
        printWindow.document.write('<style>body { font-family: "DM Sans", sans-serif; padding: 40px; background: white; color: black; } .text-center { text-align: center; }</style>');
        printWindow.document.write('</head><body>');
        printWindow.document.write(currentInvoiceHtml);
        printWindow.document.write('</body></html>');
        printWindow.document.close();
        printWindow.print();
    }
    
    function closeModal(modalId) {
        document.getElementById(modalId).classList.remove('active');
    }
    
    function toggleMobileSidebar() {
        document.querySelector('.sidebar-fixed').classList.toggle('mobile-open');
    }
    
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal').forEach(m => m.classList.remove('active'));
        }
    });
    
    togglePaymentMethod();
</script>
</body>
</html>