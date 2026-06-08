<?php
include '../config/database.php';
include 'log_activity.php';
redirectIfNotLoggedIn();
if (!isEmployee()) { header('Location: ../login.php?role=employee'); exit(); }

$user_id = $_SESSION['user_id'];

// Handle approval/rejection
if(isset($_GET['action']) && isset($_GET['id'])) {
    $review_id = $_GET['id'];
    $action = $_GET['action'];
    
    // Get review details for notification
    $review = $pdo->prepare("SELECT r.*, u.full_name, u.couple_name, p.name as package_name FROM reviews r JOIN users u ON r.client_id = u.id JOIN packages p ON r.package_id = p.id WHERE r.id = ?");
    $review->execute([$review_id]);
    $review = $review->fetch();
    
    if($action == 'approve') {
        $pdo->prepare("UPDATE reviews SET status = 'approved' WHERE id = ?")->execute([$review_id]);
        addNotification($review['client_id'], '⭐ Ulasan Disetujui', 'Ulasan Anda untuk paket ' . $review['package_name'] . ' telah disetujui dan ditampilkan di website! Terima kasih atas ulasannya 💕', 'review', 'client/reviews.php');
        logEmployeeActivity($pdo, "Menyetujui ulasan ID: $review_id dari client " . ($review['couple_name'] ?? $review['full_name']), 'approve', 'review', $review_id, "Rating: {$review['rating']} bintang");
    } elseif($action == 'reject') {
        $pdo->prepare("UPDATE reviews SET status = 'rejected' WHERE id = ?")->execute([$review_id]);
        addNotification($review['client_id'], '❌ Ulasan Ditolak', 'Ulasan Anda untuk paket ' . $review['package_name'] . ' ditolak. Silakan hubungi CS untuk informasi lebih lanjut.', 'review', 'client/reviews.php');
        logEmployeeActivity($pdo, "Menolak ulasan ID: $review_id dari client " . ($review['couple_name'] ?? $review['full_name']), 'reject', 'review', $review_id, "Rating: {$review['rating']} bintang");
    }
    
    header("Location: reviews.php");
    exit();
}

// Get all reviews
$reviews = $pdo->query("
    SELECT r.*, u.full_name, u.couple_name, u.profile_picture, p.name as package_name, o.order_number
    FROM reviews r 
    JOIN users u ON r.client_id = u.id 
    JOIN packages p ON r.package_id = p.id 
    JOIN orders o ON r.order_id = o.id
    ORDER BY 
        CASE WHEN r.status = 'pending' THEN 0 ELSE 1 END,
        r.created_at DESC
")->fetchAll();

// Count stats
$total_reviews = count($reviews);
$pending_count = $pdo->query("SELECT COUNT(*) FROM reviews WHERE status = 'pending'")->fetchColumn();
$approved_count = $pdo->query("SELECT COUNT(*) FROM reviews WHERE status = 'approved'")->fetchColumn();
$rejected_count = $pdo->query("SELECT COUNT(*) FROM reviews WHERE status = 'rejected'")->fetchColumn();
$avg_rating = $pdo->query("SELECT AVG(rating) FROM reviews WHERE status = 'approved'")->fetchColumn();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Ulasan - Prismatic Organizer</title>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,300;1,400;1,600&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        * { font-family: 'DM Sans', sans-serif; }
        .font-serif { font-family: 'Cormorant Garamond', serif; }
        .main-content { margin-left: 280px; padding: 24px; min-height: 100vh; background: #0F172A; }
        .stat-card { background: linear-gradient(135deg, #1E293B 0%, #0F172A 100%); border: 1px solid #334155; transition: all 0.3s ease; }
        .stat-card:hover { transform: translateY(-4px); border-color: #3B82F6; }
        .review-card { background: #1E293B; border: 1px solid #334155; transition: all 0.3s ease; }
        .review-card:hover { border-color: #3B82F6; transform: translateY(-2px); }
        .status-pending { background: #F59E0B20; color: #F59E0B; border: 1px solid #F59E0B30; }
        .status-approved { background: #22C55E20; color: #22C55E; border: 1px solid #22C55E30; }
        .status-rejected { background: #EF444420; color: #EF4444; border: 1px solid #EF444430; }
        @media (max-width: 768px) { .main-content { margin-left: 0; padding: 80px 16px 24px; } }
    </style>
</head>
<body class="bg-[#0F172A]">

<div class="flex">
    <?php include 'sidebar.php'; ?>
    
    <main class="main-content flex-1">
        <!-- Header -->
        <div class="mb-8">
            <h1 class="font-serif text-3xl font-semibold text-[#60A5FA]">⭐ Kelola Ulasan</h1>
            <p class="text-[#94A3B8] mt-1">Setujui atau tolak ulasan dari client sebelum ditampilkan di website</p>
        </div>
        
        <!-- Stats -->
        <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-8">
            <div class="stat-card rounded-xl p-4">
                <div class="text-2xl mb-1">📊</div>
                <div class="text-2xl font-bold text-white"><?= $total_reviews ?></div>
                <div class="text-xs text-gray-400">Total Ulasan</div>
            </div>
            <div class="stat-card rounded-xl p-4">
                <div class="text-2xl mb-1">⏳</div>
                <div class="text-2xl font-bold text-yellow-400"><?= $pending_count ?></div>
                <div class="text-xs text-gray-400">Menunggu</div>
            </div>
            <div class="stat-card rounded-xl p-4">
                <div class="text-2xl mb-1">✅</div>
                <div class="text-2xl font-bold text-green-400"><?= $approved_count ?></div>
                <div class="text-xs text-gray-400">Disetujui</div>
            </div>
            <div class="stat-card rounded-xl p-4">
                <div class="text-2xl mb-1">❌</div>
                <div class="text-2xl font-bold text-red-400"><?= $rejected_count ?></div>
                <div class="text-xs text-gray-400">Ditolak</div>
            </div>
            <div class="stat-card rounded-xl p-4">
                <div class="text-2xl mb-1">⭐</div>
                <div class="text-2xl font-bold text-yellow-400"><?= number_format($avg_rating, 1) ?></div>
                <div class="text-xs text-gray-400">Rata-rata Rating</div>
            </div>
        </div>
        
        <!-- Reviews List -->
        <div class="space-y-4">
            <?php if(count($reviews) == 0): ?>
            <div class="bg-[#1E293B] rounded-2xl p-12 text-center border border-[#334155]">
                <span class="text-6xl mb-4 block">⭐</span>
                <h3 class="text-xl font-semibold text-white mb-2">Belum Ada Ulasan</h3>
                <p class="text-gray-400">Belum ada ulasan dari client yang masuk</p>
            </div>
            <?php else: ?>
                <?php foreach($reviews as $review): ?>
                <div class="review-card rounded-2xl overflow-hidden">
                    <div class="p-5">
                        <div class="flex justify-between items-start flex-wrap gap-4">
                            <!-- Left Section -->
                            <div class="flex-1">
                                <div class="flex items-center gap-3 mb-3">
                                    <img src="../uploads/profiles/<?= $review['profile_picture'] ?>" class="w-10 h-10 rounded-full object-cover border-2 border-blue-500" onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($review['full_name']) ?>&background=2563EB&color=fff'">
                                    <div>
                                        <p class="font-semibold text-white"><?= htmlspecialchars($review['couple_name'] ?? $review['full_name']) ?></p>
                                        <p class="text-xs text-gray-400">Order #<?= $review['order_number'] ?> • <?= $review['package_name'] ?></p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-1 mb-2">
                                    <?php for($i = 1; $i <= 5; $i++): ?>
                                    <span class="text-xl <?= $i <= $review['rating'] ? 'text-yellow-400' : 'text-gray-600' ?>">★</span>
                                    <?php endfor; ?>
                                </div>
                                <p class="text-gray-300 leading-relaxed">"<?= nl2br(htmlspecialchars($review['review'])) ?>"</p>
                                <p class="text-xs text-gray-500 mt-3">📅 Dikirim: <?= date('d F Y H:i', strtotime($review['created_at'])) ?></p>
                            </div>
                            
                            <!-- Right Section - Status & Actions -->
                            <div class="text-right min-w-[140px]">
                                <div class="mb-3">
                                    <?php if($review['status'] == 'pending'): ?>
                                    <span class="status-pending inline-block px-3 py-1 rounded-full text-xs font-semibold">⏳ Menunggu</span>
                                    <?php elseif($review['status'] == 'approved'): ?>
                                    <span class="status-approved inline-block px-3 py-1 rounded-full text-xs font-semibold">✓ Disetujui</span>
                                    <?php else: ?>
                                    <span class="status-rejected inline-block px-3 py-1 rounded-full text-xs font-semibold">✗ Ditolak</span>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if($review['status'] == 'pending'): ?>
                                <div class="flex gap-2 justify-end">
                                    <a href="?action=approve&id=<?= $review['id'] ?>" class="bg-green-600 hover:bg-green-500 text-white px-4 py-2 rounded-lg text-sm transition flex items-center gap-1" onclick="return confirm('Setujui ulasan ini? Ulasan akan tampil di halaman home.')">
                                        ✅ Setujui
                                    </a>
                                    <a href="?action=reject&id=<?= $review['id'] ?>" class="bg-red-600 hover:bg-red-500 text-white px-4 py-2 rounded-lg text-sm transition flex items-center gap-1" onclick="return confirm('Tolak ulasan ini? Ulasan tidak akan tampil di halaman home.')">
                                        ❌ Tolak
                                    </a>
                                </div>
                                <?php elseif($review['status'] == 'approved'): ?>
                                <div class="text-xs text-green-400 flex items-center justify-end gap-1">
                                    <span>✓ Telah ditampilkan di website</span>
                                </div>
                                <?php else: ?>
                                <div class="text-xs text-red-400 flex items-center justify-end gap-1">
                                    <span>✗ Tidak ditampilkan</span>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Approval Info (if approved) -->
                        <?php if($review['status'] == 'approved' && $review['updated_at'] != $review['created_at']): ?>
                        <div class="mt-3 pt-3 border-t border-[#334155] text-xs text-gray-500">
                            Disetujui pada: <?= date('d F Y H:i', strtotime($review['updated_at'])) ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>
</div>

<script>
    function toggleMobileSidebar() {
        document.querySelector('.sidebar-glass').classList.toggle('mobile-open');
    }
</script>
</body>
</html>