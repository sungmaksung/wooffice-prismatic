<?php
include '../config/database.php';
include 'log_activity.php';
redirectIfNotLoggedIn();
if (!isEmployee()) { header('Location: ../login.php?role=employee'); exit(); }

// Handle verification
if(isset($_POST['verify_action'])) {
    $payment_id = $_POST['payment_id'];
    $status = $_POST['status'];
    $rejection_reason = $_POST['rejection_reason'] ?? null;
    
    // Update payment status
    $stmt = $pdo->prepare("UPDATE payments SET status = ?, verified_by = ?, verified_at = NOW(), rejection_reason = ? WHERE id = ?");
    $stmt->execute([$status, $_SESSION['user_id'], $rejection_reason, $payment_id]);
    
    // Get order and client info
    $payment = $pdo->prepare("
        SELECT p.*, o.client_id, o.order_number, o.total_price, o.package_id, u.full_name, u.couple_name, u.email 
        FROM payments p 
        JOIN orders o ON p.order_id = o.id 
        JOIN users u ON o.client_id = u.id 
        WHERE p.id = ?
    ");
    $payment->execute([$payment_id]);
    $payment = $payment->fetch();
    
    $client_display = $payment['couple_name'] ?? $payment['full_name'];
    
    // Update order status if payment verified
    if($status == 'verified') {
        $pdo->prepare("UPDATE orders SET payment_status = 'paid', status = 'approved' WHERE id = ?")->execute([$payment['order_id']]);
        addNotification($payment['client_id'], '✅ Pembayaran Diverifikasi', 
                        'Pembayaran Anda untuk order ' . $payment['order_number'] . ' telah diverifikasi. Terima kasih!', 
                        'success', 'client/dashboard.php');
        logEmployeeActivity($pdo, "Memverifikasi pembayaran #$payment_id untuk client $client_display (Order: {$payment['order_number']})", 'verify', 'payment', $payment_id, "Jumlah: Rp " . number_format($payment['amount'], 0, ',', '.'));
    } else {
        addNotification($payment['client_id'], '❌ Pembayaran Ditolak', 
                        'Pembayaran Anda untuk order ' . $payment['order_number'] . ' ditolak. Alasan: ' . ($rejection_reason ?? 'Tidak valid'), 
                        'error', 'client/payment.php');
        logEmployeeActivity($pdo, "Menolak pembayaran #$payment_id untuk client $client_display (Order: {$payment['order_number']}) - Alasan: $rejection_reason", 'reject', 'payment', $payment_id, $rejection_reason);
    }
    
    header("Location: payment_requests.php?msg=" . ($status == 'verified' ? 'verified' : 'rejected'));
    exit();
}

$msg = $_GET['msg'] ?? '';
$payments = $pdo->query("
    SELECT p.*, o.order_number, u.full_name as client_name, u.couple_name, o.total_price, o.wedding_date, o.venue, o.package_id
    FROM payments p 
    JOIN orders o ON p.order_id = o.id 
    JOIN users u ON o.client_id = u.id 
    WHERE p.status = 'pending' 
    ORDER BY p.created_at DESC
");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Permintaan Pembayaran - Prismatic Organizer</title>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,300;1,400;1,600&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        * { font-family: 'DM Sans', sans-serif; }
        .main-content { margin-left: 280px; padding: 24px; min-height: 100vh; background: #0F172A; }
        .modal-backdrop { backdrop-filter: blur(8px); }
        .detail-modal { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.7); backdrop-filter: blur(8px); z-index: 1000; align-items: center; justify-content: center; }
        .detail-modal.active { display: flex; }
        .detail-modal-card { background: linear-gradient(135deg, #1E293B 0%, #0F172A 100%); border: 1px solid #334155; border-radius: 24px; max-width: 500px; width: 90%; animation: modalFadeIn 0.3s ease; }
        @keyframes modalFadeIn { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }
        
        /* Active button styles */
        .action-btn {
            transition: all 0.2s ease;
            position: relative;
            overflow: hidden;
        }
        .action-btn.active {
            transform: scale(1.02);
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        }
        .action-btn.active::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255,255,255,0.2);
            border-radius: inherit;
            pointer-events: none;
        }
        .btn-approve.active {
            background-color: #16a34a !important;
            border: 2px solid #22c55e !important;
            box-shadow: 0 0 0 2px rgba(34,197,94,0.3) !important;
        }
        .btn-reject.active {
            background-color: #dc2626 !important;
            border: 2px solid #ef4444 !important;
            box-shadow: 0 0 0 2px rgba(239,68,68,0.3) !important;
        }
        
        .confirm-btn {
            transition: all 0.2s ease;
            
        }
        .confirm-btn.confirm-approve {
            background: linear-gradient(135deg, #b9b9b9, #656565) !important;

        }
        .confirm-btn.confirm-reject {
            background: linear-gradient(135deg, #ef4444, #dc2626) !important;
        }
        
        @media (max-width: 768px) { 
            .main-content { margin-left: 0; padding: 80px 16px 24px; } 
        }
    </style>
    <script>
        let currentPaymentId = null;
        let currentAction = 'approve'; // 'approve' or 'reject'
        
        function openModal(paymentId, amount, clientName, proofImage) {
            currentPaymentId = paymentId;
            document.getElementById('modalPaymentId').value = paymentId;
            document.getElementById('modalAmount').innerText = 'Rp ' + new Intl.NumberFormat('id-ID').format(amount);
            document.getElementById('modalClientName').innerText = clientName;
            
            const proofImg = document.getElementById('modalProofImage');
            if(proofImage && proofImage !== '') {
                proofImg.src = '../' + proofImage;
                document.getElementById('proofImageContainer').classList.remove('hidden');
            } else {
                document.getElementById('proofImageContainer').classList.add('hidden');
            }
            
            document.getElementById('verificationModal').classList.add('active');
            document.getElementById('rejectReasonDiv').classList.add('hidden');
            document.getElementById('status').value = 'verified';
            
            // Reset active states
            setActiveApprove();
            
            document.getElementById('confirmButton').innerHTML = ' Konfirmasi';
            document.getElementById('confirmButton').className = 'confirm-btn w-full py-2 rounded-lg transition font-medium confirm-approve';
        }
        
        function closeModal() {
            document.getElementById('verificationModal').classList.remove('active');
            document.getElementById('rejectReason').value = '';
            // Reset active states
            document.getElementById('btnApprove').classList.remove('active');
            document.getElementById('btnReject').classList.remove('active');
        }
        
        function setActiveApprove() {
            currentAction = 'approve';
            document.getElementById('status').value = 'verified';
            document.getElementById('rejectReasonDiv').classList.add('hidden');
            
            // Update button active states
            document.getElementById('btnApprove').classList.add('active');
            document.getElementById('btnReject').classList.remove('active');
            
            // Update confirm button style
            document.getElementById('confirmButton').innerHTML = '✅ Konfirmasi Setujui';
            document.getElementById('confirmButton').className = 'confirm-btn w-full py-2 rounded-lg transition font-medium confirm-approve';
        }
        
        function setActiveReject() {
            currentAction = 'reject';
            document.getElementById('status').value = 'rejected';
            document.getElementById('rejectReasonDiv').classList.remove('hidden');
            
            // Update button active states
            document.getElementById('btnReject').classList.add('active');
            document.getElementById('btnApprove').classList.remove('active');
            
            // Update confirm button style
            document.getElementById('confirmButton').innerHTML = '❌ Konfirmasi Tolak';
            document.getElementById('confirmButton').className = 'confirm-btn w-full py-2 rounded-lg transition font-medium confirm-reject';
        }
        
        function submitVerification() {
            if(currentAction === 'reject') {
                const reason = document.getElementById('rejectReason').value;
                if(!reason.trim()) {
                    alert('⚠️ Harap isi alasan penolakan!');
                    return false;
                }
                if(!confirm('Yakin ingin MENOLAK pembayaran ini?\nAlasan: ' + reason)) {
                    return false;
                }
            } else {
                if(!confirm('Yakin ingin MENYETUJUI pembayaran ini?')) {
                    return false;
                }
            }
            document.getElementById('verifyForm').submit();
            return true;
        }
        
        setTimeout(() => {
            const notif = document.getElementById('notificationMsg');
            if(notif) notif.style.opacity = '0';
            setTimeout(() => { if(notif) notif.style.display = 'none'; }, 500);
        }, 3000);
        
        // Close modal when clicking outside
        document.getElementById('verificationModal').addEventListener('click', function(e) {
            if(e.target === this) closeModal();
        });
        
        // Escape key to close modal
        document.addEventListener('keydown', function(e) {
            if(e.key === 'Escape') {
                closeModal();
            }
        });
    </script>
</head>
<body class="bg-[#0F172A]">

<div class="flex">
    <?php include 'sidebar.php'; ?>
    
    <main class="main-content flex-1">
        <h1 class="text-2xl font-bold mb-6 text-[#60A5FA]">💳 Permintaan Pembayaran</h1>
        
        <?php if($msg == 'verified'): ?>
        <div id="notificationMsg" class="mb-4 bg-green-500/20 border border-green-500/30 text-green-400 px-4 py-3 rounded-xl transition-opacity duration-500">✅ Pembayaran berhasil diverifikasi!</div>
        <?php elseif($msg == 'rejected'): ?>
        <div id="notificationMsg" class="mb-4 bg-red-500/20 border border-red-500/30 text-red-400 px-4 py-3 rounded-xl transition-opacity duration-500">❌ Pembayaran ditolak.</div>
        <?php endif; ?>
        
        <?php if($payments->rowCount() == 0): ?>
        <div class="bg-[#1E293B] rounded-2xl border border-[#334155] p-12 text-center">
            <div class="text-6xl mb-4">✅</div>
            <h2 class="text-xl font-bold text-white">Tidak ada permintaan pembayaran</h2>
            <p class="text-gray-400 mt-2">Semua pembayaran sudah diproses</p>
        </div>
        <?php else: ?>
        <div class="bg-[#1E293B] rounded-2xl border border-[#334155] overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-[#0F172A] border-b border-[#334155]">
                        <tr class="text-left text-gray-400 text-sm">
                            <th class="p-4">No Order</th>
                            <th>Client</th>
                            <th>Jumlah</th>
                            <th>Metode</th>
                            <th>Tanggal</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($payment = $payments->fetch()): ?>
                        <tr class="border-b border-[#334155] hover:bg-[#2D3A5E] transition">
                            <td class="p-4 font-medium text-white"><?= $payment['order_number'] ?></td>
                            <td class="p-4 text-gray-300"><?= htmlspecialchars($payment['couple_name'] ?? $payment['client_name']) ?></td>
                            <td class="p-4 font-semibold text-yellow-400">Rp <?= number_format($payment['amount'], 0, ',', '.') ?></td>
                            <td class="p-4">
                                <span class="px-2 py-1 rounded-full text-xs font-medium <?= $payment['method'] == 'qris' ? 'bg-purple-500/20 text-purple-400' : 'bg-blue-500/20 text-blue-400' ?>">
                                    <?= strtoupper($payment['method']) ?>
                                </span>
                            </td>
                            <td class="p-4 text-gray-400"><?= date('d/m/Y H:i', strtotime($payment['created_at'])) ?></td>
                            <td class="p-4">
                                <button onclick="openModal(<?= $payment['id'] ?>, <?= $payment['amount'] ?>, '<?= addslashes($payment['couple_name'] ?? $payment['client_name']) ?>', '<?= $payment['proof_image'] ?>')" 
                                        class="bg-blue-600 hover:bg-blue-500 text-white px-4 py-2 rounded-lg text-sm transition">
                                    🔍 Verifikasi
                                </button>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </main>
</div>

<!-- Modal Verifikasi - DI TENGAH dengan Active State -->
<div id="verificationModal" class="detail-modal">
    <div class="detail-modal-card p-6 mx-4">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-bold text-white flex items-center gap-2">🔍 Verifikasi Pembayaran</h2>
            <button onclick="closeModal()" class="text-gray-400 hover:text-white text-2xl">&times;</button>
        </div>
        
        <form id="verifyForm" method="POST" action="">
            <input type="hidden" id="modalPaymentId" name="payment_id">
            <input type="hidden" id="status" name="status" value="verified">
            <input type="hidden" name="verify_action" value="1">
            
            <div class="space-y-3 mb-4">
                <div class="flex justify-between py-2 border-b border-[#334155]">
                    <span class="text-gray-400">Client:</span>
                    <span id="modalClientName" class="font-semibold text-white"></span>
                </div>
                <div class="flex justify-between py-2 border-b border-[#334155]">
                    <span class="text-gray-400">Jumlah:</span>
                    <span id="modalAmount" class="font-bold text-yellow-400 text-lg"></span>
                </div>
            </div>
            
            <div class="mb-4">
                <label class="block text-gray-300 mb-2">📎 Bukti Transfer</label>
                <div id="proofImageContainer" class="border border-[#334155] rounded-xl p-3 text-center hidden bg-[#0F172A]">
                    <img id="modalProofImage" src="" class="max-w-full max-h-48 mx-auto rounded-lg cursor-pointer" onclick="window.open(this.src, '_blank')">
                    <button type="button" onclick="window.open(document.getElementById('modalProofImage').src, '_blank')" class="mt-2 text-sm text-blue-400 hover:text-blue-300">🔍 Lihat Fullscreen</button>
                </div>
                <p class="text-gray-500 text-xs mt-1">Klik gambar untuk memperbesar</p>
            </div>
            
            <div id="rejectReasonDiv" class="mb-4 hidden">
                <label class="block text-gray-300 mb-2">❌ Alasan Penolakan</label>
                <textarea id="rejectReason" name="rejection_reason" rows="3" class="w-full px-4 py-2 bg-[#0F172A] border border-[#334155] rounded-lg text-white focus:outline-none focus:border-blue-500" placeholder="Berikan alasan penolakan..."></textarea>
            </div>
            
            <!-- Tombol Setujui dan Tolak dengan Active State -->
            <div class="flex gap-3 mt-6">
                <button type="button" id="btnApprove" onclick="setActiveApprove()" class="action-btn btn-approve flex-1 bg-green-600 hover:bg-green-500 text-white py-2 rounded-lg transition font-medium">
                    ✅ Setujui
                </button>
                <button type="button" id="btnReject" onclick="setActiveReject()" class="action-btn btn-reject flex-1 bg-red-600 hover:bg-red-500 text-white py-2 rounded-lg transition font-medium">
                    ❌ Tolak
                </button>
                <button type="button" onclick="closeModal()" class="flex-1 bg-gray-600 hover:bg-gray-500 text-white py-2 rounded-lg transition font-medium">
                    Batal
                </button>
            </div>
            <div class="mt-3">
                <button type="button" id="confirmButton" onclick="submitVerification()" class="confirm-btn w-full bg-green-600 hover:bg-green-500 text-white py-2 rounded-lg transition font-medium">
                    ✅ Konfirmasi
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function toggleMobileSidebar() {
        document.querySelector('.sidebar-glass').classList.toggle('mobile-open');
    }
</script>
</body>
</html>