<?php
$pageTitle = 'Meine Inserate';
require_once '../includes/header.php';

// Require login
requireLogin();

$userId = $_SESSION['user_id'];

// Get user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

// Handle listing actions (delete, mark as sold)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_listing']) && isset($_POST['listing_id'])) {
        $listingId = (int)$_POST['listing_id'];
        
        // Verify ownership
        $stmt = $pdo->prepare("SELECT * FROM parts WHERE part_id = ? AND user_id = ?");
        $stmt->execute([$listingId, $userId]);
        
        if ($stmt->rowCount() === 1) {
            // Delete listing images
            $imagesStmt = $pdo->prepare("SELECT file_path FROM images WHERE part_id = ?");
            $imagesStmt->execute([$listingId]);
            
            while ($image = $imagesStmt->fetch()) {
                $imagePath = '../' . $image['file_path'];
                if (file_exists($imagePath)) {
                    unlink($imagePath);
                }
            }
            
            // Delete listing records
            $pdo->beginTransaction();
            try {
                $pdo->prepare("DELETE FROM images WHERE part_id = ?")->execute([$listingId]);
                $pdo->prepare("DELETE FROM favorites WHERE part_id = ?")->execute([$listingId]);
                $pdo->prepare("DELETE FROM parts WHERE part_id = ?")->execute([$listingId]);
                $pdo->commit();
                
                $_SESSION['success'] = "Inserat wurde erfolgreich gelöscht.";
            } catch (Exception $e) {
                $pdo->rollBack();
                $_SESSION['error'] = "Fehler beim Löschen des Inserats: " . $e->getMessage();
            }
        } else {
            $_SESSION['error'] = "Du hast keine Berechtigung, dieses Inserat zu löschen.";
        }
    } elseif (isset($_POST['mark_sold']) && isset($_POST['listing_id'])) {
        $listingId = (int)$_POST['listing_id'];
        
        // Verify ownership
        $stmt = $pdo->prepare("SELECT * FROM parts WHERE part_id = ? AND user_id = ?");
        $stmt->execute([$listingId, $userId]);
        
        if ($stmt->rowCount() === 1) {
            $pdo->prepare("UPDATE parts SET is_sold = 1 WHERE part_id = ?")->execute([$listingId]);
            $_SESSION['success'] = "Inserat wurde als verkauft markiert.";
        } else {
            $_SESSION['error'] = "Du hast keine Berechtigung, dieses Inserat zu bearbeiten.";
        }
    } elseif (isset($_POST['mark_active']) && isset($_POST['listing_id'])) {
        $listingId = (int)$_POST['listing_id'];
        
        // Verify ownership
        $stmt = $pdo->prepare("SELECT * FROM parts WHERE part_id = ? AND user_id = ?");
        $stmt->execute([$listingId, $userId]);
        
        if ($stmt->rowCount() === 1) {
            $pdo->prepare("UPDATE parts SET is_sold = 0 WHERE part_id = ?")->execute([$listingId]);
            $_SESSION['success'] = "Inserat wurde als aktiv markiert.";
        } else {
            $_SESSION['error'] = "Du hast keine Berechtigung, dieses Inserat zu bearbeiten.";
        }
    }
    
    // Redirect to avoid form resubmission
    redirect($_SERVER['PHP_SELF']);
}

// Get user's listings
$status = isset($_GET['status']) ? sanitizeInput($_GET['status']) : 'all';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

$statusCondition = "";
if ($status === 'active') {
    $statusCondition = "AND p.is_sold = 0";
} elseif ($status === 'sold') {
    $statusCondition = "AND p.is_sold = 1";
}

$listingsQuery = "SELECT p.*, c.name as category_name, 
                 (SELECT file_path FROM images WHERE part_id = p.part_id AND is_primary = 1 LIMIT 1) as image_path,
                 (SELECT COUNT(*) FROM messages WHERE part_id = p.part_id AND receiver_id = ? AND is_read = 0) as unread_messages
                 FROM parts p
                 JOIN categories c ON p.category_id = c.category_id
                 WHERE p.user_id = ? $statusCondition
                 ORDER BY p.date_posted DESC
                 LIMIT ?, ?";
$stmt = $pdo->prepare($listingsQuery);
$stmt->bindValue(1, $userId, PDO::PARAM_INT);
$stmt->bindValue(2, $userId, PDO::PARAM_INT);
$stmt->bindValue(3, $offset, PDO::PARAM_INT);
$stmt->bindValue(4, $perPage, PDO::PARAM_INT);
$stmt->execute();
$listings = $stmt->fetchAll();

// Get total count for pagination
$countQuery = "SELECT COUNT(*) as total FROM parts p WHERE p.user_id = ? $statusCondition";
$countStmt = $pdo->prepare($countQuery);
$countStmt->execute([$userId]);
$totalCount = $countStmt->fetchColumn();
$totalPages = ceil($totalCount / $perPage);

// Get counts by status for tabs
$activeCountStmt = $pdo->prepare("SELECT COUNT(*) FROM parts WHERE user_id = ? AND is_sold = 0");
$activeCountStmt->execute([$userId]);
$activeCount = $activeCountStmt->fetchColumn();

$soldCountStmt = $pdo->prepare("SELECT COUNT(*) FROM parts WHERE user_id = ? AND is_sold = 1");
$soldCountStmt->execute([$userId]);
$soldCount = $soldCountStmt->fetchColumn();
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
                <li><a href="<?php echo SITE_URL; ?>/account/listings.php" class="active"><i class="fas fa-list"></i> Meine Inserate</a></li>
                <li>
                    <a href="<?php echo SITE_URL; ?>/account/messages.php">
                        <i class="fas fa-envelope"></i> Nachrichten
                        <?php 
                        $stmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0");
                        $stmt->execute([$userId]);
                        $unreadMessages = $stmt->fetchColumn();
                        if ($unreadMessages > 0): 
                        ?>
                            <span class="badge"><?php echo $unreadMessages; ?></span>
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
            <h1 class="dashboard-title">Meine Inserate</h1>
            <a href="<?php echo SITE_URL; ?>/create-listing.php" class="btn btn-primary">
                <i class="fas fa-plus-circle"></i> Neues Inserat erstellen
            </a>
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
        
        <div class="listings-tabs">
            <a href="?status=all" class="tab <?php echo ($status === 'all') ? 'active' : ''; ?>">
                Alle <span class="count">(<?php echo $totalCount; ?>)</span>
            </a>
            <a href="?status=active" class="tab <?php echo ($status === 'active') ? 'active' : ''; ?>">
                Aktiv <span class="count">(<?php echo $activeCount; ?>)</span>
            </a>
            <a href="?status=sold" class="tab <?php echo ($status === 'sold') ? 'active' : ''; ?>">
                Verkauft <span class="count">(<?php echo $soldCount; ?>)</span>
            </a>
        </div>
        
        <?php if (empty($listings)): ?>
            <div class="empty-state">
                <i class="fas fa-list fa-3x"></i>
                <h3>Keine Inserate gefunden</h3>
                <p>Sie haben noch keine Inserate in dieser Kategorie.</p>
                <a href="<?php echo SITE_URL; ?>/create-listing.php" class="btn btn-primary">Erstes Inserat erstellen</a>
            </div>
        <?php else: ?>
            <div class="listings-table-container">
                <table class="listings-table">
                    <thead>
                        <tr>
                            <th>Bild</th>
                            <th>Titel</th>
                            <th>Kategorie</th>
                            <th>Preis</th>
                            <th>Status</th>
                            <th>Datum</th>
                            <th>Aktionen</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($listings as $listing): ?>
                            <tr>
                                <td class="listing-image-cell">
                                    <?php if (!empty($listing['image_path'])): ?>
                                        <img src="<?php echo SITE_URL . '/' . $listing['image_path']; ?>" alt="<?php echo htmlspecialchars($listing['title']); ?>" class="listing-thumbnail">
                                    <?php else: ?>
                                        <div class="no-image-small">
                                            <i class="fas fa-image"></i>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="listing-title-cell">
                                    <a href="<?php echo SITE_URL; ?>/listing.php?id=<?php echo $listing['part_id']; ?>" class="listing-title-link">
                                        <?php echo htmlspecialchars($listing['title']); ?>
                                    </a>
                                    <?php if ($listing['unread_messages'] > 0): ?>
                                        <span class="badge"><?php echo $listing['unread_messages']; ?> neue Nachrichten</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($listing['category_name']); ?></td>
                                <td class="listing-price-cell">
                                    <?php echo number_format($listing['price'], 2, ',', '.'); ?> €
                                    <?php if ($listing['is_negotiable']): ?>
                                        <span class="negotiable-small">VB</span>
                                    <?php endif; ?>
                                </td>
                                <td class="listing-status-cell">
                                    <?php if ($listing['is_sold']): ?>
                                        <span class="status-badge status-sold">Verkauft</span>
                                    <?php else: ?>
                                        <span class="status-badge status-active">Aktiv</span>
                                    <?php endif; ?>
                                </td>
                                <td class="listing-date-cell">
                                    <?php echo date('d.m.Y', strtotime($listing['date_posted'])); ?>
                                </td>
                                <td class="listing-actions-cell">
                                    <div class="action-buttons">
                                        <a href="<?php echo SITE_URL; ?>/listing.php?id=<?php echo $listing['part_id']; ?>" class="btn btn-sm btn-outline" title="Ansehen">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        
                                        <a href="<?php echo SITE_URL; ?>/edit-listing.php?id=<?php echo $listing['part_id']; ?>" class="btn btn-sm btn-outline" title="Bearbeiten">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Möchten Sie dieses Inserat wirklich löschen? Diese Aktion kann nicht rückgängig gemacht werden.');">
                                            <input type="hidden" name="listing_id" value="<?php echo $listing['part_id']; ?>">
                                            <button type="submit" name="delete_listing" class="btn btn-sm btn-danger" title="Löschen">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                        
                                        <?php if (!$listing['is_sold']): ?>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="listing_id" value="<?php echo $listing['part_id']; ?>">
                                                <button type="submit" name="mark_sold" class="btn btn-sm btn-outline" title="Als verkauft markieren" onclick="return confirm('Möchten Sie dieses Inserat als verkauft markieren?');">
                                                    <i class="fas fa-check-circle"></i>
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="listing_id" value="<?php echo $listing['part_id']; ?>">
                                                <button type="submit" name="mark_active" class="btn btn-sm btn-outline" title="Als aktiv markieren" onclick="return confirm('Möchten Sie dieses Inserat wieder aktivieren?');">
                                                    <i class="fas fa-redo"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
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
                        <a href="?status=<?php echo $status; ?>&page=<?php echo $page - 1; ?>" class="pagination-link">
                            <i class="fas fa-chevron-left"></i> Zurück
                        </a>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                        <a href="?status=<?php echo $status; ?>&page=<?php echo $i; ?>" class="pagination-link <?php echo ($i == $page) ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <a href="?status=<?php echo $status; ?>&page=<?php echo $page + 1; ?>" class="pagination-link">
                            Weiter <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<style>
/* Listings Page Styles */
.listings-tabs {
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

.listings-table-container {
    overflow-x: auto;
    margin-bottom: 30px;
}

.listings-table {
    width: 100%;
    border-collapse: collapse;
}

.listings-table th, .listings-table td {
    padding: 15px;
    text-align: left;
    border-bottom: 1px solid #eee;
}

.listings-table th {
    background-color: #f8f9fa;
    font-weight: 600;
    color: #2c3e50;
}

.listings-table tr:hover {
    background-color: #f8f9fa;
}

.listing-image-cell {
    width: 80px;
}

.listing-thumbnail {
    width: 60px;
    height: 60px;
    object-fit: cover;
    border-radius: 4px;
}

.no-image-small {
    width: 60px;
    height: 60px;
    display: flex;
    align-items: center;
    justify-content: center;
    background-color: #f5f5f5;
    color: #aaa;
    border-radius: 4px;
}

.listing-title-cell {
    max-width: 250px;
}

.listing-title-link {
    display: block;
    margin-bottom: 5px;
    color: #2c3e50;
    text-decoration: none;
    font-weight: 600;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 250px;
}

.listing-title-link:hover {
    color: #3498db;
}

.listing-price-cell {
    font-weight: 600;
    color: #2ecc71;
    white-space: nowrap;
}

.negotiable-small {
    font-size: 0.75rem;
    color: #e74c3c;
    margin-left: 5px;
}

.listing-status-cell {
    text-align: center;
}

.status-badge {
    display: inline-block;
    padding: 5px 10px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
}

.status-active {
    background-color: #e8f7f0;
    color: #2ecc71;
}

.status-sold {
    background-color: #fef2f0;
    color: #e74c3c;
}

.listing-date-cell {
    white-space: nowrap;
    color: #7f8c8d;
}

.listing-actions-cell {
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

.pagination {
    display: flex;
    justify-content: center;
    margin-top: 30px;
}

.pagination-link {
    display: inline-block;
    padding: 8px 15px;
    margin: 0 5px;
    background-color: white;
    color: #3498db;
    border-radius: 4px;
    text-decoration: none;
    transition: all 0.3s ease;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
}

.pagination-link:hover {
    background-color: #f8f9fa;
}

.pagination-link.active {
    background-color: #3498db;
    color: white;
}

/* Responsive adjustments */
@media (max-width: 992px) {
    .listings-tabs {
        overflow-x: auto;
        white-space: nowrap;
        padding-bottom: 5px;
    }
    
    .tab {
        padding: 10px 15px;
    }
    
    .listing-title-cell {
        max-width: 150px;
    }
    
    .listing-title-link {
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
    
    .listings-table th, .listings-table td {
        padding: 10px;
    }
}
</style>

<?php require_once '../includes/footer.php'; ?>

