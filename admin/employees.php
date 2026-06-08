<?php
session_start();
require_once '../config/database.php';

// Fungsi log untuk admin
function logAdminActivity($pdo, $action, $action_type, $target_type = null, $target_id = null, $details = null) {
    if (!isset($_SESSION['admin_id'])) return;
    
    $admin_id = $_SESSION['admin_id'];
    $admin_name = $_SESSION['admin_name'] ?? 'Admin';
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
    
    $stmt = $pdo->prepare("INSERT INTO employee_activities (employee_id, employee_name, action, action_type, target_type, target_id, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$admin_id, $admin_name, $action, $action_type, $target_type, $target_id, $details, $ip_address, $user_agent]);
}

if(!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$message = '';
$error = '';

// Handle Tambah Karyawan
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_employee'])) {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $password = md5($_POST['password']);
    $position = trim($_POST['position']);
    
    $check = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $check->execute([$email]);
    if($check->fetch()) {
        $error = 'Email sudah terdaftar!';
    } else {
        $stmt = $pdo->prepare("INSERT INTO users (full_name, email, phone, password, role, position, status) VALUES (?, ?, ?, ?, 'employee', ?, 'active')");
        if($stmt->execute([$full_name, $email, $phone, $password, $position])) {
            $message = 'Karyawan berhasil ditambahkan!';
            logAdminActivity($pdo, "Menambah karyawan baru: $full_name", 'create', 'employee', $pdo->lastInsertId(), "Email: $email, Position: $position");
        } else {
            $error = 'Gagal menambahkan karyawan!';
        }
    }
}

// Handle Edit Karyawan
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_employee'])) {
    $id = $_POST['id'];
    $full_name = trim($_POST['full_name']);
    $phone = trim($_POST['phone']);
    $position = trim($_POST['position']);
    $status = $_POST['status'];
    
    $stmt = $pdo->prepare("UPDATE users SET full_name = ?, phone = ?, position = ?, status = ? WHERE id = ? AND role = 'employee'");
    if($stmt->execute([$full_name, $phone, $position, $status, $id])) {
        $message = 'Data karyawan berhasil diupdate!';
        logAdminActivity($pdo, "Mengedit karyawan ID: $id", 'update', 'employee', $id, "Nama: $full_name, Status: $status");
    } else {
        $error = 'Gagal mengupdate karyawan!';
    }
}

// Handle Reset Password
if(isset($_GET['reset_password'])) {
    $id = $_GET['reset_password'];
    $new_password = md5('admin123');
    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ? AND role = 'employee'");
    if($stmt->execute([$new_password, $id])) {
        $message = 'Password berhasil direset menjadi: admin123';
        logAdminActivity($pdo, "Reset password karyawan ID: $id", 'update', 'employee', $id, "Password direset ke default");
    } else {
        $error = 'Gagal reset password!';
    }
    header("Location: employees.php?msg=" . urlencode($message));
    exit();
}

// Handle Delete Karyawan
if(isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $check = $pdo->prepare("SELECT full_name FROM users WHERE id = ? AND role = 'employee'");
    $check->execute([$id]);
    $emp = $check->fetch();
    if($emp) {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'employee'");
        if($stmt->execute([$id])) {
            $message = "Karyawan {$emp['full_name']} berhasil dihapus!";
            logAdminActivity($pdo, "Menghapus karyawan: {$emp['full_name']}", 'delete', 'employee', $id, null);
        } else {
            $error = 'Gagal menghapus karyawan!';
        }
    }
    header("Location: employees.php?msg=" . urlencode($message));
    exit();
}

// Get all employees
$employees = $pdo->query("SELECT * FROM users WHERE role = 'employee' ORDER BY created_at DESC")->fetchAll();
$msg = $_GET['msg'] ?? '';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Manajemen Karyawan - Admin Panel</title>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,300;1,400;1,600&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        * { font-family: 'DM Sans', sans-serif; }
        .glass-card { background: rgba(30, 41, 59, 0.6); backdrop-filter: blur(12px); border: 1px solid rgba(139, 92, 246, 0.2); }
        .modal { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.7); backdrop-filter: blur(8px); z-index: 1000; align-items: center; justify-content: center; }
        .modal.active { display: flex; }
        .modal-content { background: #1E293B; border: 1px solid #334155; border-radius: 24px; max-width: 500px; width: 90%; animation: fadeIn 0.3s ease; }
        @keyframes fadeIn { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }
    </style>
</head>
<body class="bg-[#0F172A]">

<?php include 'sidebar.php'; ?>

<div class="admin-main-content">
    <div class="flex justify-between items-center mb-8">
        <div>
            <h1 class="font-serif text-3xl font-semibold text-[#A78BFA]">👥 Manajemen Karyawan</h1>
            <p class="text-[#94A3B8] mt-1">Kelola akun karyawan Prismatic Organizer</p>
        </div>
        <button onclick="openAddModal()" class="bg-gradient-to-r from-purple-600 to-indigo-600 text-white px-5 py-2 rounded-xl hover:shadow-lg transition">
            + Tambah Karyawan
        </button>
    </div>
    
    <?php if($msg): ?>
    <div class="mb-4 p-3 bg-green-500/20 border border-green-500/30 text-green-400 rounded-xl">✅ <?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>
    <?php if($error): ?>
    <div class="mb-4 p-3 bg-red-500/20 border border-red-500/30 text-red-400 rounded-xl">❌ <?= $error ?></div>
    <?php endif; ?>
    
    <!-- Employees Table -->
    <div class="glass-card rounded-2xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-[#0F172A] border-b border-[#334155]">
                    <tr class="text-left text-gray-400 text-sm">
                        <th class="p-4">ID</th>
                        <th>Nama</th>
                        <th>Email</th>
                        <th>No HP</th>
                        <th>Jabatan</th>
                        <th>Status</th>
                        <th>Bergabung</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($employees as $emp): ?>
                    <tr class="border-b border-[#334155] hover:bg-[#2D3A5E] transition">
                        <td class="p-4 text-gray-400">#<?= $emp['id'] ?></td>
                        <td class="p-4 font-medium text-white"><?= htmlspecialchars($emp['full_name']) ?></td>
                        <td class="p-4 text-gray-300"><?= $emp['email'] ?></td>
                        <td class="p-4 text-gray-300"><?= $emp['phone'] ?></td>
                        <td class="p-4 text-gray-300"><?= htmlspecialchars($emp['position'] ?? '-') ?></td>
                        <td class="p-4">
                            <span class="px-2 py-1 rounded-full text-xs font-medium <?= $emp['status'] == 'active' ? 'bg-green-500/20 text-green-400' : 'bg-red-500/20 text-red-400' ?>">
                                <?= $emp['status'] == 'active' ? '✅ Aktif' : '❌ Nonaktif' ?>
                            </span>
                        </td>
                        <td class="p-4 text-gray-400 text-sm"><?= date('d/m/Y', strtotime($emp['created_at'])) ?></td>
                        <td class="p-4">
                            <div class="flex gap-2">
                                <button onclick="openEditModal(<?= $emp['id'] ?>, '<?= addslashes($emp['full_name']) ?>', '<?= $emp['phone'] ?>', '<?= addslashes($emp['position']) ?>', '<?= $emp['status'] ?>')" class="text-blue-400 hover:text-blue-300">✏️ Edit</button>
                                <a href="?reset_password=<?= $emp['id'] ?>" class="text-yellow-400 hover:text-yellow-300" onclick="return confirm('Reset password karyawan ini? Password baru: admin123')">🔑 Reset</a>
                                <a href="?delete=<?= $emp['id'] ?>" class="text-red-400 hover:text-red-300" onclick="return confirm('Hapus karyawan ini?')">🗑️ Hapus</a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Tambah Karyawan -->
<div id="addModal" class="modal">
    <div class="modal-content p-6">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-bold text-white">➕ Tambah Karyawan</h2>
            <button onclick="closeAddModal()" class="text-gray-400 hover:text-white text-2xl">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="add_employee" value="1">
            <div class="space-y-4">
                <div><label class="block text-gray-300 mb-1">Nama Lengkap</label><input type="text" name="full_name" required class="w-full px-4 py-2 bg-[#0F172A] border border-[#334155] rounded-lg text-white"></div>
                <div><label class="block text-gray-300 mb-1">Email</label><input type="email" name="email" required class="w-full px-4 py-2 bg-[#0F172A] border border-[#334155] rounded-lg text-white"></div>
                <div><label class="block text-gray-300 mb-1">No HP</label><input type="tel" name="phone" required class="w-full px-4 py-2 bg-[#0F172A] border border-[#334155] rounded-lg text-white"></div>
                <div><label class="block text-gray-300 mb-1">Password</label><input type="password" name="password" required class="w-full px-4 py-2 bg-[#0F172A] border border-[#334155] rounded-lg text-white" placeholder="Minimal 6 karakter"></div>
                <div><label class="block text-gray-300 mb-1">Jabatan</label><input type="text" name="position" class="w-full px-4 py-2 bg-[#0F172A] border border-[#334155] rounded-lg text-white" placeholder="Contoh: Event Coordinator"></div>
            </div>
            <div class="flex gap-3 mt-6">
                <button type="submit" class="flex-1 bg-purple-600 text-white py-2 rounded-lg hover:bg-purple-500">Simpan</button>
                <button type="button" onclick="closeAddModal()" class="flex-1 bg-gray-600 text-white py-2 rounded-lg hover:bg-gray-500">Batal</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Edit Karyawan -->
<div id="editModal" class="modal">
    <div class="modal-content p-6">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-bold text-white">✏️ Edit Karyawan</h2>
            <button onclick="closeEditModal()" class="text-gray-400 hover:text-white text-2xl">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="edit_employee" value="1">
            <input type="hidden" name="id" id="edit_id">
            <div class="space-y-4">
                <div><label class="block text-gray-300 mb-1">Nama Lengkap</label><input type="text" name="full_name" id="edit_name" required class="w-full px-4 py-2 bg-[#0F172A] border border-[#334155] rounded-lg text-white"></div>
                <div><label class="block text-gray-300 mb-1">No HP</label><input type="tel" name="phone" id="edit_phone" required class="w-full px-4 py-2 bg-[#0F172A] border border-[#334155] rounded-lg text-white"></div>
                <div><label class="block text-gray-300 mb-1">Jabatan</label><input type="text" name="position" id="edit_position" class="w-full px-4 py-2 bg-[#0F172A] border border-[#334155] rounded-lg text-white"></div>
                <div><label class="block text-gray-300 mb-1">Status</label>
                    <select name="status" id="edit_status" class="w-full px-4 py-2 bg-[#0F172A] border border-[#334155] rounded-lg text-white">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
            </div>
            <div class="flex gap-3 mt-6">
                <button type="submit" class="flex-1 bg-purple-600 text-white py-2 rounded-lg hover:bg-purple-500">Simpan</button>
                <button type="button" onclick="closeEditModal()" class="flex-1 bg-gray-600 text-white py-2 rounded-lg hover:bg-gray-500">Batal</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openAddModal() { document.getElementById('addModal').classList.add('active'); }
    function closeAddModal() { document.getElementById('addModal').classList.remove('active'); }
    function openEditModal(id, name, phone, position, status) {
        document.getElementById('edit_id').value = id;
        document.getElementById('edit_name').value = name;
        document.getElementById('edit_phone').value = phone;
        document.getElementById('edit_position').value = position || '';
        document.getElementById('edit_status').value = status;
        document.getElementById('editModal').classList.add('active');
    }
    function closeEditModal() { document.getElementById('editModal').classList.remove('active'); }
    document.getElementById('addModal')?.addEventListener('click', function(e) { if(e.target === this) closeAddModal(); });
    document.getElementById('editModal')?.addEventListener('click', function(e) { if(e.target === this) closeEditModal(); });
</script>
</body>
</html>