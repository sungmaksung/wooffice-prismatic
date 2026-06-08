<?php
$admin_name = $_SESSION['admin_name'] ?? 'Admin';
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <style>
        /* ADMIN TOP NAVIGATION STYLES */
        .admin-top-nav {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            background: rgba(10, 10, 10, 0.8);
            backdrop-filter: blur(16px);
            border-bottom: 1px solid rgba(139, 92, 246, 0.2);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
        }
        
        .admin-nav-container {
            max-width: 1600px;
            margin: 0 auto;
            padding: 0 24px;
        }
        
        .admin-nav-wrapper {
            display: flex;
            align-items: center;
            justify-content: space-between;
            height: 70px;
        }
        
        /* Logo Section */
        .admin-logo {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .admin-logo-icon {
            width: 42px;
            height: 42px;
            background: linear-gradient(135deg, #8B5CF6, #6D28D9);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            box-shadow: 0 4px 15px rgba(139, 92, 246, 0.4);
            animation: pulse-glow 2s infinite;
        }
        
        @keyframes pulse-glow {
            0%, 100% { box-shadow: 0 4px 15px rgba(139, 92, 246, 0.4); }
            50% { box-shadow: 0 4px 25px rgba(139, 92, 246, 0.7); }
        }
        
        .admin-logo-text h1 {
            font-family: 'Cormorant Garamond', serif;
            font-size: 1.4rem;
            font-weight: 600;
            color: #A78BFA;
            margin: 0;
            line-height: 1.2;
        }
        
        .admin-logo-text p {
            font-size: 0.6rem;
            letter-spacing: 0.15em;
            text-transform: uppercase;
            color: rgba(255, 255, 255, 0.45);
            margin: 0;
        }
        
        /* Navigation Menu */
        .admin-nav-menu {
            display: flex;
            align-items: center;
            gap: 8px;
            background: rgba(30, 41, 59, 0.5);
            backdrop-filter: blur(8px);
            padding: 6px 12px;
            border-radius: 50px;
            border: 1px solid rgba(139, 92, 246, 0.2);
        }
        
        .admin-nav-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 18px;
            border-radius: 40px;
            color: rgba(255, 255, 255, 0.7);
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 500;
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            position: relative;
            overflow: hidden;
        }
        
        .admin-nav-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, #8B5CF6, #6D28D9);
            border-radius: 40px;
            opacity: 0;
            transition: opacity 0.3s ease;
            z-index: -1;
        }
        
        .admin-nav-item:hover {
            color: white;
            transform: translateY(-2px);
        }
        
        .admin-nav-item:hover::before {
            opacity: 0.15;
        }
        
        .admin-nav-item.active {
            background: linear-gradient(135deg, #8B5CF6, #6D28D9);
            color: white;
            box-shadow: 0 4px 12px rgba(139, 92, 246, 0.4);
        }
        
        .admin-nav-item.active::before {
            opacity: 1;
        }
        
        .nav-icon {
            font-size: 1.1rem;
        }
        
        /* Right Section - Profile */
        .admin-profile-section {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .admin-notif-bell {
            position: relative;
            cursor: pointer;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(30, 41, 59, 0.6);
            border-radius: 50%;
            transition: all 0.3s;
        }
        
        .admin-notif-bell:hover {
            background: rgba(139, 92, 246, 0.2);
            transform: scale(1.05);
        }
        
        .admin-notif-dot {
            position: absolute;
            top: 8px;
            right: 8px;
            width: 10px;
            height: 10px;
            background: #EF4444;
            border-radius: 50%;
            animation: pulse-red 1.5s infinite;
        }
        
        @keyframes pulse-red {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.6; transform: scale(1.2); }
        }
        
        .admin-profile {
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
            padding: 6px 16px 6px 8px;
            border-radius: 50px;
            background: rgba(30, 41, 59, 0.5);
            backdrop-filter: blur(8px);
            border: 1px solid rgba(139, 92, 246, 0.2);
            transition: all 0.3s;
        }
        
        .admin-profile:hover {
            background: rgba(139, 92, 246, 0.15);
            border-color: rgba(139, 92, 246, 0.5);
        }
        
        .admin-avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #8B5CF6, #6D28D9);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 1rem;
        }
        
        .admin-info {
            text-align: right;
        }
        
        .admin-info-name {
            font-size: 0.85rem;
            font-weight: 600;
            color: white;
            margin: 0;
            line-height: 1.2;
        }
        
        .admin-info-role {
            font-size: 0.65rem;
            color: #A78BFA;
            margin: 0;
        }
        
        /* MAIN CONTENT OFFSET */
        .admin-main-content {
            margin-top: 70px;
            padding: 24px;
            min-height: calc(100vh - 70px);
            background: #0F172A;
        }
        
        /* Mobile Responsive */
        @media (max-width: 1024px) {
            .admin-nav-menu {
                position: fixed;
                top: 70px;
                left: 0;
                right: 0;
                background: rgba(10, 10, 10, 0.95);
                backdrop-filter: blur(20px);
                flex-direction: column;
                align-items: stretch;
                padding: 16px;
                border-radius: 0;
                border-top: 1px solid rgba(139, 92, 246, 0.2);
                transform: translateY(-100%);
                transition: transform 0.3s ease;
                z-index: 999;
            }
            
            .admin-nav-menu.mobile-open {
                transform: translateY(0);
            }
            
            .admin-nav-item {
                justify-content: center;
                padding: 12px;
            }
            
            .mobile-menu-btn {
                display: flex;
                align-items: center;
                justify-content: center;
                width: 44px;
                height: 44px;
                background: rgba(139, 92, 246, 0.2);
                border-radius: 12px;
                cursor: pointer;
                font-size: 24px;
            }
        }
        
        @media (min-width: 1025px) {
            .mobile-menu-btn {
                display: none;
            }
        }
        
        /* Dropdown Menu */
        .admin-dropdown {
            position: absolute;
            top: 70px;
            right: 24px;
            background: #1E293B;
            border: 1px solid #334155;
            border-radius: 16px;
            padding: 8px 0;
            min-width: 200px;
            z-index: 1001;
            display: none;
            backdrop-filter: blur(12px);
        }
        
        .admin-dropdown.show {
            display: block;
            animation: fadeIn 0.2s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .admin-dropdown a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 16px;
            color: #94A3B8;
            text-decoration: none;
            transition: all 0.2s;
        }
        
        .admin-dropdown a:hover {
            background: #2D3A5E;
            color: white;
        }
        
        .dropdown-divider {
            height: 1px;
            background: #334155;
            margin: 8px 0;
        }
    </style>
</head>
<body>

<!-- TOP NAVIGATION BAR -->
<nav class="admin-top-nav">
    <div class="admin-nav-container">
        <div class="admin-nav-wrapper">
            <!-- Logo -->
           
            
            <!-- Desktop Navigation Menu -->
            <div class="admin-nav-menu" id="adminNavMenu">
                <a href="index.php" class="admin-nav-item <?= $current_page == 'index.php' ? 'active' : '' ?>">
                    <span class="nav-icon">📊</span>
                    <span>Dashboard</span>
                </a>
                <a href="activities.php" class="admin-nav-item <?= $current_page == 'activities.php' ? 'active' : '' ?>">
                    <span class="nav-icon">📜</span>
                    <span>Log Aktivitas</span>
                </a>
                <a href="employees.php" class="admin-nav-item <?= $current_page == 'employees.php' ? 'active' : '' ?>">
                    <span class="nav-icon">👥</span>
                    <span>Manajemen Karyawan</span>
                </a>
                <a href="database.php" class="admin-nav-item <?= $current_page == 'database.php' ? 'active' : '' ?>">
                    <span class="nav-icon">🗄️</span>
                    <span>Database Stats</span>
                </a>
                <a href="packages.php" class="admin-nav-item <?= $current_page == 'packages.php' ? 'active' : '' ?>">
                    <span class="nav-icon">📦</span>
                    <span>Manajemen Paket</span>
                </a>
                <a href="settings.php" class="admin-nav-item <?= $current_page == 'settings.php' ? 'active' : '' ?>">
                    <span class="nav-icon">⚙️</span>
                    <span>Pengaturan</span>
                </a>
                <a href="backup.php" class="admin-nav-item <?= $current_page == 'backup.php' ? 'active' : '' ?>">
                    <span class="nav-icon">💾</span>
                    <span>Backup</span>
                </a>
                <a href="profile.php" class="admin-nav-item <?= $current_page == 'profile.php' ? 'active' : '' ?>">
                    <span class="nav-icon">👤</span>
                    <span>Profile</span>
                </a>
            </div>
            
            <!-- Right Section -->
            <div class="admin-profile-section">
                <div class="admin-notif-bell" id="notifBell">
                    <span>🔔</span>
                    <div class="admin-notif-dot"></div>
                </div>
                
                <div class="admin-profile" id="adminProfileBtn">
                    <div class="admin-avatar">
                        <?= substr($admin_name, 0, 1) ?>
                    </div>
                    <div class="admin-info">
                        <p class="admin-info-name"><?= htmlspecialchars($admin_name) ?></p>
                        <p class="admin-info-role">Super Admin</p>
                    </div>
                    <span>▼</span>
                </div>
            </div>
            
            <!-- Mobile Menu Button -->
            <div class="mobile-menu-btn" onclick="toggleMobileMenu()">
                <span>☰</span>
            </div>
        </div>
    </div>
</nav>

<!-- Dropdown Menu -->
<div id="adminDropdown" class="admin-dropdown">
    <a href="profile.php">
        <span>👤</span> Profile Saya
    </a>
    <a href="settings.php">
        <span>⚙️</span> Pengaturan
    </a>
    <div class="dropdown-divider"></div>
    <a href="logout.php" style="color: #EF4444;">
        <span>🚪</span> Logout
    </a>
</div>



<script>
    // Toggle mobile menu
    function toggleMobileMenu() {
        const menu = document.getElementById('adminNavMenu');
        menu.classList.toggle('mobile-open');
    }
    
    // Toggle dropdown
    const profileBtn = document.getElementById('adminProfileBtn');
    const dropdown = document.getElementById('adminDropdown');
    
    if(profileBtn) {
        profileBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            dropdown.classList.toggle('show');
        });
    }
    
    // Close dropdown when clicking outside
    document.addEventListener('click', function(e) {
        if(!profileBtn?.contains(e.target) && !dropdown?.contains(e.target)) {
            dropdown?.classList.remove('show');
        }
    });
    
    // Close mobile menu on window resize
    window.addEventListener('resize', function() {
        if(window.innerWidth > 1024) {
            const menu = document.getElementById('adminNavMenu');
            menu?.classList.remove('mobile-open');
        }
    });
</script>
</body>
</html>