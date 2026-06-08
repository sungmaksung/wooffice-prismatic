<?php
include '../config/database.php';
redirectIfNotLoggedIn();
if (!isClient()) { header('Location: ../login.php?role=client'); exit(); }

$user_id = $_SESSION['user_id'];

// Handle submit review
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    $order_id = $_POST['order_id'];
    $rating = (int)$_POST['rating'];
    $review = trim($_POST['review']);
    
    // Get order details
    $order = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND client_id = ? AND can_review = 1 AND reviewed = 0");
    $order->execute([$order_id, $user_id]);
    $order = $order->fetch();
    
    if ($order && $rating >= 1 && $rating <= 5 && !empty($review)) {
        // Insert review
        $stmt = $pdo->prepare("INSERT INTO reviews (order_id, client_id, package_id, rating, review, status) VALUES (?, ?, ?, ?, ?, 'pending')");
        $stmt->execute([$order_id, $user_id, $order['package_id'], $rating, $review]);
        
        // Update order reviewed flag
        $pdo->prepare("UPDATE orders SET reviewed = 1 WHERE id = ?")->execute([$order_id]);
        
        // Notify employees
        $employees = $pdo->query("SELECT id FROM users WHERE role = 'employee'")->fetchAll();
        foreach($employees as $emp) {
            addNotification($emp['id'], '⭐ Ulasan Baru', $_SESSION['user_name'] . ' memberi ulasan bintang ' . $rating, 'review', 'employee/reviews.php');
        }
        
        header("Location: reviews.php?success=1");
        exit();
    } else {
        $error = "Gagal menyimpan ulasan. Silakan coba lagi.";
    }
}

// Get orders that can be reviewed (wedding date passed + not reviewed yet)
$can_review_orders = $pdo->prepare("
    SELECT o.*, p.name as package_name 
    FROM orders o 
    JOIN packages p ON o.package_id = p.id 
    WHERE o.client_id = ? 
      AND o.can_review = 1 
      AND o.reviewed = 0
      AND o.status = 'approved'
    ORDER BY o.wedding_date DESC
");
$can_review_orders->execute([$user_id]);

// Get already submitted reviews
$my_reviews = $pdo->prepare("
    SELECT r.*, o.order_number, o.wedding_date, p.name as package_name 
    FROM reviews r 
    JOIN orders o ON r.order_id = o.id 
    JOIN packages p ON r.package_id = p.id 
    WHERE r.client_id = ? 
    ORDER BY r.created_at DESC
");
$my_reviews->execute([$user_id]);

$success = isset($_GET['success']);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ulasan Saya - Prismatic Organizer</title>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,300;1,400;1,600&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        * { font-family: 'DM Sans', sans-serif; }
        .font-serif { font-family: 'Cormorant Garamond', serif; }
        .main-content { margin-left: 280px; padding: 32px; min-height: 100vh; background: #0A0A0A; }
        .rating-star { cursor: pointer; transition: all 0.2s; font-size: 28px; }
        .rating-star:hover, .rating-star.active { color: #F59E0B; text-shadow: 0 0 4px rgba(245,158,11,0.5); }
        @media (max-width: 768px) { .main-content { margin-left: 0; padding: 80px 20px 32px; } }
        .modal { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.85); backdrop-filter: blur(12px); z-index: 1000; align-items: center; justify-content: center; }
        .modal.active { display: flex; }
    </style>
</head>
<body class="bg-[#0A0A0A]">

<?php include 'includes/sidebar.php'; ?>

<div class="main-content">
    <div class="mb-8">
        <h1 class="font-serif text-3xl font-semibold text-[#FFD700]">⭐ Ulasan Saya</h1>
        <p class="text-[#9CA3AF] mt-1">Bagikan pengalaman Anda menggunakan layanan Prismatic Organizer</p>
    </div>
    
    <?php if($success): ?>
    <div class="mb-6 bg-green-900 border border-green-700 text-green-300 px-4 py-3 rounded-xl">
        ✅ Terima kasih atas ulasannya! Ulasan Anda akan ditampilkan setelah disetujui admin.
    </div>
    <?php endif; ?>
    
    <!-- Orders that can be reviewed -->
    <?php if($can_review_orders->rowCount() > 0): ?>
    <div class="mb-10">
        <h2 class="text-xl font-semibold text-white mb-4 flex items-center gap-2">
            <span>📝</span> Beri Ulasan untuk Pesanan Ini
        </h2>
        <div class="space-y-4">
            <?php while($order = $can_review_orders->fetch()): ?>
            <div class="bg-[#1A1A1A] rounded-2xl border border-[#2A2A2A] overflow-hidden">
                <div class="p-5">
                    <div class="flex justify-between items-start flex-wrap gap-4">
                        <div>
                            <h3 class="font-serif text-lg font-semibold text-white"><?= htmlspecialchars($order['package_name']) ?></h3>
                            <p class="text-sm text-[#9CA3AF]">Order #<?= $order['order_number'] ?></p>
                            <p class="text-sm text-[#9CA3AF]">📅 <?= date('d F Y', strtotime($order['wedding_date'])) ?></p>
                            <p class="text-sm text-[#9CA3AF]">📍 <?= htmlspecialchars($order['venue']) ?></p>
                        </div>
                        <div>
                            <span class="inline-block px-3 py-1 rounded-full text-xs font-medium bg-green-900 text-green-300">
                                ✓ Selesai
                            </span>
                        </div>
                    </div>
                    
                    <div class="mt-4 pt-3 border-t border-[#2A2A2A]">
                        <button onclick="openReviewModal(<?= $order['id'] ?>, '<?= addslashes($order['package_name']) ?>', '<?= $order['order_number'] ?>')" 
                                class="bg-gradient-to-r from-[#FFD700] to-[#DAA520] text-[#0A0A0A] font-bold px-5 py-2 rounded-full text-sm hover:shadow-lg transition">
                            ⭐ Beri Ulasan
                        </button>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- My Reviews History -->
    <div>
        <h2 class="text-xl font-semibold text-white mb-4 flex items-center gap-2">
            <span>📋</span> Riwayat Ulasan Saya
        </h2>
        
        <?php if($my_reviews->rowCount() == 0): ?>
        <div class="bg-[#1A1A1A] rounded-2xl p-8 text-center border border-[#2A2A2A]">
            <span class="text-5xl mb-3 block">⭐</span>
            <p class="text-[#9CA3AF]">Belum ada ulasan yang Anda berikan.</p>
            <p class="text-sm text-[#FFD700] mt-1">Setelah pernikahan selesai, Anda bisa memberi ulasan di sini!</p>
        </div>
        <?php else: ?>
            <div class="space-y-4">
                <?php while($review = $my_reviews->fetch()): ?>
                <div class="bg-[#1A1A1A] rounded-2xl p-5 border border-[#2A2A2A]">
                    <div class="flex justify-between items-start flex-wrap gap-3">
                        <div>
                            <div class="flex items-center gap-2 mb-2">
                                <?php for($i = 1; $i <= 5; $i++): ?>
                                <span class="text-xl <?= $i <= $review['rating'] ? 'text-yellow-500' : 'text-gray-600' ?>">★</span>
                                <?php endfor; ?>
                            </div>
                            <h3 class="font-serif text-lg font-semibold text-white"><?= htmlspecialchars($review['package_name']) ?></h3>
                            <p class="text-sm text-[#9CA3AF]">Order #<?= $review['order_number'] ?> • <?= date('d F Y', strtotime($review['wedding_date'])) ?></p>
                            <p class="text-[#9CA3AF] mt-2 italic">"<?= htmlspecialchars($review['review']) ?>"</p>
                        </div>
                        <div>
                            <span class="inline-block px-2 py-1 rounded-full text-xs font-medium 
                                <?= $review['status'] == 'approved' ? 'bg-green-900 text-green-300' : ($review['status'] == 'pending' ? 'bg-yellow-900 text-yellow-300' : 'bg-red-900 text-red-300') ?>">
                                <?= $review['status'] == 'approved' ? '✓ Ditampilkan' : ($review['status'] == 'pending' ? '⏳ Menunggu Review' : '✗ Ditolak') ?>
                            </span>
                        </div>
                    </div>
                    <div class="mt-3 text-xs text-[#9CA3AF]">
                        Dikirim: <?= date('d/m/Y H:i', strtotime($review['created_at'])) ?>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Review -->
<div id="reviewModal" class="modal">
    <div class="bg-[#1A1A1A] rounded-2xl p-6 max-w-lg w-full mx-4 border border-[#2A2A2A]">
        <div class="text-center mb-4">
            <span class="text-4xl">⭐</span>
            <h2 class="font-serif text-xl font-bold text-white mt-2">Beri Ulasan</h2>
        </div>
        <form method="POST" id="reviewForm">
            <input type="hidden" name="order_id" id="review_order_id">
            <input type="hidden" name="submit_review" value="1">
            
            <div class="mb-4 p-3 bg-[#252525] rounded-xl">
                <p id="review_package_name" class="font-semibold text-white"></p>
                <p id="review_order_number" class="text-sm text-[#9CA3AF]"></p>
            </div>
            
            <div class="mb-4 text-center">
                <label class="block text-white mb-2">Rating Anda</label>
                <div class="flex justify-center gap-2" id="star_rating">
                    <?php for($i = 1; $i <= 5; $i++): ?>
                    <span class="rating-star cursor-pointer text-3xl text-gray-600 hover:text-yellow-500 transition" data-rating="<?= $i ?>">★</span>
                    <?php endfor; ?>
                </div>
                <input type="hidden" name="rating" id="rating_value" required>
            </div>
            
            <div class="mb-4">
                <label class="block text-white mb-1">Ulasan Anda</label>
                <textarea name="review" id="review_text" rows="5" class="w-full px-4 py-2 border border-[#2A2A2A] rounded-xl bg-[#0A0A0A] text-white focus:outline-none focus:border-[#FFD700]" placeholder="Ceritakan pengalaman Anda menggunakan layanan Prismatic Organizer..."></textarea>
            </div>
            
            <button type="submit" class="w-full bg-gradient-to-r from-[#FFD700] to-[#DAA520] text-[#0A0A0A] font-bold py-2.5 rounded-full hover:shadow-lg transition">
                Kirim Ulasan →
            </button>
            <button type="button" onclick="closeReviewModal()" class="w-full mt-2 text-[#9CA3AF] text-sm">Batal</button>
        </form>
    </div>
</div>

<script>
    let selectedRating = 0;
    
    // Setup star rating
    const stars = document.querySelectorAll('#star_rating .rating-star');
    stars.forEach(star => {
        star.addEventListener('click', function() {
            selectedRating = parseInt(this.getAttribute('data-rating'));
            document.getElementById('rating_value').value = selectedRating;
            stars.forEach((s, i) => {
                if (i < selectedRating) {
                    s.classList.add('active');
                    s.style.color = '#F59E0B';
                } else {
                    s.classList.remove('active');
                    s.style.color = '#4B5563';
                }
            });
        });
    });
    
    function openReviewModal(orderId, packageName, orderNumber) {
        document.getElementById('review_order_id').value = orderId;
        document.getElementById('review_package_name').innerText = packageName;
        document.getElementById('review_order_number').innerText = 'Order #' + orderNumber;
        document.getElementById('review_text').value = '';
        document.getElementById('rating_value').value = '';
        selectedRating = 0;
        stars.forEach(star => {
            star.style.color = '#4B5563';
            star.classList.remove('active');
        });
        document.getElementById('reviewModal').classList.add('active');
    }
    
    function closeReviewModal() {
        document.getElementById('reviewModal').classList.remove('active');
    }
    
    function toggleMobileSidebar() {
        document.querySelector('.sidebar-fixed').classList.toggle('mobile-open');
    }
    
    // Close modal on ESC
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeReviewModal();
        }
    });
</script>
</body>
</html>