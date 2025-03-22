<?php
$pageTitle = 'Mein Konto';
require_once '../includes/header.php';

// Require login
requireLogin();

// Get user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Get user's listings
$stmt = $pdo->prepare("
    SELECT p.*, i.file_path, COUNT(m.message_id) as message_count
    FROM parts p
    LEFT JOIN images i ON p.part_id = i.part_id AND i.is_primary = 1
    LEFT JOIN messages m ON p.part_id = m.part_id AND m.receiver_id = ? AND m.is_read = 0
    WHERE p.user_id = ?
    GROUP BY p.part_id
    ORDER BY p.date_posted DESC
");
$stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
$listings = $stmt->fetchAll();

// Get unread messages count
$stmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0");
$stmt->execute([$_SESSION['user_id']]);
$unreadMessages = $stmt->fetchColumn();

// Get favorites count
$stmt = $pdo->prepare("SELECT COUNT(*) FROM favorites WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$favoritesCount = $stmt->fetchColumn();
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
                <li><a href="<?php echo SITE_URL; ?>/account/dashboard.php" class="active"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li><a href="<?php echo SITE_URL; ?>/account/listings.php"><i class="fas fa-list"></i> Meine Inserate</a></li>
                <li>
                    <a href="<?php echo SITE_URL; ?>/account/messages.php">
                        <i class="fas fa-envelope"></i> Nachrichten
                        <?php if ($unreadMessages > 0): ?>
                            <span class="badge"><?php echo $unreadMessages; ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li><a href="<?php echo SITE_URL; ?>/account/favorites.php"><i class="fas fa-heart"></i> Favoriten (<?php echo $favoritesCount; ?>)</a></li>
                <li><a href="<?php echo SITE_URL; ?>/account/settings.php"><i class="fas fa-cog"></i> Einstellungen</a></li>
                <li><a href="<?php echo SITE_URL; ?>/logout.php"><i class="fas fa-sign-out-alt"></i> Abmelden</a></li>
            </ul>
        </div>
    </div>
    
    <div class="dashboard-content">
        <div class="dashboard-header">
            <h1 class="dashboard-title">Dashboard</h1>
            <a href="<?php echo SITE_URL; ?>/create-listing.php" class="btn btn-primary"><i class="fas fa-plus"></i> Neues Inserat</a>
        </div>
        
        <div class="dashboard-stats">
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-list"></i></div>
                <div class="stat-content">
                    <h3><?php echo count($listings); ?></h3>
                    <p>Aktive Inserate</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-envelope"></i></div>
                <div class="stat-content">
                    <h3><?php echo $unreadMessages; ?></h3>
                    <p>Ungelesene Nachrichten</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-heart"></i></div>
                <div class="stat-content">
                    <h3><?php echo $favoritesCount; ?></h3>
                    <p>Favoriten</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-eye"></i></div>
                <div class="stat-content">
                    <?php
                    $stmt = $pdo->prepare("SELECT SUM(views) FROM parts WHERE user_id = ?");
                    $stmt->execute([$_SESSION['user_id']]);
                    $totalViews = $stmt->fetchColumn() ?: 0;
                    ?>
                    <h3><?php echo $totalViews; ?></h3>
                    <p>Inserate Aufrufe</p>
                </div>
            </div>
        </div>
        
        <div class="dashboard-section">
            <h2>Neueste Inserate</h2>
    
    <?php if (count($listings) > 0): ?>
        <div class="dashboard-listings-grid">
            <?php foreach (array_slice($listings, 0, 4) as $listing): ?>
                <div class="dashboard-listing-card">
                    <div class="dashboard-listing-image">
                        <a href="<?php echo SITE_URL; ?>/listing.php?id=<?php echo $listing['part_id']; ?>">
                            <?php if (!empty($listing['file_path'])): ?>
                                <img src="<?php echo SITE_URL . '/' . $listing['file_path']; ?>" alt="<?php echo htmlspecialchars($listing['title']); ?>">
                            <?php else: ?>
                                <div class="no-image">
                                    <i class="fas fa-image"></i>
                                    <span>Kein Bild</span>
                                </div>
                            <?php endif; ?>
                        </a>
                        <?php if ($listing['message_count'] > 0): ?>
                            <div class="message-badge"><?php echo $listing['message_count']; ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="dashboard-listing-details">
                        <h3 class="dashboard-listing-title">
                            <a href="<?php echo SITE_URL; ?>/listing.php?id=<?php echo $listing['part_id']; ?>">
                                <?php echo htmlspecialchars($listing['title']); ?>
                            </a>
                        </h3>
                        
                        <div class="dashboard-listing-price">
                            <?php echo number_format($listing['price'], 2, ',', '.'); ?> €
                            <?php if ($listing['is_sold']): ?>
                                <span class="sold-badge">Verkauft</span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="dashboard-listing-meta">
                            <span class="dashboard-listing-date">
                                <i class="far fa-calendar-alt"></i> <?php echo date('d.m.Y', strtotime($listing['date_posted'])); ?>
                            </span>
                            <span class="dashboard-listing-views">
                                <i class="far fa-eye"></i> <?php echo $listing['views']; ?>
                            </span>
                        </div>
                        
                        <div class="dashboard-listing-actions">
                            <a href="<?php echo SITE_URL; ?>/edit-listing.php?id=<?php echo $listing['part_id']; ?>" class="btn btn-sm btn-outline" title="Bearbeiten">
                                <i class="fas fa-edit"></i>
                            </a>
                            <?php if (!$listing['is_sold']): ?>
                                <a href="<?php echo SITE_URL; ?>/mark-sold.php?id=<?php echo $listing['part_id']; ?>" class="btn btn-sm btn-outline" title="Als verkauft markieren">
                                    <i class="fas fa-check"></i>
                                </a>
                            <?php endif; ?>
                            <a href="<?php echo SITE_URL; ?>/delete-listing.php?id=<?php echo $listing['part_id']; ?>" class="btn btn-sm btn-danger" title="Löschen" onclick="return confirm('Sind Sie sicher, dass Sie dieses Inserat löschen möchten?');">
                                <i class="fas fa-trash"></i>
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <?php if (count($listings) > 4): ?>
            <div class="text-center mt-20">
                <a href="<?php echo SITE_URL; ?>/account/listings.php" class="btn btn-outline">Alle Inserate anzeigen</a>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-list fa-3x"></i>
            <h3>Keine Inserate vorhanden</h3>
            <p>Sie haben noch keine Inserate erstellt.</p>
            <a href="<?php echo SITE_URL; ?>/create-listing.php" class="btn btn-primary">Erstes Inserat erstellen</a>
        </div>
    <?php endif; ?>
</div>
        
        <div class="dashboard-section">
            <h2>Neueste Nachrichten</h2>
            
            <?php
            $stmt = $pdo->prepare("
                SELECT m.*, u.username, p.title as part_title, p.part_id
                FROM messages m
                JOIN users u ON m.sender_id = u.user_id
                LEFT JOIN parts p ON m.part_id = p.part_id
                WHERE m.receiver_id = ?
                ORDER BY m.sent_date DESC
                LIMIT 5
            ");
            $stmt->execute([$_SESSION['user_id']]);
            $messages = $stmt->fetchAll();
            ?>
            
            <?php if (count($messages) > 0): ?>
                <div class="dashboard-messages">
                    <table class="dashboard-table">
                        <thead>
                            <tr>
                                <th>Absender</th>
                                <th>Betreff</th>
                                <th>Nachricht</th>
                                <th>Datum</th>
                                <th>Status</th>
                                <th>Aktionen</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($messages as $message): ?>
                                <tr class="<?php echo $message['is_read'] ? '' : 'unread'; ?>">
                                    <td><?php echo htmlspecialchars($message['username']); ?></td>
                                    <td>
                                        <?php if ($message['part_id']): ?>
                                            <a href="<?php echo SITE_URL; ?>/listing.php?id=<?php echo $message['part_id']; ?>">
                                                <?php echo htmlspecialchars($message['subject'] ?: 'Anfrage: ' . $message['part_title']); ?>
                                            </a>
                                        <?php else: ?>
                                            <?php echo htmlspecialchars($message['subject'] ?: 'Keine Betreff'); ?>
                                        <?php endif; ?>
                                    </td>
                                    <td class="message-preview"><?php echo htmlspecialchars(substr($message['message'], 0, 50) . (strlen($message['message']) > 50 ? '...' : '')); ?></td>
                                    <td><?php echo date('d.m.Y H:i', strtotime($message['sent_date'])); ?></td>
                                    <td>
                                        <?php if ($message['is_read']): ?>
                                            <span class="status-badge read">Gelesen</span>
                                        <?php else: ?>
                                            <span class="status-badge unread">Ungelesen</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="actions">
                                        <a href="<?php echo SITE_URL; ?>/account/view-message.php?id=<?php echo $message['message_id']; ?>" class="btn btn-sm btn-outline" title="Anzeigen">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="<?php echo SITE_URL; ?>/account/reply-message.php?id=<?php echo $message['message_id']; ?>" class="btn btn-sm btn-outline" title="Antworten">
                                            <i class="fas fa-reply"></i>
                                        </a>
                                        <a href="<?php echo SITE_URL; ?>/account/delete-message.php?id=<?php echo $message['message_id']; ?>" class="btn btn-sm btn-danger" title="Löschen" onclick="return confirm('Sind Sie sicher, dass Sie diese Nachricht löschen möchten?');">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <div class="text-center mt-20">
                        <a href="<?php echo SITE_URL; ?>/account/messages.php" class="btn btn-outline">Alle Nachrichten anzeigen</a>
                    </div>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-state-icon">
                        <i class="fas fa-envelope-open"></i>
                    </div>
                    <h3>Keine Nachrichten vorhanden</h3>
                    <p>Sie haben noch keine Nachrichten erhalten.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
/* Dashboard Styles */
.dashboard-container {
    display: grid;
    grid-template-columns: 280px 1fr;
    gap: 30px;
    max-width: 70%;
    margin: 0 auto;
    padding: 20px 15px;
}

.dashboard-sidebar {
    background-color: white;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    height: fit-content;
    position: sticky;
    top: 20px;
}

.user-info {
    padding: 25px 20px;
    text-align: center;
    border-bottom: 1px solid #eee;
    background-color: #f8f9fa;
    border-radius: 10px 10px 0 0;
}

.user-avatar {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    margin: 0 auto 15px;
    object-fit: cover;
    border: 4px solid white;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
}

.user-name {
    font-size: 1.3rem;
    font-weight: bold;
    margin-bottom: 5px;
    color: #2c3e50;
}

.user-email {
    color: #7f8c8d;
    margin-bottom: 15px;
    font-size: 0.9rem;
}

.dashboard-nav ul {
    list-style: none;
    padding: 0;
    margin: 0;
}

.dashboard-nav ul li a {
    display: flex;
    align-items: center;
    padding: 15px 20px;
    color: #2c3e50;
    text-decoration: none;
    border-bottom: 1px solid #f1f1f1;
    transition: all 0.3s ease;
}

.dashboard-nav ul li a:hover {
    background-color: #f8f9fa;
    color: #3498db;
}

.dashboard-nav ul li a.active {
    background-color: #f0f7ff;
    color: #3498db;
    border-left: 4px solid #3498db;
}

.dashboard-nav ul li a i {
    margin-right: 10px;
    width: 20px;
    text-align: center;
    font-size: 1.1rem;
}

.badge {
    background-color: #e74c3c;
    color: white;
    border-radius: 50%;
    padding: 2px 6px;
    font-size: 0.7rem;
    margin-left: 5px;
}

.dashboard-content {
    background-color: white;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    padding: 25px;
}

.dashboard-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
    padding-bottom: 15px;
    border-bottom: 1px solid #eee;
}

.dashboard-title {
    font-size: 1.8rem;
    color: #2c3e50;
    margin: 0;
}

/* Stats Cards */
.dashboard-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background-color: #f8f9fa;
    border-radius: 10px;
    padding: 20px;
    display: flex;
    align-items: center;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    border: 1px solid #eee;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
}

.stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background-color: #3498db;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 15px;
    font-size: 1.5rem;
}

.stat-card:nth-child(1) .stat-icon {
    background-color: #3498db;
}

.stat-card:nth-child(2) .stat-icon {
    background-color: #e74c3c;
}

.stat-card:nth-child(3) .stat-icon {
    background-color: #f1c40f;
}

.stat-card:nth-child(4) .stat-icon {
    background-color: #2ecc71;
}

.stat-content h3 {
    font-size: 1.8rem;
    margin: 0 0 5px 0;
    color: #2c3e50;
}

.stat-content p {
    margin: 0;
    color: #7f8c8d;
    font-size: 0.9rem;
}

.dashboard-section {
    margin-bottom: 30px;
}

.dashboard-section h2 {
    font-size: 1.4rem;
    color: #2c3e50;
    margin-bottom: 20px;
    padding-bottom: 10px;
    border-bottom: 1px solid #eee;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 40px 20px;
    background-color: #f8f9fa;
    border-radius: 10px;
    margin-bottom: 30px;
}

.empty-state-icon {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background-color: #e8f4fc;
    color: #3498db;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 20px;
    font-size: 2rem;
}

.empty-state h3 {
    font-size: 1.3rem;
    color: #2c3e50;
    margin-bottom: 10px;
}

.empty-state p {
    color: #7f8c8d;
    margin-bottom: 20px;
}

/* Dashboard Messages */
.dashboard-messages {
    margin-bottom: 30px;
}

.dashboard-table {
    width: 100%;
    border-collapse: collapse;
}

.dashboard-table th, .dashboard-table td {
    padding: 12px 15px;
    text-align: left;
    border-bottom: 1px solid #eee;
}

.dashboard-table th {
    background-color: #f8f9fa;
    font-weight: 600;
    color: #2c3e50;
}

.dashboard-table tr:hover {
    background-color: #f8f9fa;
}

.dashboard-table tr.unread {
    background-color: #f0f7ff;
}

.message-preview {
    max-width: 200px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.status-badge {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
}

.status-badge.read {
    background-color: #f1f1f1;
    color: #7f8c8d;
}

.status-badge.unread {
    background-color: #e8f4fc;
    color: #3498db;
}

.actions {
    display: flex;
    gap: 5px;
}

.text-center {
    text-align: center;
}

.mt-20 {
    margin-top: 20px;
}

/* Dashboard Listings */
.dashboard-listings-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.dashboard-listing-card {
    background-color: white;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    overflow: hidden;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    border: 1px solid #eee;
}

.dashboard-listing-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
}

.dashboard-listing-image {
    position: relative;
    height: 160px;
    overflow: hidden;
}

.dashboard-listing-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s;
}

.dashboard-listing-card:hover .dashboard-listing-image img {
    transform: scale(1.05);
}

.no-image {
    width: 100%;
    height: 100%;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    background-color: #f5f5f5;
    color: #aaa;
}

.no-image i {
    font-size: 2rem;
    margin-bottom: 10px;
}

.message-badge {
    position: absolute;
    top: 10px;
    right: 10px;
    background-color: #e74c3c;
    color: white;
    width: 24px;
    height: 24px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.8rem;
    font-weight: bold;
}

.dashboard-listing-details {
    padding: 15px;
}

.dashboard-listing-title {
    margin: 0 0 10px 0;
    font-size: 1.1rem;
    line-height: 1.3;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.dashboard-listing-title a {
    color: #2c3e50;
    text-decoration: none;
}

.dashboard-listing-title a:hover {
    color: #3498db;
}

.dashboard-listing-price {
    font-size: 1.2rem;
    font-weight: bold;
    color: #2ecc71;
    margin-bottom: 10px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.sold-badge {
    background-color: #e74c3c;
    color: white;
    padding: 3px 8px;
    border-radius: 4px;
    font-size: 0.7rem;
    font-weight: bold;
}

.dashboard-listing-meta {
    display: flex;
    justify-content: space-between;
    margin-bottom: 15px;
    font-size: 0.85rem;
    color: #7f8c8d;
}

.dashboard-listing-date, .dashboard-listing-views {
    display: flex;
    align-items: center;
}

.dashboard-listing-meta i {
    margin-right: 5px;
}

.dashboard-listing-actions {
    display: flex;
    gap: 8px;
}

.dashboard-listing-actions .btn {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
}

/* Responsive adjustments */
@media (max-width: 992px) {
    .dashboard-container {
        grid-template-columns: 1fr;
    }
    
    .dashboard-sidebar {
        position: static;
        margin-bottom: 20px;
    }
    
    .dashboard-stats {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 576px) {
    .dashboard-stats {
        grid-template-columns: 1fr;
    }
    
    .dashboard-listings-grid {
        grid-template-columns: 1fr;
    }
    
    .dashboard-table {
        font-size: 0.9rem;
    }
    
    .message-preview {
        max-width: 100px;
    }
    
    .actions {
        flex-direction: column;
    }
}
</style>
<?php require_once '../includes/footer.php'; ?>

