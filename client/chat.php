<?php
include '../config/database.php';
redirectIfNotLoggedIn();
if (!isClient()) { header('Location: ../login.php?role=client'); exit(); }

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

$pdo->prepare("UPDATE messages SET is_read = 1 WHERE (receiver_id = ? OR (is_broadcast = 1 AND receiver_id IS NULL)) AND is_read = 0")->execute([$user_id]);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
    $message = trim($_POST['message']);
    if ($message != '') {
        $stmt = $pdo->prepare("INSERT INTO messages (sender_id, message, is_broadcast, receiver_id) VALUES (?, ?, 1, NULL)");
        $stmt->execute([$user_id, $message]);
        
        $employees = $pdo->query("SELECT id FROM users WHERE role = 'employee'")->fetchAll();
        foreach ($employees as $emp) {
            addNotification($emp['id'], '💬 Pesan Baru dari Client', $user_name . ': ' . substr($message, 0, 50), 'chat', 'employee/cs.php');
        }
        header("Location: chat.php?sent=1");
        exit();
    }
}

$messages = $pdo->prepare("
    SELECT m.*, u.full_name, u.profile_picture, u.role,
           DATE_FORMAT(m.created_at, '%H:%i') as time
    FROM messages m 
    JOIN users u ON m.sender_id = u.id 
    WHERE (m.is_broadcast = 1 AND m.receiver_id IS NULL AND u.role = 'employee')
       OR (m.receiver_id = ? AND u.role = 'employee')
       OR (m.sender_id = ?)
    ORDER BY m.created_at ASC
");
$messages->execute([$user_id, $user_id]);
$messages = $messages->fetchAll();

$sent = $_GET['sent'] ?? '';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Chat CS - Prismatic Organizer</title>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,300;1,400;1,600&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        * { font-family: 'DM Sans', sans-serif; }
        .font-serif { font-family: 'Cormorant Garamond', serif; }
        .main-content { margin-left: 280px; padding: 32px; min-height: 100vh; background: #0A0A0A; }
        .chat-container { height: calc(100vh - 280px); overflow-y: auto; scroll-behavior: smooth; }
        .message-bubble { max-width: 70%; word-wrap: break-word; }
        @media (max-width: 768px) { .main-content { margin-left: 0; padding: 80px 20px 32px; } }
    </style>
</head>
<body class="bg-[#0A0A0A]">

<?php include 'includes/sidebar.php'; ?>

<div class="main-content">
    <div class="bg-[#1A1A1A] rounded-2xl border border-[#2A2A2A] overflow-hidden flex flex-col h-[calc(100vh-100px)]">
        <div class="bg-gradient-to-r from-[#FFD700] to-[#DAA520] p-5">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 bg-white/20 rounded-full flex items-center justify-center text-2xl">🎧</div>
                <div>
                    <h1 class="font-serif text-2xl font-semibold text-[#0A0A0A]">Customer Service</h1>
                    <p class="text-[#0A0A0A]/70 text-sm">Tim kami siap membantu Anda 24/7</p>
                </div>
                <?php if($sent == '1'): ?>
                <div class="ml-auto bg-green-500/20 px-4 py-2 rounded-full text-sm text-green-700">✅ Pesan terkirim!</div>
                <?php endif; ?>
            </div>
        </div>
        
        <div id="chatMessages" class="flex-1 chat-container p-5 space-y-4 bg-[#0A0A0A]">
            <?php if(count($messages) == 0): ?>
            <div class="text-center py-20">
                <span class="text-6xl mb-4 block">💬</span>
                <p class="text-[#9CA3AF]">Belum ada pesan. Kirim pesan ke CS!</p>
                <p class="text-sm text-[#FFD700] mt-2">Kami akan membalas secepatnya ✨</p>
            </div>
            <?php else: ?>
                <?php foreach($messages as $msg): ?>
                <?php $isMe = ($msg['sender_id'] == $user_id); ?>
                <div class="flex <?= $isMe ? 'justify-end' : 'justify-start' ?>">
                    <div class="flex items-end gap-2 <?= $isMe ? 'flex-row-reverse' : '' ?>" style="max-width: 75%;">
                        <?php if(!$isMe): ?>
                        <img src="../uploads/profiles/<?= $msg['profile_picture'] ?>" 
                             class="w-8 h-8 rounded-full object-cover mb-1"
                             onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($msg['full_name']) ?>&background=FFD700&color=000'">
                        <?php endif; ?>
                        <div class="<?= $isMe ? 'bg-gradient-to-r from-[#FFD700] to-[#DAA520] text-[#0A0A0A] rounded-tl-xl rounded-tr-xl rounded-bl-xl' : 'bg-[#1A1A1A] text-white rounded-tl-xl rounded-tr-xl rounded-br-xl border border-[#2A2A2A]' ?> px-4 py-2.5">
                            <p class="text-xs <?= $isMe ? 'text-[#0A0A0A]/60' : 'text-[#FFD700]' ?> mb-1">
                                <?= $isMe ? 'Anda' : 'Customer Service' ?> • <?= $msg['time'] ?>
                            </p>
                            <p class="whitespace-pre-wrap"><?= nl2br(htmlspecialchars($msg['message'])) ?></p>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <div class="p-5 bg-[#1A1A1A] border-t border-[#2A2A2A]">
            <form method="POST" id="chatForm" class="flex gap-3">
                <textarea name="message" id="messageText" rows="2" 
                          class="flex-1 px-5 py-3 border border-[#2A2A2A] rounded-2xl resize-none focus:outline-none focus:border-[#FFD700] focus:ring-1 focus:ring-[#FFD700] bg-[#0A0A0A] text-white"
                          placeholder="Ketik pesan... (Enter untuk kirim, Shift+Enter untuk new line)"></textarea>
                <button type="submit" class="bg-gradient-to-r from-[#FFD700] to-[#DAA520] text-[#0A0A0A] font-bold px-8 py-3 rounded-2xl hover:shadow-lg transition">
                    Kirim →
                </button>
            </form>
            <p class="text-xs text-[#9CA3AF] mt-3 flex items-center gap-1">💡 Pesan akan diterima oleh tim Customer Service kami</p>
        </div>
    </div>
</div>

<script>
    const container = document.getElementById('chatMessages');
    if(container) container.scrollTop = container.scrollHeight;
    
    const textarea = document.getElementById('messageText');
    if(textarea) {
        textarea.addEventListener('keydown', function(e) {
            if(e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                document.getElementById('chatForm').submit();
            }
        });
    }
    
    setInterval(() => { location.reload(); }, 8000);
    
    function toggleMobileSidebar() {
        document.querySelector('.sidebar-fixed').classList.toggle('mobile-open');
    }
</script>
</body>
</html>