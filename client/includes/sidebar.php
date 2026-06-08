<?php
$user_id = $_SESSION['user_id'];
$user = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$user->execute([$user_id]);
$user = $user->fetch();

$unread_messages = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE (receiver_id = ? OR (is_broadcast = 1 AND receiver_id IS NULL)) AND is_read = 0");
$unread_messages->execute([$user_id]);
$unread_messages = $unread_messages->fetchColumn();

$days_to_wedding = '-';
if ($user['wedding_date']) {
    $days = ceil((strtotime($user['wedding_date']) - time()) / 86400);
    $days_to_wedding = $days > 0 ? "H-$days" : ($days == 0 ? "Hari H!" : "Wedding passed");
}
?>

<aside class="sidebar-fixed">
    <div class="sidebar-header">
        <div class="sidebar-logo">
            <div class="logo-mark">
                <img src="../uploads/logo/icon.png" alt="Prismatic" onerror="this.style.display='none'; this.parentElement.style.background='linear-gradient(135deg, #FFD700, #DAA520)'; this.parentElement.innerHTML='💕'">
            </div>
            <div>
                <h2>Prismatic</h2>
                <p>Client Portal</p>
            </div>
        </div>
    </div>

    <div class="sidebar-profile">
        <div class="profile-avatar">
            <img src="../uploads/profiles/<?= $user['profile_picture'] ?>" 
                 onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($user['full_name']) ?>&background=FFD700&color=000'">
            <div class="online-dot"></div>
        </div>
        <div class="profile-info">
            <h4><?= $user['full_name'] ?></h4>
            <p><?= $user['couple_name'] ?? $user['full_name'] ?></p>
            <span class="wedding-countdown"><?= $days_to_wedding ?></span>
        </div>
    </div>

    <nav class="sidebar-nav">
        <a href="dashboard.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : '' ?>">
            <span class="nav-icon">🏠</span>
            <span class="nav-text">Dashboard</span>
        </a>
        
        <a href="packages.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) == 'packages.php' ? 'active' : '' ?>">
            <span class="nav-icon">📦</span>
            <span class="nav-text">Pesan Paket</span>
        </a>
        
        <a href="orders.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) == 'orders.php' ? 'active' : '' ?>">
            <span class="nav-icon">📋</span>
            <span class="nav-text">Pesanan Saya</span>
        </a>

        <a href="reviews.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) == 'reviews.php' ? 'active' : '' ?>">
            <span class="nav-icon">⭐</span>
            <span class="nav-text">Ulasan</span>
            <?php
            $can_review_count = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE client_id = ? AND can_review = 1 AND reviewed = 0");
            $can_review_count->execute([$user_id]);
            $can_review = $can_review_count->fetchColumn();
            if($can_review > 0):
            ?>
            <span class="nav-badge"><?= $can_review ?></span>
            <?php endif; ?>
        </a>
        
        <a href="chat.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) == 'chat.php' ? 'active' : '' ?>">
            <span class="nav-icon">💬</span>
            <span class="nav-text">Chat CS</span>
            <?php if($unread_messages > 0): ?>
            <span class="nav-badge"><?= $unread_messages ?></span>
            <?php endif; ?>
        </a>
        
        <a href="profile.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : '' ?>">
            <span class="nav-icon">⚙️</span>
            <span class="nav-text">Pengaturan</span>
        </a>
    </nav>

    <div class="sidebar-footer">
        <a href="../logout.php" class="logout-btn">
            <span>🚪</span>
            <span>Logout</span>
        </a>
    </div>
</aside>

<style>
    .sidebar-fixed {
        position: fixed;
        top: 0;
        left: 0;
        width: 280px;
        height: 100vh;
        background: linear-gradient(135deg, #0A0A0A 0%, #1A1A1A 100%);
        color: #FFFFFF;
        display: flex;
        flex-direction: column;
        box-shadow: 8px 0 32px rgba(0,0,0,0.5);
        z-index: 100;
        overflow-y: auto;
        border-right: 1px solid #2A2A2A;
    }
    
    .main-content {
        margin-left: 280px;
        padding: 32px;
        min-height: 100vh;
        background: #0A0A0A;
    }
    
    @media (max-width: 768px) {
        .sidebar-fixed {
            transform: translateX(-100%);
            transition: transform 0.3s ease;
            z-index: 1000;
        }
        .sidebar-fixed.mobile-open {
            transform: translateX(0);
        }
        .main-content {
            margin-left: 0;
            padding: 80px 20px 32px;
        }
        .mobile-menu-toggle {
            display: flex;
            position: fixed;
            top: 16px;
            left: 16px;
            z-index: 101;
            background: #FFD700;
            color: #0A0A0A;
            width: 44px;
            height: 44px;
            border-radius: 12px;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 24px;
            font-weight: bold;
        }
    }
    
    .mobile-menu-toggle {
        display: none;
    }
    
    .sidebar-header {
        padding: 28px 24px;
        border-bottom: 1px solid rgba(255,215,0,0.1);
    }
    .sidebar-logo {
        display: flex;
        align-items: center;
        gap: 12px;
    }
    .logo-mark {
        width: 44px;
        height: 44px;
    }
    .sidebar-logo h2 {
        font-family: 'Cormorant Garamond', serif;
        font-size: 1.4rem;
        font-weight: 600;
        margin: 0;
        color: #FFD700;
    }
    .sidebar-logo p {
        font-size: 0.65rem;
        letter-spacing: 0.2em;
        text-transform: uppercase;
        color: rgba(255,255,255,0.45);
        margin: 2px 0 0;
    }
    .sidebar-profile {
        padding: 24px;
        display: flex;
        align-items: center;
        gap: 14px;
        border-bottom: 1px solid rgba(255,215,0,0.1);
    }
    .profile-avatar {
        position: relative;
        width: 56px;
        height: 56px;
    }
    .profile-avatar img {
        width: 100%;
        height: 100%;
        border-radius: 50%;
        object-fit: cover;
        border: 2px solid #FFD700;
    }
    .online-dot {
        position: absolute;
        bottom: 2px;
        right: 2px;
        width: 12px;
        height: 12px;
        background: #22C55E;
        border-radius: 50%;
        border: 2px solid #1A1A1A;
    }
    .profile-info h4 {
        font-size: 0.95rem;
        font-weight: 600;
        margin: 0 0 2px;
        color: #FFFFFF;
    }
    .profile-info p {
        font-size: 0.75rem;
        color: rgba(255,255,255,0.6);
        margin: 0;
    }
    .wedding-countdown {
        display: inline-block;
        font-size: 0.7rem;
        background: rgba(255,215,0,0.2);
        padding: 2px 8px;
        border-radius: 20px;
        margin-top: 6px;
        color: #FFD700;
    }
    .sidebar-nav {
        flex: 1;
        padding: 20px 16px;
        display: flex;
        flex-direction: column;
        gap: 6px;
    }
    .nav-item {
        display: flex;
        align-items: center;
        gap: 14px;
        padding: 12px 16px;
        border-radius: 14px;
        color: rgba(255,255,255,0.7);
        text-decoration: none;
        transition: all 0.25s ease;
        position: relative;
    }
    .nav-item:hover {
        background: rgba(255,215,0,0.1);
        color: #FFD700;
    }
    .nav-item.active {
        background: linear-gradient(135deg, #FFD700, #DAA520);
        color: #0A0A0A;
        box-shadow: 0 4px 12px rgba(255,215,0,0.3);
    }
    .nav-icon {
        font-size: 1.2rem;
        width: 28px;
    }
    .nav-text {
        font-size: 0.85rem;
        font-weight: 500;
    }
    .nav-badge {
        position: absolute;
        right: 16px;
        background: #EF4444;
        color: white;
        font-size: 0.65rem;
        font-weight: 600;
        padding: 2px 8px;
        border-radius: 20px;
        animation: pulse 1.5s infinite;
    }
    @keyframes pulse {
        0%, 100% { opacity: 1; transform: scale(1); }
        50% { opacity: 0.7; transform: scale(1.05); }
    }
    .sidebar-footer {
        padding: 20px 16px;
        border-top: 1px solid rgba(255,215,0,0.1);
    }
    .logout-btn {
        display: flex;
        align-items: center;
        gap: 14px;
        padding: 12px 16px;
        border-radius: 14px;
        color: rgba(255,255,255,0.6);
        text-decoration: none;
        transition: all 0.25s ease;
    }
    .logout-btn:hover {
        background: rgba(239,68,68,0.15);
        color: #EF4444;
    }
    .sidebar-fixed::-webkit-scrollbar {
        width: 4px;
    }
    .sidebar-fixed::-webkit-scrollbar-track {
        background: rgba(255,255,255,0.05);
    }
    .sidebar-fixed::-webkit-scrollbar-thumb {
        background: #FFD700;
        border-radius: 4px;
    }
</style>

<div class="mobile-menu-toggle" onclick="toggleMobileSidebar()">☰</div>
<script>
    function toggleMobileSidebar() {
        document.querySelector('.sidebar-fixed').classList.toggle('mobile-open');
    }
</script>