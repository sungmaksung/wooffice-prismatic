<?php
$emp_id = $_SESSION['user_id'] ?? 0;
$emp_data = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$emp_data->execute([$emp_id]);
$emp = $emp_data->fetch();

$notif_count = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
$notif_count->execute([$emp_id]);
$notif_count = $notif_count->fetchColumn();

$unread_messages = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE (receiver_id IS NULL OR receiver_id = ?) AND is_read = 0");
$unread_messages->execute([$emp_id]);
$unread_messages = $unread_messages->fetchColumn();

$pending_payments = $pdo->query("SELECT COUNT(*) FROM payments WHERE status = 'pending'")->fetchColumn();
$new_posts = $pdo->query("SELECT COUNT(*) FROM forum_posts WHERE created_at > DATE_SUB(NOW(), INTERVAL 1 DAY)")->fetchColumn();
?>

<aside class="sidebar-glass">
    <div class="sidebar-header">
        <div class="sidebar-logo">
            <div class="logo-mark">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <path d="M20 12V8H4v4M12 4v4M4 4h16v16H4z"/>
                    <path d="M8 16h8M12 12v8"/>
                </svg>
            </div>
            <div>
                <h2>Prismatic</h2>
                <p>Employee Portal</p>
            </div>
        </div>
    </div>

    <div class="sidebar-profile">
        <div class="profile-avatar">
            <img src="../uploads/profiles/<?= $emp['profile_picture'] ?? 'default.png' ?>" 
                 onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($emp['full_name'] ?? 'User') ?>&background=1E3A5F&color=fff'">
            <div class="online-dot"></div>
        </div>
        <div class="profile-info">
            <h4><?= htmlspecialchars($emp['full_name'] ?? 'Employee') ?></h4>
            <p><?= htmlspecialchars($emp['position'] ?? 'Staff') ?></p>
            <span class="join-date">📅 Join: <?= date('d/m/Y', strtotime($emp['created_at'] ?? 'now')) ?></span>
        </div>
    </div>

    <nav class="sidebar-nav">
        <a href="dashboard.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : '' ?>">
            <span class="nav-icon">📊</span>
            <span class="nav-text">Dashboard</span>
        </a>
        
        <a href="clients.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) == 'clients.php' ? 'active' : '' ?>">
            <span class="nav-icon">👥</span>
            <span class="nav-text">Manajemen Client</span>
        </a>
        
        <a href="payment_requests.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) == 'payment_requests.php' ? 'active' : '' ?>">
            <span class="nav-icon">💳</span>
            <span class="nav-text">Permintaan Pembayaran</span>
            <?php if($pending_payments > 0): ?>
            <span class="nav-badge"><?= $pending_payments ?></span>
            <?php endif; ?>
        </a>
        
        <a href="events.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) == 'events.php' ? 'active' : '' ?>">
            <span class="nav-icon">📅</span>
            <span class="nav-text">Daftar Acara</span>
        </a>
        

        
        <a href="forum.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) == 'forum.php' ? 'active' : '' ?>">
            <span class="nav-icon">💬</span>
            <span class="nav-text">Forum Karyawan</span>
            <?php if($new_posts > 0): ?>
            <span class="nav-badge green"><?= $new_posts ?></span>
            <?php endif; ?>
        </a>
        
        <a href="cs.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) == 'cs.php' ? 'active' : '' ?>">
            <span class="nav-icon">🎧</span>
            <span class="nav-text">Customer Service</span>
            <?php if($unread_messages > 0): ?>
            <span class="nav-badge pulse"><?= $unread_messages ?></span>
            <?php endif; ?>
        </a>
        
        <a href="reviews.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) == 'reviews.php' ? 'active' : '' ?>">
            <span class="nav-icon">⭐</span>
            <span class="nav-text">Kelola Ulasan</span>
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
    .sidebar-glass {
        position: fixed;
        top: 0;
        left: 0;
        width: 280px;
        height: 100vh;
        background: linear-gradient(135deg, #0A1628 0%, #0D1F3C 100%);
        backdrop-filter: blur(10px);
        color: #FFFFFF;
        display: flex;
        flex-direction: column;
        box-shadow: 4px 0 20px rgba(0,0,0,0.3);
        z-index: 100;
        overflow-y: auto;
        border-right: 1px solid rgba(59,130,246,0.2);
    }
    
    .main-content {
        margin-left: 280px;
        padding: 24px;
        min-height: 100vh;
        background: #0F172A;
    }
    
    @media (max-width: 768px) {
        .sidebar-glass {
            transform: translateX(-100%);
            transition: transform 0.3s ease;
            z-index: 1000;
        }
        .sidebar-glass.mobile-open {
            transform: translateX(0);
        }
        .main-content {
            margin-left: 0;
            padding: 80px 16px 24px;
        }
        .mobile-menu-toggle {
            display: flex;
            position: fixed;
            top: 16px;
            left: 16px;
            z-index: 101;
            background: #2563EB;
            color: white;
            width: 44px;
            height: 44px;
            border-radius: 12px;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 24px;
        }
    }
    
    .mobile-menu-toggle { display: none; }
    
    .sidebar-header {
        padding: 24px;
        border-bottom: 1px solid rgba(59,130,246,0.2);
    }
    
    .sidebar-logo {
        display: flex;
        align-items: center;
        gap: 12px;
    }
    
    .logo-mark {
        width: 44px;
        height: 44px;
        background: linear-gradient(135deg, #2563EB, #1D4ED8);
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
    }
    
    .sidebar-logo h2 {
        font-family: 'Cormorant Garamond', serif;
        font-size: 1.3rem;
        font-weight: 600;
        margin: 0;
        color: #60A5FA;
    }
    
    .sidebar-logo p {
        font-size: 0.6rem;
        letter-spacing: 0.15em;
        text-transform: uppercase;
        color: rgba(255,255,255,0.4);
        margin: 2px 0 0;
    }
    
    .sidebar-profile {
        padding: 20px;
        display: flex;
        align-items: center;
        gap: 12px;
        border-bottom: 1px solid rgba(59,130,246,0.2);
    }
    
    .profile-avatar {
        position: relative;
        width: 52px;
        height: 52px;
    }
    
    .profile-avatar img {
        width: 100%;
        height: 100%;
        border-radius: 50%;
        object-fit: cover;
        border: 2px solid #3B82F6;
    }
    
    .online-dot {
        position: absolute;
        bottom: 2px;
        right: 2px;
        width: 12px;
        height: 12px;
        background: #22C55E;
        border-radius: 50%;
        border: 2px solid #0D1F3C;
        animation: pulse-green 2s infinite;
    }
    
    @keyframes pulse-green {
        0%, 100% { opacity: 1; transform: scale(1); }
        50% { opacity: 0.6; transform: scale(1.1); }
    }
    
    .profile-info h4 {
        font-size: 0.9rem;
        font-weight: 600;
        margin: 0 0 2px;
        color: #FFFFFF;
    }
    
    .profile-info p {
        font-size: 0.7rem;
        color: rgba(255,255,255,0.5);
        margin: 0;
    }
    
    .join-date {
        display: inline-block;
        font-size: 0.6rem;
        background: rgba(59,130,246,0.2);
        padding: 2px 8px;
        border-radius: 20px;
        margin-top: 6px;
        color: #60A5FA;
    }
    
    .sidebar-nav {
        flex: 1;
        padding: 16px;
        display: flex;
        flex-direction: column;
        gap: 4px;
    }
    
    .nav-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 10px 14px;
        border-radius: 12px;
        color: rgba(255,255,255,0.7);
        text-decoration: none;
        transition: all 0.25s ease;
        position: relative;
    }
    
    .nav-item:hover {
        background: rgba(59,130,246,0.15);
        color: #FFFFFF;
    }
    
    .nav-item.active {
        background: linear-gradient(135deg, #2563EB, #1D4ED8);
        color: #FFFFFF;
        box-shadow: 0 4px 12px rgba(37,99,235,0.3);
    }
    
    .nav-icon {
        font-size: 1.1rem;
        width: 24px;
    }
    
    .nav-text {
        font-size: 0.85rem;
        font-weight: 500;
    }
    
    .nav-badge {
        position: absolute;
        right: 12px;
        background: #EF4444;
        color: white;
        font-size: 0.6rem;
        font-weight: 600;
        padding: 2px 8px;
        border-radius: 20px;
    }
    
    .nav-badge.green {
        background: #22C55E;
    }
    
    .nav-badge.pulse {
        animation: pulse-red 1.5s infinite;
    }
    
    @keyframes pulse-red {
        0%, 100% { opacity: 1; transform: scale(1); }
        50% { opacity: 0.7; transform: scale(1.05); }
    }
    
    .sidebar-footer {
        padding: 16px;
        border-top: 1px solid rgba(59,130,246,0.2);
    }
    
    .logout-btn {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 10px 14px;
        border-radius: 12px;
        color: rgba(255,255,255,0.6);
        text-decoration: none;
        transition: all 0.25s ease;
    }
    
    .logout-btn:hover {
        background: rgba(239,68,68,0.15);
        color: #EF4444;
    }
    
    .sidebar-glass::-webkit-scrollbar { width: 4px; }
    .sidebar-glass::-webkit-scrollbar-track { background: rgba(255,255,255,0.05); }
    .sidebar-glass::-webkit-scrollbar-thumb { background: #3B82F6; border-radius: 4px; }
</style>

<div class="mobile-menu-toggle" onclick="toggleMobileSidebar()">☰</div>
<script>
    function toggleMobileSidebar() {
        document.querySelector('.sidebar-glass').classList.toggle('mobile-open');
    }
</script>