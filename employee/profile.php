<?php
include '../config/database.php';
include 'log_activity.php';
redirectIfNotLoggedIn();
if (!isEmployee()) { header('Location: ../login.php?role=employee'); exit(); }

$user_id = $_SESSION['user_id'];
$user = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$user->execute([$user_id]);
$user = $user->fetch();

$success = '';
$error = '';
$old_data = [];

if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = $_POST['full_name'];
    $phone = $_POST['phone'];
    $email = $_POST['email'];
    $position = $_POST['position'];
    
    // Simpan data lama untuk log
    $old_data = [
        'full_name' => $user['full_name'],
        'phone' => $user['phone'],
        'email' => $user['email'],
        'position' => $user['position']
    ];
    
    // Handle profile picture upload
    $profile_picture = $user['profile_picture'];
    if(isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === 0) {
        $upload_dir = '../uploads/profiles/';
        if(!file_exists($upload_dir)) mkdir($upload_dir, 0777, true);
        $ext = pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION);
        $filename = 'emp_' . $user_id . '_' . time() . '.' . $ext;
        move_uploaded_file($_FILES['profile_picture']['tmp_name'], $upload_dir . $filename);
        $profile_picture = $filename;
        logEmployeeActivity($pdo, "Mengganti foto profile", 'update', 'profile', null, "Foto baru: $filename");
    }
    
    // Handle password change
    if(!empty($_POST['new_password'])) {
        if(md5($_POST['current_password']) === $user['password']) {
            $new_password = md5($_POST['new_password']);
            $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$new_password, $user_id]);
            $success = 'Password berhasil diubah!';
            logEmployeeActivity($pdo, "Mengganti password", 'update', 'profile', null, "Password changed");
        } else {
            $error = 'Password saat ini salah!';
        }
    }
    
    if(!$error) {
        $stmt = $pdo->prepare("UPDATE users SET full_name = ?, phone = ?, email = ?, position = ?, profile_picture = ? WHERE id = ?");
        $stmt->execute([$full_name, $phone, $email, $position, $profile_picture, $user_id]);
        
        // Log perubahan data
        $changes = [];
        if($old_data['full_name'] != $full_name) $changes[] = "Nama: {$old_data['full_name']} → $full_name";
        if($old_data['phone'] != $phone) $changes[] = "No HP: {$old_data['phone']} → $phone";
        if($old_data['email'] != $email) $changes[] = "Email: {$old_data['email']} → $email";
        if($old_data['position'] != $position) $changes[] = "Jabatan: {$old_data['position']} → $position";
        
        if(!empty($changes)) {
            logEmployeeActivity($pdo, "Memperbarui profile: " . implode(', ', $changes), 'update', 'profile', null, implode('; ', $changes));
        }
        
        $_SESSION['user_name'] = $full_name;
        $_SESSION['user_phone'] = $phone;
        $_SESSION['user_email'] = $email;
        $_SESSION['user_position'] = $position;
        $_SESSION['user_photo'] = $profile_picture;
        $success = 'Profile berhasil diupdate!';
        header("Refresh:0");
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Pengaturan Profile - Prismatic Organizer</title>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,300;1,400;1,600&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        * { font-family: 'DM Sans', sans-serif; }
        .font-serif { font-family: 'Cormorant Garamond', serif; }
        .main-content { margin-left: 280px; padding: 24px; min-height: 100vh; background: #0F172A; }
        .profile-card {
            background: linear-gradient(135deg, #1E293B 0%, #0F172A 100%);
            border: 1px solid #334155;
            transition: all 0.3s ease;
        }
        .input-field {
            background: #0F172A;
            border: 1px solid #334155;
            color: white;
            transition: all 0.3s ease;
        }
        .input-field:focus {
            outline: none;
            border-color: #3B82F6;
            box-shadow: 0 0 0 2px rgba(59,130,246,0.2);
        }
        @media (max-width: 768px) { .main-content { margin-left: 0; padding: 80px 16px 24px; } }
    </style>
</head>
<body class="bg-[#0F172A]">

<?php include 'sidebar.php'; ?>

<div class="main-content">
    <div class="max-w-3xl mx-auto">
        <div class="mb-8">
            <h1 class="font-serif text-3xl font-semibold text-[#60A5FA]">⚙️ Pengaturan Profile</h1>
            <p class="text-[#94A3B8] mt-1">Kelola data diri dan informasi akun Anda</p>
        </div>
        
        <?php if($success): ?>
        <div class="mb-6 bg-green-500/20 border border-green-500/30 text-green-400 px-4 py-3 rounded-xl">✅ <?= $success ?></div>
        <?php endif; ?>
        <?php if($error): ?>
        <div class="mb-6 bg-red-500/20 border border-red-500/30 text-red-400 px-4 py-3 rounded-xl">❌ <?= $error ?></div>
        <?php endif; ?>
        
        <div class="profile-card rounded-2xl p-6">
            <form method="POST" enctype="multipart/form-data">
                <div class="flex items-center gap-6 mb-6 pb-6 border-b border-[#334155]">
                    <div class="relative">
                        <img src="../uploads/profiles/<?= $user['profile_picture'] ?>" class="w-24 h-24 rounded-full object-cover border-4 border-blue-500" 
                             onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($user['full_name']) ?>&background=2563EB&color=fff'">
                        <label class="absolute bottom-0 right-0 bg-blue-600 rounded-full p-1.5 cursor-pointer hover:bg-blue-500 transition">
                            <input type="file" name="profile_picture" accept="image/*" class="hidden" onchange="this.form.submit()">
                            <span class="text-white text-xs">📷</span>
                        </label>
                    </div>
                    <div>
                        <p class="font-bold text-xl text-white"><?= htmlspecialchars($user['full_name']) ?></p>
                        <p class="text-gray-400 text-sm">Bergabung: <?= date('d F Y', strtotime($user['created_at'])) ?></p>
                        <p class="text-blue-400 text-xs mt-1">Klik ikon kamera untuk mengganti foto</p>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <label class="block text-gray-300 mb-1">Nama Lengkap</label>
                        <input type="text" name="full_name" value="<?= htmlspecialchars($user['full_name']) ?>" class="input-field w-full px-4 py-2 rounded-lg">
                    </div>
                    <div>
                        <label class="block text-gray-300 mb-1">Jabatan</label>
                        <input type="text" name="position" value="<?= htmlspecialchars($user['position']) ?>" class="input-field w-full px-4 py-2 rounded-lg">
                    </div>
                    <div>
                        <label class="block text-gray-300 mb-1">Email</label>
                        <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" class="input-field w-full px-4 py-2 rounded-lg">
                    </div>
                    <div>
                        <label class="block text-gray-300 mb-1">No HP</label>
                        <input type="tel" name="phone" value="<?= htmlspecialchars($user['phone']) ?>" class="input-field w-full px-4 py-2 rounded-lg">
                    </div>
                </div>
                
                <div class="mt-6 pt-6 border-t border-[#334155]">
                    <h3 class="font-semibold text-white mb-4 flex items-center gap-2">
                        <span class="text-xl">🔒</span> Ganti Password
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-gray-300 mb-1">Password Saat Ini</label>
                            <input type="password" name="current_password" class="input-field w-full px-4 py-2 rounded-lg">
                        </div>
                        <div>
                            <label class="block text-gray-300 mb-1">Password Baru</label>
                            <input type="password" name="new_password" class="input-field w-full px-4 py-2 rounded-lg">
                        </div>
                        <div>
                            <label class="block text-gray-300 mb-1">Konfirmasi Password Baru</label>
                            <input type="password" name="confirm_password" class="input-field w-full px-4 py-2 rounded-lg">
                        </div>
                    </div>
                    <p class="text-xs text-gray-500 mt-2">* Kosongkan jika tidak ingin mengganti password</p>
                </div>
                
                <button type="submit" class="mt-6 bg-gradient-to-r from-blue-600 to-blue-500 text-white px-6 py-2.5 rounded-lg hover:shadow-lg transition font-medium">
                    💾 Simpan Perubahan
                </button>
            </form>
        </div>
    </div>
</div>

<script>
    function toggleMobileSidebar() {
        document.querySelector('.sidebar-glass').classList.toggle('mobile-open');
    }
</script>
</body>
</html>