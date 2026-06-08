<?php
include '../config/database.php';
include 'log_activity.php';
redirectIfNotLoggedIn();
if (!isEmployee()) { header('Location: ../login.php?role=employee'); exit(); }

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

// Handle delete message
if(isset($_POST['ajax_delete_message'])) {
    $message_id = $_POST['message_id'];
    
    // Cek apakah pesan ini milik employee yang sedang login
    $check = $pdo->prepare("SELECT sender_id, message FROM messages WHERE id = ?");
    $check->execute([$message_id]);
    $message = $check->fetch();
    
    if($message && $message['sender_id'] == $user_id) {
        $pdo->prepare("DELETE FROM messages WHERE id = ?")->execute([$message_id]);
        logEmployeeActivity($pdo, "Menghapus pesan ID: $message_id", 'delete', 'message', $message_id, "Konten: " . substr($message['message'], 0, 50));
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Tidak dapat menghapus pesan ini']);
    }
    exit();
}

// Handle AJAX send message
if(isset($_POST['ajax_send'])) {
    $type = $_POST['type'];
    $message = $_POST['message'];
    $client_id = $_POST['client_id'] ?? 0;
    
    if(trim($message) == '') {
        echo json_encode(['success' => false, 'error' => 'Pesan kosong']);
        exit();
    }
    
    if($type == 'broadcast') {
        $stmt = $pdo->prepare("INSERT INTO messages (sender_id, message, is_broadcast, receiver_id) VALUES (?, ?, 1, NULL)");
        $stmt->execute([$user_id, $message]);
        $new_id = $pdo->lastInsertId();
        
        logEmployeeActivity($pdo, "Mengirim broadcast: " . substr($message, 0, 50), 'create', 'broadcast', null, $message);
        
        $clients = $pdo->query("SELECT id FROM users WHERE role = 'client' AND status = 'active'")->fetchAll();
        foreach($clients as $client) {
            addNotification($client['id'], '📢 Pesan dari Customer Service', substr($message, 0, 50), 'chat', 'client/chat.php');
        }
        
        echo json_encode([
            'success' => true, 
            'type' => 'broadcast', 
            'message' => $message, 
            'sender_name' => $user_name, 
            'sender_id' => $user_id,
            'time' => date('H:i'),
            'profile_picture' => $_SESSION['user_photo'] ?? '',
            'message_id' => $new_id
        ]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, message, is_broadcast) VALUES (?, ?, ?, 0)");
        $stmt->execute([$user_id, $client_id, $message]);
        $new_id = $pdo->lastInsertId();
        
        logEmployeeActivity($pdo, "Mengirim pesan private ke client ID $client_id: " . substr($message, 0, 50), 'create', 'private_chat', $client_id, $message);
        
        addNotification($client_id, '💬 Balasan dari CS', substr($message, 0, 50), 'chat', 'client/chat.php');
        
        echo json_encode([
            'success' => true, 
            'type' => 'private', 
            'message' => $message, 
            'sender_name' => $user_name,
            'sender_id' => $user_id,
            'time' => date('H:i'),
            'profile_picture' => $_SESSION['user_photo'] ?? '',
            'message_id' => $new_id
        ]);
    }
    exit();
}

// Handle get messages (AJAX refresh)
if(isset($_GET['ajax_get_messages'])) {
    $selected_client_id = $_GET['client_id'] ?? 0;
    
    if($selected_client_id) {
        $pdo->prepare("UPDATE messages SET is_read = 1 WHERE sender_id = ? AND is_read = 0")->execute([$selected_client_id]);
        
        $stmt = $pdo->prepare("
            SELECT m.*, u.full_name, u.profile_picture, u.role,
                   DATE_FORMAT(m.created_at, '%H:%i') as time
            FROM messages m 
            JOIN users u ON m.sender_id = u.id 
            WHERE (m.sender_id = ? AND m.is_broadcast = 1)
               OR (m.sender_id = ? AND m.receiver_id = ?)
               OR (m.sender_id = ? AND m.receiver_id = ?)
            ORDER BY m.created_at ASC
        ");
        $stmt->execute([$selected_client_id, $user_id, $selected_client_id, $selected_client_id, $user_id]);
        $messages = $stmt->fetchAll();
        
        echo json_encode(['messages' => $messages, 'current_user_id' => $user_id]);
    } else {
        $broadcastMessages = $pdo->query("
            SELECT m.*, u.full_name, u.profile_picture, u.role,
                   DATE_FORMAT(m.created_at, '%H:%i') as time
            FROM messages m 
            JOIN users u ON m.sender_id = u.id 
            WHERE m.is_broadcast = 1 
            ORDER BY m.created_at ASC
        ")->fetchAll();
        
        echo json_encode(['messages' => $broadcastMessages, 'current_user_id' => $user_id, 'is_broadcast' => true]);
    }
    exit();
}

// Handle get clients (AJAX refresh)
if(isset($_GET['ajax_get_clients'])) {
    $clients = $pdo->query("
        SELECT u.*, 
               (SELECT COUNT(*) FROM messages WHERE sender_id = u.id AND is_read = 0 AND is_broadcast = 1) as unread
        FROM users u 
        WHERE u.role = 'client' 
        ORDER BY 
            CASE WHEN (SELECT COUNT(*) FROM messages WHERE sender_id = u.id AND is_read = 0) > 0 THEN 1 ELSE 0 END DESC,
            u.created_at DESC
    ")->fetchAll();
    
    foreach($clients as &$c) {
        if(empty($c['profile_picture'])) $c['profile_picture'] = '';
        if(empty($c['couple_name'])) $c['couple_name'] = $c['full_name'];
    }
    
    echo json_encode(['clients' => $clients]);
    exit();
}

$selected_client_id = $_GET['client_id'] ?? 0;
$selected_client = null;
if($selected_client_id) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'client'");
    $stmt->execute([$selected_client_id]);
    $selected_client = $stmt->fetch();
}

$clients = $pdo->query("
    SELECT u.*, 
           (SELECT COUNT(*) FROM messages WHERE sender_id = u.id AND is_read = 0 AND is_broadcast = 1) as unread
    FROM users u 
    WHERE u.role = 'client' 
    ORDER BY 
        CASE WHEN (SELECT COUNT(*) FROM messages WHERE sender_id = u.id AND is_read = 0) > 0 THEN 1 ELSE 0 END DESC,
        u.created_at DESC
")->fetchAll();

$broadcastMessages = $pdo->query("
    SELECT m.*, u.full_name, u.profile_picture, u.role,
           DATE_FORMAT(m.created_at, '%H:%i') as time
    FROM messages m 
    JOIN users u ON m.sender_id = u.id 
    WHERE m.is_broadcast = 1 
    ORDER BY m.created_at ASC
")->fetchAll();

$privateMessages = [];
if($selected_client) {
    $pdo->prepare("UPDATE messages SET is_read = 1 WHERE sender_id = ? AND is_read = 0")->execute([$selected_client_id]);
    
    $stmt = $pdo->prepare("
        SELECT m.*, u.full_name, u.profile_picture, u.role,
               DATE_FORMAT(m.created_at, '%H:%i') as time
        FROM messages m 
        JOIN users u ON m.sender_id = u.id 
        WHERE (m.sender_id = ? AND m.is_broadcast = 1)
           OR (m.sender_id = ? AND m.receiver_id = ?)
           OR (m.sender_id = ? AND m.receiver_id = ?)
        ORDER BY m.created_at ASC
    ");
    $stmt->execute([$selected_client_id, $user_id, $selected_client_id, $selected_client_id, $user_id]);
    $privateMessages = $stmt->fetchAll();
}

$sent = $_GET['sent'] ?? '';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Customer Service - Prismatic Organizer</title>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,300;1,400;1,600&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        * { font-family: 'DM Sans', sans-serif; }
        .main-content { margin-left: 280px; padding: 24px; min-height: 100vh; background: #0F172A; }
        
        .client-list-container {
            height: calc(100vh - 280px);
            overflow-y: auto;
            overflow-x: hidden;
        }
        
        .chat-messages {
            height: calc(100vh - 280px);
            overflow-y: auto;
            scroll-behavior: smooth;
        }
        
        .client-list-container::-webkit-scrollbar {
            width: 5px;
        }
        .client-list-container::-webkit-scrollbar-track {
            background: #1E293B;
            border-radius: 10px;
        }
        .client-list-container::-webkit-scrollbar-thumb {
            background: #3B82F6;
            border-radius: 10px;
        }
        
        .chat-messages::-webkit-scrollbar {
            width: 5px;
        }
        .chat-messages::-webkit-scrollbar-track {
            background: #1E293B;
            border-radius: 10px;
        }
        .chat-messages::-webkit-scrollbar-thumb {
            background: #3B82F6;
            border-radius: 10px;
        }
        
        .message-bubble {
            max-width: 70%;
            word-wrap: break-word;
        }
        
        .message-menu {
            display: none;
            position: absolute;
            right: 0;
            top: 100%;
            background: #1E293B;
            border: 1px solid #334155;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
            z-index: 20;
            min-width: 100px;
        }
        
        .message-menu a, .message-menu button {
            display: block;
            padding: 6px 12px;
            color: #94A3B8;
            text-decoration: none;
            font-size: 12px;
            width: 100%;
            text-align: left;
            background: none;
            border: none;
            cursor: pointer;
        }
        
        .message-menu a:hover, .message-menu button:hover {
            background: #2D3A5E;
            color: white;
        }
        
        .message-item { position: relative; }
        .message-actions { position: absolute; top: 0; right: 0; opacity: 0; transition: opacity 0.2s; }
        .message-item:hover .message-actions { opacity: 1; }
        .delete-message-btn { background: none; border: none; cursor: pointer; font-size: 14px; padding: 2px 6px; border-radius: 4px; color: #94A3B8; }
        .delete-message-btn:hover { background: #EF4444; color: white; }
        
        .client-item:hover { background: #2D3A5E; }
        .client-item-active { background: linear-gradient(135deg, #2563EB, #1D4ED8); color: white; }
        .client-item-active .text-gray-500 { color: #BFDBFE !important; }
        .unread-badge {
            animation: pulse 1s infinite;
            background: #EF4444;
            color: white;
        }
        
        .employee-badge {
            background: #22C55E;
            color: white;
            font-size: 9px;
            padding: 2px 6px;
            border-radius: 20px;
            margin-left: 8px;
            display: inline-block;
        }
        
        @keyframes pulse {
            0%,100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }
        
        @media (max-width: 768px) { 
            .main-content { margin-left: 0; padding: 80px 16px 24px; }
            .client-list-container { height: calc(100vh - 320px); }
            .chat-messages { height: calc(100vh - 320px); }
        }
    </style>
</head>
<body class="bg-[#0F172A]">

<div class="flex">
    <?php include 'sidebar.php'; ?>
    
    <main class="main-content flex-1 overflow-hidden">
   
        
        <?php if($sent == '1'): ?>
        <div class="bg-green-500/20 border border-green-500/30 text-green-400 px-4 py-3 rounded-xl mb-4 flex justify-between items-center">
            <span>✅ Pesan broadcast berhasil dikirim!</span>
            <button onclick="this.parentElement.style.display='none'" class="text-green-400">✕</button>
        </div>
        <?php endif; ?>
        
        <div class="flex gap-4 h-full">
            <!-- Client List Sidebar -->
            <div class="w-80 bg-[#1E293B] rounded-xl border border-[#334155] flex flex-col overflow-hidden">
                <div class="p-4 bg-gradient-to-r from-blue-600 to-blue-500 text-white">
                    <div class="flex justify-between items-center">
                        <span class="font-semibold">👥 Daftar Client</span>
                        <a href="cs.php" class="text-sm text-white/80 hover:text-white">📢 Broadcast</a>
                    </div>
                    <p class="text-xs text-blue-200 mt-1">Klik client untuk chat private</p>
                </div>
                
                <div class="client-list-container" id="clientListContainer">
                    <?php foreach($clients as $client): ?>
                    <a href="cs.php?client_id=<?= $client['id'] ?>" 
                       class="client-item block p-3 border-b border-[#334155] transition <?= ($selected_client_id == $client['id']) ? 'client-item-active' : '' ?>"
                       data-client-id="<?= $client['id'] ?>">
                        <div class="flex items-center gap-3">
                            <div class="relative">
                                <img src="../uploads/profiles/<?= $client['profile_picture'] ?>" 
                                     class="w-12 h-12 rounded-full object-cover border-2 <?= ($selected_client_id == $client['id']) ? 'border-white' : 'border-blue-500' ?>"
                                     onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($client['full_name']) ?>&background=2563EB&color=fff'">
                                <div class="absolute bottom-0 right-0 w-3 h-3 bg-green-500 rounded-full border-2 border-[#1E293B]"></div>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="font-semibold truncate text-white"><?= htmlspecialchars($client['full_name']) ?></p>
                                <p class="text-xs text-gray-400 truncate"><?= htmlspecialchars($client['couple_name'] ?? $client['full_name']) ?></p>
                            </div>
                            <?php if($client['unread'] > 0): ?>
                            <div class="unread-badge text-white text-xs rounded-full w-5 h-5 flex items-center justify-center font-bold">
                                <?= $client['unread'] ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </a>
                    <?php endforeach; ?>
                    <?php if(count($clients) == 0): ?>
                    <div class="p-8 text-center text-gray-400"><span class="text-4xl mb-2 block">👀</span><p>Belum ada client terdaftar</p></div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Chat Area -->
            <div class="flex-1 bg-[#1E293B] rounded-xl border border-[#334155] flex flex-col overflow-hidden">
                <div class="p-4 bg-gradient-to-r from-blue-600 to-blue-500 text-white flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <?php if($selected_client): ?>
                        <img src="../uploads/profiles/<?= $selected_client['profile_picture'] ?>" class="w-10 h-10 rounded-full object-cover border-2 border-white" onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($selected_client['full_name']) ?>&background=fff&color=2563EB'">
                        <div>
                            <p class="font-semibold"><?= htmlspecialchars($selected_client['full_name']) ?></p>
                            <p class="text-xs text-blue-200"><?= htmlspecialchars($selected_client['couple_name'] ?? '') ?></p>
                        </div>
                        <?php else: ?>
                        <div class="flex items-center gap-3"><span class="text-2xl">📢</span><div><p class="font-semibold">Broadcast Message</p><p class="text-xs text-blue-200">Pesan akan dikirim ke semua client</p></div></div>
                        <?php endif; ?>
                    </div>
                    <?php if($selected_client): ?>
                    <div class="flex gap-2"><span class="text-xs bg-white/20 px-2 py-1 rounded-full">🟢 Online</span></div>
                    <?php endif; ?>
                </div>
                
                <div id="chatMessages" class="chat-messages p-4 bg-[#0F172A]">
                    <?php if($selected_client): ?>
                        <?php if(count($privateMessages) == 0): ?>
                        <div class="text-center text-gray-400 py-20"><span class="text-5xl mb-3 block">💬</span><p>Belum ada pesan dengan client ini</p><p class="text-sm">Ketik pesan di bawah untuk memulai chat</p></div>
                        <?php else: ?>
                            <?php foreach($privateMessages as $msg): $isMe = ($msg['sender_id'] == $user_id); ?>
                            <div class="flex <?= $isMe ? 'justify-end' : 'justify-start' ?> mb-3 message-item" data-message-id="<?= $msg['id'] ?>" data-sender-id="<?= $msg['sender_id'] ?>">
                                <div class="flex items-end gap-2 <?= $isMe ? 'flex-row-reverse' : '' ?>" style="max-width: 70%;">
                                    <?php if(!$isMe): ?>
                                    <img src="../uploads/profiles/<?= $msg['profile_picture'] ?>" class="w-8 h-8 rounded-full object-cover mb-1" onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($msg['full_name']) ?>&background=gray'">
                                    <?php endif; ?>
                                    <div class="<?= $isMe ? 'bg-blue-600 text-white rounded-tl-xl rounded-tr-xl rounded-bl-xl' : 'bg-[#1E293B] text-gray-200 rounded-tl-xl rounded-tr-xl rounded-br-xl border border-[#334155]' ?> px-4 py-2 shadow-sm relative">
                                        <p class="text-xs <?= $isMe ? 'text-blue-200' : 'text-gray-400' ?> mb-1">
                                            <?= $isMe ? 'Anda' : $msg['full_name'] ?> • <?= $msg['time'] ?>
                                            <?php if($msg['role'] == 'employee' && !$isMe): ?>
                                            <span class="employee-badge">Employee</span>
                                            <?php endif; ?>
                                        </p>
                                        <p class="whitespace-pre-wrap"><?= nl2br(htmlspecialchars($msg['message'])) ?></p>
                                        <?php if($isMe): ?>
                                        <div class="message-actions">
                                            <button class="delete-message-btn" onclick="deleteMessage(<?= $msg['id'] ?>, this)" title="Hapus pesan">🗑️</button>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    <?php else: ?>
                        <?php if(count($broadcastMessages) == 0): ?>
                        <div class="text-center text-gray-400 py-20"><span class="text-5xl mb-3 block">📢</span><p>Belum ada pesan broadcast</p><p class="text-sm">Kirim pesan broadcast untuk semua client</p></div>
                        <?php else: ?>
                            <?php foreach($broadcastMessages as $msg): ?>
                            <div class="flex justify-start mb-3 message-item" data-message-id="<?= $msg['id'] ?>" data-sender-id="<?= $msg['sender_id'] ?>">
                                <div class="flex items-end gap-2" style="max-width: 70%;">
                                    <img src="../uploads/profiles/<?= $msg['profile_picture'] ?>" class="w-8 h-8 rounded-full object-cover mb-1" onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($msg['full_name']) ?>&background=gray'">
                                    <div class="bg-[#1E293B] text-gray-200 rounded-tl-xl rounded-tr-xl rounded-br-xl px-4 py-2 border border-[#334155] shadow-sm relative">
                                        <p class="text-xs text-gray-400 mb-1">
                                            <?= htmlspecialchars($msg['full_name']) ?> • <?= $msg['time'] ?>
                                            <?php if($msg['role'] == 'employee'): ?>
                                            <span class="employee-badge">Employee</span>
                                            <?php endif; ?>
                                        </p>
                                        <p class="whitespace-pre-wrap"><?= nl2br(htmlspecialchars($msg['message'])) ?></p>
                                        <?php if($msg['sender_id'] == $user_id): ?>
                                        <div class="message-actions">
                                            <button class="delete-message-btn" onclick="deleteMessage(<?= $msg['id'] ?>, this)" title="Hapus pesan">🗑️</button>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
                
                <div class="p-4 bg-[#1E293B] border-t border-[#334155]">
                    <div class="flex gap-2">
                        <textarea id="messageText" rows="2" class="flex-1 px-4 py-2 bg-[#0F172A] border border-[#334155] rounded-xl resize-none focus:outline-none focus:border-blue-500 text-white placeholder-gray-500" placeholder="Ketik pesan... (Enter untuk kirim, Shift+Enter untuk new line)"></textarea>
                        <button id="sendBtn" class="bg-blue-600 hover:bg-blue-500 text-white px-6 py-2 rounded-xl transition font-semibold">Kirim →</button>
                    </div>
                    <p class="text-xs text-gray-500 mt-2">💡 <?= $selected_client ? "Pesan akan dikirim secara PRIVATE ke client terpilih" : "Pesan akan dikirim secara BROADCAST ke SEMUA client" ?></p>
                </div>
            </div>
        </div>
    </main>
</div>

<script>
    let currentClientId = <?= $selected_client_id ?: 0 ?>;
    let isBroadcastMode = (currentClientId === 0);
    let isTyping = false;
    let currentUserId = <?= $user_id ?>;
    
    const chatContainer = document.getElementById('chatMessages');
    const messageInput = document.getElementById('messageText');
    const sendBtn = document.getElementById('sendBtn');
    
    function scrollToBottom() {
        if(chatContainer) {
            chatContainer.scrollTop = chatContainer.scrollHeight;
        }
    }
    
    // Delete message function
    function deleteMessage(messageId, btnElement) {
        if(!confirm('Yakin ingin menghapus pesan ini?')) return;
        
        $.post('', { ajax_delete_message: 1, message_id: messageId }, function(response) {
            if(response.success) {
                // Hapus elemen pesan dari DOM
                const messageElement = $(btnElement).closest('.message-item');
                messageElement.fadeOut(300, function() { $(this).remove(); });
            } else {
                alert(response.error || 'Gagal menghapus pesan');
            }
        }, 'json').fail(function() {
            alert('Gagal menghapus pesan');
        });
    }
    
    // Fungsi untuk menambah pesan baru ke chat (LANGSUNG TAMPIL)
    function appendNewMessage(messageData) {
        const isMe = true;
        const employeeBadge = messageData.type === 'broadcast' ? '<span class="employee-badge">Employee</span>' : '';
        
        const messageHtml = `
            <div class="flex justify-end mb-3 message-item temp-message" data-message-id="${messageData.message_id}" data-sender-id="${currentUserId}">
                <div class="flex items-end gap-2 flex-row-reverse" style="max-width: 70%;">
                    <div class="bg-blue-600 text-white rounded-tl-xl rounded-tr-xl rounded-bl-xl px-4 py-2 shadow-sm relative">
                        <p class="text-xs text-blue-200 mb-1">Anda • ${messageData.time} ${employeeBadge}</p>
                        <p class="whitespace-pre-wrap">${escapeHtml(messageData.message)}</p>
                        <div class="message-actions">
                            <button class="delete-message-btn" onclick="deleteMessage(${messageData.message_id}, this)" title="Hapus pesan">🗑️</button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        chatContainer.insertAdjacentHTML('beforeend', messageHtml);
        scrollToBottom();
    }
    
    // Load messages
    function loadMessages(keepExisting = true) {
        let url = '?ajax_get_messages=1';
        if(currentClientId > 0) url += '&client_id=' + currentClientId;
        
        $.get(url, function(data) {
            if(data.messages) {
                let html = '';
                if(data.is_broadcast) {
                    data.messages.forEach(msg => {
                        const isMe = (msg.sender_id == currentUserId);
                        const employeeBadge = (msg.role == 'employee' && !isMe) ? '<span class="employee-badge">Employee</span>' : '';
                        html += `
                            <div class="flex ${isMe ? 'justify-end' : 'justify-start'} mb-3 message-item" data-message-id="${msg.id}" data-sender-id="${msg.sender_id}">
                                <div class="flex items-end gap-2 ${isMe ? 'flex-row-reverse' : ''}" style="max-width: 70%;">
                                    ${!isMe ? `<img src="../uploads/profiles/${msg.profile_picture || ''}" class="w-8 h-8 rounded-full object-cover mb-1" onerror="this.src='https://ui-avatars.com/api/?name=${encodeURIComponent(msg.full_name)}&background=gray'">` : ''}
                                    <div class="${isMe ? 'bg-blue-600 text-white rounded-tl-xl rounded-tr-xl rounded-bl-xl' : 'bg-[#1E293B] text-gray-200 rounded-tl-xl rounded-tr-xl rounded-br-xl border border-[#334155]'} px-4 py-2 shadow-sm relative">
                                        <p class="text-xs ${isMe ? 'text-blue-200' : 'text-gray-400'} mb-1">${isMe ? 'Anda' : escapeHtml(msg.full_name)} • ${msg.time} ${employeeBadge}</p>
                                        <p class="whitespace-pre-wrap">${escapeHtml(msg.message)}</p>
                                        ${isMe ? `<div class="message-actions"><button class="delete-message-btn" onclick="deleteMessage(${msg.id}, this)" title="Hapus pesan">🗑️</button></div>` : ''}
                                    </div>
                                </div>
                            </div>
                        `;
                    });
                } else {
                    data.messages.forEach(msg => {
                        const isMe = (msg.sender_id == data.current_user_id);
                        const employeeBadge = (msg.role == 'employee' && !isMe) ? '<span class="employee-badge">Employee</span>' : '';
                        html += `
                            <div class="flex ${isMe ? 'justify-end' : 'justify-start'} mb-3 message-item" data-message-id="${msg.id}" data-sender-id="${msg.sender_id}">
                                <div class="flex items-end gap-2 ${isMe ? 'flex-row-reverse' : ''}" style="max-width: 70%;">
                                    ${!isMe ? `<img src="../uploads/profiles/${msg.profile_picture || ''}" class="w-8 h-8 rounded-full object-cover mb-1" onerror="this.src='https://ui-avatars.com/api/?name=${encodeURIComponent(msg.full_name)}&background=gray'">` : ''}
                                    <div class="${isMe ? 'bg-blue-600 text-white rounded-tl-xl rounded-tr-xl rounded-bl-xl' : 'bg-[#1E293B] text-gray-200 rounded-tl-xl rounded-tr-xl rounded-br-xl border border-[#334155]'} px-4 py-2 shadow-sm relative">
                                        <p class="text-xs ${isMe ? 'text-blue-200' : 'text-gray-400'} mb-1">${isMe ? 'Anda' : escapeHtml(msg.full_name)} • ${msg.time} ${employeeBadge}</p>
                                        <p class="whitespace-pre-wrap">${escapeHtml(msg.message)}</p>
                                        ${isMe ? `<div class="message-actions"><button class="delete-message-btn" onclick="deleteMessage(${msg.id}, this)" title="Hapus pesan">🗑️</button></div>` : ''}
                                    </div>
                                </div>
                            </div>
                        `;
                    });
                }
                
                if(keepExisting) {
                    const currentMessageCount = document.querySelectorAll('#chatMessages .message-item').length;
                    if(data.messages.length > currentMessageCount) {
                        chatContainer.innerHTML = html || '<div class="text-center text-gray-400 py-20">💬 Belum ada pesan</div>';
                        scrollToBottom();
                    }
                } else {
                    chatContainer.innerHTML = html || '<div class="text-center text-gray-400 py-20">💬 Belum ada pesan</div>';
                    scrollToBottom();
                }
            }
        });
    }
    
    function loadClients() {
        $.get('?ajax_get_clients=1', function(data) {
            if(data.clients) {
                let html = '';
                data.clients.forEach(client => {
                    const isActive = (client.id == currentClientId);
                    html += `
                        <a href="cs.php?client_id=${client.id}" class="client-item block p-3 border-b border-[#334155] transition ${isActive ? 'client-item-active' : ''}" data-client-id="${client.id}">
                            <div class="flex items-center gap-3">
                                <div class="relative">
                                    <img src="../uploads/profiles/${client.profile_picture || ''}" class="w-12 h-12 rounded-full object-cover border-2 ${isActive ? 'border-white' : 'border-blue-500'}" onerror="this.src='https://ui-avatars.com/api/?name=${encodeURIComponent(client.full_name)}&background=2563EB&color=fff'">
                                    <div class="absolute bottom-0 right-0 w-3 h-3 bg-green-500 rounded-full border-2 border-[#1E293B]"></div>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="font-semibold truncate text-white">${escapeHtml(client.full_name)}</p>
                                    <p class="text-xs text-gray-400 truncate">${escapeHtml(client.couple_name || client.full_name)}</p>
                                </div>
                                ${client.unread > 0 ? `<div class="unread-badge text-white text-xs rounded-full w-5 h-5 flex items-center justify-center font-bold">${client.unread}</div>` : ''}
                            </div>
                        </a>
                    `;
                });
                document.getElementById('clientListContainer').innerHTML = html || '<div class="p-8 text-center text-gray-400">👀 Belum ada client</div>';
            }
        });
    }
    
    function sendMessage() {
        const message = messageInput.value.trim();
        if(message === '') return;
        
        const sendData = {
            ajax_send: 1,
            type: isBroadcastMode ? 'broadcast' : 'private',
            message: message
        };
        if(!isBroadcastMode) {
            sendData.client_id = currentClientId;
        }
        
        const currentTime = new Date();
        const timeStr = currentTime.getHours().toString().padStart(2,'0') + ':' + currentTime.getMinutes().toString().padStart(2,'0');
        
        // Optimistic rendering - tampilkan pesan dulu
        const tempId = 'temp_' + Date.now();
        const employeeBadge = isBroadcastMode ? '<span class="employee-badge">Employee</span>' : '';
        
        const tempMessageHtml = `
            <div class="flex justify-end mb-3 message-item temp-message" data-message-id="${tempId}" data-sender-id="${currentUserId}">
                <div class="flex items-end gap-2 flex-row-reverse" style="max-width: 70%;">
                    <div class="bg-blue-600 text-white rounded-tl-xl rounded-tr-xl rounded-bl-xl px-4 py-2 shadow-sm relative">
                        <p class="text-xs text-blue-200 mb-1">Anda • ${timeStr} ${employeeBadge}</p>
                        <p class="whitespace-pre-wrap">${escapeHtml(message)}</p>
                        <div class="message-actions">
                            <button class="delete-message-btn" onclick="deleteMessage('${tempId}', this)" title="Hapus pesan">🗑️</button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        chatContainer.insertAdjacentHTML('beforeend', tempMessageHtml);
        scrollToBottom();
        messageInput.value = '';
        
        $.post('', sendData, function(response) {
            if(response.success) {
                // Ganti id temporary dengan id real
                $('.temp-message').removeClass('temp-message').attr('data-message-id', response.message_id);
                // Update button delete dengan id real
                $('.delete-message-btn').attr('onclick', function(i, val) {
                    return val.replace(tempId, response.message_id);
                });
                if(!isBroadcastMode) loadClients();
            } else {
                alert(response.error || 'Gagal mengirim pesan');
                $('.temp-message').remove();
            }
        }, 'json').fail(function() {
            alert('Gagal mengirim pesan');
            $('.temp-message').remove();
        });
    }
    
    function escapeHtml(text) {
        if(!text) return '';
        return String(text).replace(/[&<>]/g, function(m) {
            if(m === '&') return '&amp;';
            if(m === '<') return '&lt;';
            if(m === '>') return '&gt;';
            return m;
        }).replace(/\n/g, '<br>');
    }
    
    messageInput.addEventListener('keydown', function(e) {
        if(e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendMessage();
        }
    });
    
    sendBtn.addEventListener('click', sendMessage);
    messageInput.addEventListener('focus', () => { isTyping = true; });
    messageInput.addEventListener('blur', () => { isTyping = false; });
    
    setInterval(function() {
        if(!isTyping) {
            loadMessages(true);
            loadClients();
        }
    }, 3000);
    
    loadMessages(false);
    scrollToBottom();
    
    function toggleMobileSidebar() { 
        document.querySelector('.sidebar-glass').classList.toggle('mobile-open');
    }
</script>
</body>
</html>