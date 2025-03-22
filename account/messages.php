<?php
$pageTitle = 'Nachrichten';
require_once '../includes/header.php';

// Require login
requireLogin();

$userId = $_SESSION['user_id'];

// Get user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

// Handle message actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['mark_read']) && isset($_POST['message_id'])) {
        $messageId = (int)$_POST['message_id'];
        
        // Verify ownership
        $stmt = $pdo->prepare("SELECT * FROM messages WHERE message_id = ? AND receiver_id = ?");
        $stmt->execute([$messageId, $userId]);
        
        if ($stmt->rowCount() === 1) {
            $pdo->prepare("UPDATE messages SET is_read = 1 WHERE message_id = ?")->execute([$messageId]);
            $_SESSION['success'] = "Nachricht als gelesen markiert.";
        } else {
            $_SESSION['error'] = "Du hast keine Berechtigung, diese Nachricht zu bearbeiten.";
        }
    } elseif (isset($_POST['delete_message']) && isset($_POST['message_id'])) {
        $messageId = (int)$_POST['message_id'];
        
        // Verify ownership
        $stmt = $pdo->prepare("SELECT * FROM messages WHERE message_id = ? AND (sender_id = ? OR receiver_id = ?)");
        $stmt->execute([$messageId, $userId, $userId]);
        
        if ($stmt->rowCount() === 1) {
            $pdo->prepare("DELETE FROM messages WHERE message_id = ?")->execute([$messageId]);
            $_SESSION['success'] = "Nachricht wurde gelöscht.";
        } else {
            $_SESSION['error'] = "Du hast keine Berechtigung, diese Nachricht zu löschen.";
        }
    }
    
    // Redirect to avoid form resubmission
    redirect($_SERVER['PHP_SELF']);
}

// Get messages
$type = isset($_GET['type']) ? sanitizeInput($_GET['type']) : 'inbox';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

if ($type === 'sent') {
    $messagesQuery = "SELECT m.*, 
                     u.username as recipient_name,
                     p.title as part_title, p.part_id
                     FROM messages m
                     JOIN users u ON m.receiver_id = u.user_id
                     LEFT JOIN parts p ON m.part_id = p.part_id
                     WHERE m.sender_id = ?
                     ORDER BY m.sent_date DESC
                     LIMIT ?, ?";
    $countQuery = "SELECT COUNT(*) FROM messages WHERE sender_id = ?";
} else {
    $messagesQuery = "SELECT m.*, 
                     u.username as sender_name,
                     p.title as part_title, p.part_id
                     FROM messages m
                     JOIN users u ON m.sender_id = u.user_id
                     LEFT JOIN parts p ON m.part_id = p.part_id
                     WHERE m.receiver_id = ?
                     ORDER BY m.sent_date DESC
                     LIMIT ?, ?";
    $countQuery = "SELECT COUNT(*) FROM messages WHERE receiver_id = ?";
}

$stmt = $pdo->prepare($messagesQuery);
$stmt->bindValue(1, $userId, PDO::PARAM_INT);
$stmt->bindValue(2, $offset, PDO::PARAM_INT);
$stmt->bindValue(3, $perPage, PDO::PARAM_INT);
$stmt->execute();
$messages = $stmt->fetchAll();

// Get total count for pagination
$countStmt = $pdo->prepare($countQuery);
$countStmt->execute([$userId]);
$totalCount = $countStmt->fetchColumn();
$totalPages = ceil($totalCount / $perPage);

// Get counts for tabs
$inboxCountStmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = ?");
$inboxCountStmt->execute([$userId]);
$inboxCount = $inboxCountStmt->fetchColumn();

$unreadCountStmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0");
$unreadCountStmt->execute([$userId]);
$unreadCount = $unreadCountStmt->fetchColumn();

$sentCountStmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE sender_id = ?");
$sentCountStmt->execute([$userId]);
$sentCount = $sentCountStmt->fetchColumn();
?>

<div class="dashboard-container">
    <div class="dashboard-sidebar">
        <div class="user-info">
            <?php if (!empty($user['profile_image'])): ?>
                <img src="<?php echo SITE_URL . '/' . $user['profile_image']; ?>" alt="<?php echo htmlspecialchars($user['username']); ?>" class="user-avatar">
            <?php else: ?>
                <img src="<?php echo SITE_URL; ?>/assets/images/avatar-placeholder.jpg" alt="<?php echo htmlspecialchars($user['username']); ?>" class="user-avatar">
            <?php endif; ?>
            <h3 class="user-name"><?php echo htmlspecialchars($user['username']); ?></h3>
            <p class="user-email"><?php echo htmlspecialchars($user['email']); ?></p>
            <a href="<?php echo SITE_URL; ?>/account/profile.php" class="btn btn-sm btn-outline">Profil bearbeiten</a>
        </div>
        
        <div class="dashboard-nav">
            <ul>
                <li><a href="<?php echo SITE_URL; ?>/account/dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li><a href="<?php echo SITE_URL; ?>/account/listings.php"><i class="fas fa-list"></i> Meine Inserate</a></li>
                <li>
                    <a href="<?php echo SITE_URL; ?>/account/messages.php" class="active">
                        <i class="fas fa-envelope"></i> Nachrichten
                        <?php if ($unreadCount > 0): ?>
                            <span class="badge"><?php echo $unreadCount; ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li><a href="<?php echo SITE_URL; ?>/account/favorites.php"><i class="fas fa-heart"></i> Favoriten</a></li>
                <li><a href="<?php echo SITE_URL; ?>/account/profile.php"><i class="fas fa-user"></i> Profil bearbeiten</a></li>
                <li><a href="<?php echo SITE_URL; ?>/account/settings.php"><i class="fas fa-cog"></i> Einstellungen</a></li>
                <li><a href="<?php echo SITE_URL; ?>/logout.php"><i class="fas fa-sign-out-alt"></i> Abmelden</a></li>
            </ul>
        </div>
    </div>
    
    <div class="dashboard-content">
        <div class="dashboard-header">
            <h1 class="dashboard-title">Nachrichten</h1>
        </div>
        
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>
        
        <div class="messages-tabs">
            <a href="?type=inbox" class="tab <?php echo ($type === 'inbox') ? 'active' : ''; ?>">
                Posteingang <span class="count">(<?php echo $inboxCount; ?>)</span>
                <?php if ($unreadCount > 0): ?>
                    <span class="unread-badge"><?php echo $unreadCount; ?> ungelesen</span>
                <?php endif; ?>
            </a>
            <a href="?type=sent" class="tab <?php echo ($type === 'sent') ? 'active' : ''; ?>">
                Gesendet <span class="count">(<?php echo $sentCount; ?>)</span>
            </a>
        </div>
        
        <?php if (empty($messages)): ?>
            <div class="empty-state">
                <i class="fas fa-envelope-open fa-3x"></i>
                <h3>Keine Nachrichten gefunden</h3>
                <p>
                    <?php if ($type === 'sent'): ?>
                        Sie haben noch keine Nachrichten gesendet.
                    <?php else: ?>
                        Sie haben noch keine Nachrichten erhalten.
                    <?php endif; ?>
                </p>
            </div>
        <?php else: ?>
            <div class="messages-table-container">
                <table class="messages-table">
                    <thead>
                        <tr>
                            <th>
                                <?php if ($type === 'sent'): ?>
                                    Empfänger
                                <?php else: ?>
                                    Absender
                                <?php endif; ?>
                            </th>
                            <th>Betreff</th>
                            <th>Nachricht</th>
                            <th>Datum</th>
                            <th>Status</th>
                            <th>Aktionen</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($messages as $message): ?>
                            <tr class="<?php echo ($type !== 'sent' && !$message['is_read']) ? 'unread-row' : ''; ?>">
                                <td>
                                    <?php if ($type === 'sent'): ?>
                                        <?php echo htmlspecialchars($message['recipient_name']); ?>
                                    <?php else: ?>
                                        <?php echo htmlspecialchars($message['sender_name']); ?>
                                    <?php endif; ?>
                                </td>
                                <td class="message-subject-cell">
                                    <?php if ($message['part_id']): ?>
                                        <a href="<?php echo SITE_URL; ?>/listing.php?id=<?php echo $message['part_id']; ?>">
                                            <?php echo htmlspecialchars($message['subject'] ?: 'Anfrage: ' . $message['part_title']); ?>
                                        </a>
                                    <?php else: ?>
                                        <?php echo htmlspecialchars($message['subject'] ?: 'Keine Betreff'); ?>
                                    <?php endif; ?>
                                </td>
                                <td class="message-preview-cell">
                                    <?php echo htmlspecialchars(substr($message['message'], 0, 50) . (strlen($message['message']) > 50 ? '...' : '')); ?>
                                </td>
                                <td class="message-date-cell">
                                    <?php echo date('d.m.Y H:i', strtotime($message['sent_date'])); ?>
                                </td>
                                <td class="message-status-cell">
                                    <?php if ($type === 'sent'): ?>
                                        <span class="status-badge status-sent">Gesendet</span>
                                    <?php else: ?>
                                        <?php if ($message['is_read']): ?>
                                            <span class="status-badge status-read">Gelesen</span>
                                        <?php else: ?>
                                            <span class="status-badge status-unread">Ungelesen</span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <td class="message-actions-cell">
                                    <div class="action-buttons">
                                        <a href="<?php echo SITE_URL; ?>/account/view-message.php?id=<?php echo $message['message_id']; ?>" class="btn btn-sm btn-outline" title="Anzeigen">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        
                                        <?php if ($type !== 'sent' && !$message['is_read']): ?>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="message_id" value="<?php echo $message['message_id']; ?>">
                                                <button type="submit" name="mark_read" class="btn btn-sm btn-outline" title="Als gelesen markieren">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        
                                        <?php if ($type !== 'sent'): ?>
                                            <a href="<?php echo SITE_URL; ?>/account/reply-message.php?id=<?php echo $message['message_id']; ?>" class="btn btn-sm btn-outline" title="Antworten">
                                                <i class="fas fa-reply"></i>
                                            </a>
                                        <?php endif; ?>
                                        
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Möchten Sie diese Nachricht wirklich löschen?');">
                                            <input type="hidden" name="message_id" value="<?php echo $message['message_id']; ?>">
                                            <button type="submit" name="delete_message" class="btn btn-sm btn-danger" title="Löschen">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?type=<?php echo $type; ?>&page=<?php echo $page - 1; ?>" class="pagination-link">
                            <i class="fas fa-chevron-left"></i> Zurück
                        </a>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                        <a href="?type=<?php echo $type; ?>&page=<?php echo $i; ?>" class="pagination-link <?php echo ($i == $page) ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <a href="?type=<?php echo $type; ?>&page=<?php echo $page + 1; ?>" class="pagination-link">
                            Weiter <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<style>
/* Messages Page Styles */
.messages-tabs {
    display: flex;
    margin-bottom: 25px;
    border-bottom: 1px solid #eee;
}

.tab {
    padding: 12px 20px;
    color: #7f8c8d;
    text-decoration: none;
    border-bottom: 3px solid transparent;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 10px;
}

.tab:hover {
    color: #3498db;
}

.tab.active {
    color: #3498db;
    border-bottom-color: #3498db;
    font-weight: 600;
}

.count {
    color: #95a5a6;
    font-size: 0.9rem;
}

.unread-badge {
    background-color: #e74c3c;
    color: white;
    padding: 3px 8px;
    border-radius: 10px;
    font-size: 0.75rem;
}

.messages-table-container {
    overflow-x: auto;
    margin-bottom: 30px;
}

.messages-table {
    width: 100%;
    border-collapse: collapse;
}

.messages-table th, .messages-table td {
    padding: 15px;
    text-align: left;
    border-bottom: 1px solid #eee;
}

.messages-table th {
    background-color: #f8f9fa;
    font-weight: 600;
    color: #2c3e50;
}

.messages-table tr:hover {
    background-color: #f8f9fa;
}

.unread-row {
    background-color: #f0f7ff;
    font-weight: 500;
}

.message-subject-cell {
    max-width: 250px;
}

.message-subject-cell a {
    color: #2c3e50;
    text-decoration: none;
    font-weight: 600;
}

.message-subject-cell a:hover {
    color: #3498db;
}

.message-preview-cell {
    max-width: 300px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.message-date-cell {
    white-space: nowrap;
    color: #7f8c8d;
}

.message-status-cell {
    text-align: center;
}

.status-badge {
    display: inline-block;
    padding: 5px 10px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
}

.status-read {
    background-color: #f1f1f1;
    color: #7f8c8d;
}

.status-unread {
    background-color: #e8f4fc;
    color: #3498db;
}

.status-sent {
    background-color: #e8f7f0;
    color: #2ecc71;
}

.message-actions-cell {
    white-space: nowrap;
}

.action-buttons {
    display: flex;
    gap: 5px;
}

.d-inline {
    display: inline;
}

.empty-state {
    text-align: center;
    padding: 50px 0;
    color: #7f8c8d;
}

.empty-state i {
    margin-bottom: 20px;
    color: #bdc3c7;
}

.empty-state h3 {
    margin-bottom: 10px;
    color: #2c3e50;
}

/* Responsive adjustments */
@media (max-width: 992px) {
    .messages-tabs {
        overflow-x: auto;
        white-space: nowrap;
        padding-bottom: 5px;
    }
    
    .tab {
        padding: 10px 15px;
    }
    
    .message-subject-cell, .message-preview-cell {
        max-width: 150px;
    }
}

@media (max-width: 768px) {
    .action-buttons {
        flex-direction: column;
        gap: 5px;
    }
    
    .action-buttons .btn {
        width: 100%;
    }
    
    .messages-table th, .messages-table td {
        padding: 10px;
    }
}
</style>

<?php require_once '../includes/footer.php'; ?>

