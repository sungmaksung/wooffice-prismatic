<?php
include '../config/database.php';
include 'log_activity.php';
redirectIfNotLoggedIn();
if (!isEmployee()) { header('Location: ../login.php?role=employee'); exit(); }

// ORDER BY wedding_date DESC agar yang terbaru di atas
$events = $pdo->query("
    SELECT o.*, u.full_name as client_name, u.couple_name, u.phone, p.name as package_name 
    FROM orders o 
    JOIN users u ON o.client_id = u.id 
    JOIN packages p ON o.package_id = p.id 
    WHERE o.status = 'approved' 
    ORDER BY o.wedding_date DESC
");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Daftar Acara - Prismatic Organizer</title>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,300;1,400;1,600&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        * { font-family: 'DM Sans', sans-serif; }
        .main-content { margin-left: 280px; padding: 24px; min-height: 100vh; background: #0F172A; }
        .modal-backdrop { backdrop-filter: blur(8px); }
        .detail-modal { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.7); backdrop-filter: blur(8px); z-index: 1000; align-items: center; justify-content: center; }
        .detail-modal.active { display: flex; }
        .detail-modal-card { background: linear-gradient(135deg, #1E293B 0%, #0F172A 100%); border: 1px solid #334155; border-radius: 24px; max-width: 500px; width: 90%; max-height: 85vh; overflow-y: auto; animation: modalFadeIn 0.3s ease; }
        @keyframes modalFadeIn { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }
        .invoice-btn { background: linear-gradient(135deg, #2563EB, #1D4ED8); transition: all 0.3s ease; }
        .invoice-btn:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(37,99,235,0.4); }
        @media (max-width: 768px) { .main-content { margin-left: 0; padding: 80px 16px 24px; } }
    </style>
</head>
<body class="bg-[#0F172A]">

<div class="flex">
    <?php include 'sidebar.php'; ?>
    
    <main class="main-content flex-1">
        <h1 class="text-2xl font-bold mb-6 text-[#60A5FA]">📅 Daftar Acara</h1>
        
        <div class="bg-[#1E293B] rounded-2xl border border-[#334155] overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-[#0F172A] border-b border-[#334155]">
                        <tr class="text-left text-gray-400 text-sm">
                            <th class="p-4">No Order</th>
                            <th>Nama Pasangan</th>
                            <th>Lokasi Gedung</th>
                            <th>Tanggal</th>
                            <th>Paket</th>
                            <th>Total</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($event = $events->fetch()): 
                            $payment = $pdo->prepare("SELECT * FROM payments WHERE order_id = ? AND status = 'verified' LIMIT 1");
                            $payment->execute([$event['id']]);
                            $payment = $payment->fetch();
                        ?>
                        <tr class="border-b border-[#334155] hover:bg-[#2D3A5E] transition">
                            <td class="p-4 font-medium text-white"><?= $event['order_number'] ?></td>
                            <td class="p-4 text-gray-300"><?= htmlspecialchars($event['couple_name'] ?? $event['client_name']) ?></td>
                            <td class="p-4 text-gray-300"><?= htmlspecialchars($event['venue']) ?></td>
                            <td class="p-4 text-gray-300"><?= date('d/m/Y', strtotime($event['wedding_date'])) ?> <span class="text-xs text-gray-500 ml-1">(<?= date('d M Y', strtotime($event['wedding_date'])) ?>)</span></td>
                            <td class="p-4 text-gray-300"><?= $event['package_name'] ?></td>
                            <td class="p-4 text-yellow-400 font-semibold">Rp <?= number_format($event['total_price'], 0, ',', '.') ?></td>
                            <td class="p-4">
                                <button onclick="showDetail(<?= $event['id'] ?>, '<?= addslashes($event['order_number']) ?>', '<?= addslashes($event['client_name']) ?>', '<?= addslashes($event['couple_name'] ?? '') ?>', '<?= $event['phone'] ?>', '<?= addslashes($event['venue']) ?>', '<?= date('d/m/Y', strtotime($event['wedding_date'])) ?>', '<?= addslashes($event['package_name']) ?>', <?= $event['total_price'] ?>, '<?= addslashes($payment['sender_name'] ?? '') ?>', '<?= $payment['method'] ?? '' ?>', '<?= $payment['proof_image'] ?? '' ?>', <?= $payment['id'] ?? 0 ?>)" 
                                        class="text-blue-400 hover:text-blue-300 transition">👁️ Lihat Detail</button>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<!-- Modal Detail -->
<div id="detailModal" class="detail-modal">
    <div class="detail-modal-card p-6">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-bold text-white flex items-center gap-2">📋 Detail Pesanan</h2>
            <button onclick="closeModal()" class="text-gray-400 hover:text-white text-2xl">&times;</button>
        </div>
        <div class="space-y-3">
            <div class="flex justify-between py-2 border-b border-[#334155]">
                <span class="text-gray-400">No Order:</span>
                <span id="detail_order" class="text-white font-medium"></span>
            </div>
            <div class="flex justify-between py-2 border-b border-[#334155]">
                <span class="text-gray-400">Nama Client:</span>
                <span id="detail_client" class="text-white"></span>
            </div>
            <div class="flex justify-between py-2 border-b border-[#334155]">
                <span class="text-gray-400">Nama Pasangan:</span>
                <span id="detail_couple" class="text-white"></span>
            </div>
            <div class="flex justify-between py-2 border-b border-[#334155]">
                <span class="text-gray-400">No HP Pemesan:</span>
                <span id="detail_phone" class="text-white"></span>
            </div>
            <div class="flex justify-between py-2 border-b border-[#334155]">
                <span class="text-gray-400">Lokasi Gedung:</span>
                <span id="detail_venue" class="text-white"></span>
            </div>
            <div class="flex justify-between py-2 border-b border-[#334155]">
                <span class="text-gray-400">Tanggal Pernikahan:</span>
                <span id="detail_date" class="text-white"></span>
            </div>
            <div class="flex justify-between py-2 border-b border-[#334155]">
                <span class="text-gray-400">Paket:</span>
                <span id="detail_package" class="text-white"></span>
            </div>
            <div class="flex justify-between py-2 border-b border-[#334155]">
                <span class="text-gray-400">Total Harga:</span>
                <span id="detail_price" class="text-yellow-400 font-semibold"></span>
            </div>
            <div class="flex justify-between py-2 border-b border-[#334155]">
                <span class="text-gray-400">Nama Pengirim:</span>
                <span id="detail_sender" class="text-white"></span>
            </div>
            <div class="flex justify-between py-2 border-b border-[#334155]">
                <span class="text-gray-400">Metode Bayar:</span>
                <span id="detail_method" class="text-white"></span>
            </div>
        </div>
        <div id="proofImageContainer" class="mt-4 p-3 bg-[#0F172A] rounded-xl border border-[#334155] text-center hidden">
            <p class="text-gray-400 text-sm mb-2">📎 Bukti Transfer:</p>
            <img id="proofImage" src="" class="max-w-full max-h-48 mx-auto rounded-lg cursor-pointer" onclick="window.open(this.src, '_blank')">
            <button onclick="window.open(document.getElementById('proofImage').src, '_blank')" class="mt-2 text-blue-400 text-sm hover:text-blue-300">🔍 Lihat Fullscreen</button>
        </div>
        <div class="flex gap-3 mt-6">
            <button onclick="closeModal()" class="flex-1 bg-gray-600 hover:bg-gray-500 text-white py-2 rounded-lg transition">Tutup</button>
            <button id="printInvoiceBtn" class="flex-1 invoice-btn text-white py-2 rounded-lg transition font-medium">🖨️ Cetak Invoice</button>
        </div>
    </div>
</div>

<script>
    let currentOrderId = null;
    
    function showDetail(id, orderNum, clientName, coupleName, phone, venue, weddingDate, packageName, totalPrice, senderName, paymentMethod, proofImage, paymentId) {
        currentOrderId = id;
        document.getElementById('detail_order').innerText = orderNum;
        document.getElementById('detail_client').innerText = clientName;
        document.getElementById('detail_couple').innerText = coupleName || '-';
        document.getElementById('detail_phone').innerText = phone;
        document.getElementById('detail_venue').innerText = venue;
        document.getElementById('detail_date').innerText = weddingDate;
        document.getElementById('detail_package').innerText = packageName;
        document.getElementById('detail_price').innerText = 'Rp ' + new Intl.NumberFormat('id-ID').format(totalPrice);
        document.getElementById('detail_sender').innerText = senderName || '-';
        document.getElementById('detail_method').innerText = paymentMethod ? paymentMethod.toUpperCase() : '-';
        
        const proofContainer = document.getElementById('proofImageContainer');
        const proofImg = document.getElementById('proofImage');
        if(proofImage && proofImage !== '') {
            proofImg.src = '../' + proofImage;
            proofContainer.classList.remove('hidden');
        } else {
            proofContainer.classList.add('hidden');
        }
        
        document.getElementById('detailModal').classList.add('active');
        document.body.style.overflow = 'hidden';
        
        const printBtn = document.getElementById('printInvoiceBtn');
        printBtn.onclick = function() { window.open('invoice.php?id=' + currentOrderId, '_blank'); };
    }
    
    function closeModal() {
        document.getElementById('detailModal').classList.remove('active');
        document.body.style.overflow = '';
    }
    
    document.getElementById('detailModal').addEventListener('click', function(e) {
        if(e.target === this) closeModal();
    });
    
    function toggleMobileSidebar() {
        document.querySelector('.sidebar-glass').classList.toggle('mobile-open');
    }
</script>
</body>
</html>