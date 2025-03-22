<?php
$pageTitle = 'Inserate verwalten';
require_once '../includes/admin-header.php';

// Prüfen, ob der Benutzer ein Admin ist
if (!isAdmin()) {
    redirect(SITE_URL);
    exit;
}

// Aktionen verarbeiten
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_listing']) && isset($_POST['listing_id'])) {
        $listingId = (int)$_POST['listing_id'];
        
        // Bilder löschen
        $imagesQuery = $pdo->prepare("SELECT file_path FROM images WHERE part_id = ?");
        $imagesQuery->execute([$listingId]);
        
        while ($image = $imagesQuery->fetch()) {
            $imagePath = '../' . $image['file_path'];
            if (file_exists($imagePath)) {
                unlink($imagePath);
            }
        }
        
        // Datenbankeinträge löschen
        $pdo->beginTransaction();
        try {
            $pdo->prepare("DELETE FROM images WHERE part_id = ?")->execute([$listingId]);
            $pdo->prepare("DELETE FROM favorites WHERE listing_id = ?")->execute([$listingId]);
            // Entferne diese Zeile, da es keine separate views-Tabelle gibt
            $pdo->prepare("DELETE FROM parts WHERE part_id = ?")->execute([$listingId]);
            $pdo->commit();
            
            $_SESSION['success'] = "Inserat wurde erfolgreich gelöscht.";
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['error'] = "Fehler beim Löschen des Inserats: " . $e->getMessage();
        }
    } elseif (isset($_POST['toggle_status']) && isset($_POST['listing_id'])) {
        $listingId = (int)$_POST['listing_id'];
        $currentStatus = (int)$_POST['current_status'];
        $newStatus = $currentStatus ? 0 : 1;
        
        try {
            $stmt = $pdo->prepare("UPDATE parts SET is_sold = ? WHERE part_id = ?");
            $stmt->execute([$newStatus, $listingId]);
            
            $_SESSION['success'] = "Status des Inserats wurde aktualisiert.";
        } catch (Exception $e) {
            $_SESSION['error'] = "Fehler beim Aktualisieren des Status: " . $e->getMessage();
        }
    }
    
    // Zurück zur gleichen Seite leiten, um POST-Daten zu löschen
    redirect($_SERVER['PHP_SELF']);
    exit;
}

// Anzahl der Inserate pro Seite
$itemsPerPage = 20;

// Aktuelle Seite ermitteln
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max(1, $page);
$offset = ($page - 1) * $itemsPerPage;

// Suchparameter
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status = isset($_GET['status']) ? $_GET['status'] : 'all';

// Ändere die SQL-Abfrage, um die views-Spalte direkt aus der parts-Tabelle zu verwenden
// und entferne den Verweis auf die nicht existierende views-Tabelle

// SQL-Abfrage vorbereiten
$sql = "
    SELECT p.*, 
           u.username, 
           c.name AS category_name,
           (SELECT file_path FROM images WHERE part_id = p.part_id AND is_primary = 1 LIMIT 1) AS image_path,
           p.views AS view_count
    FROM parts p
    JOIN users u ON p.user_id = u.user_id
    JOIN categories c ON p.category_id = c.category_id
    WHERE 1=1
";

$params = [];

// Suchfilter hinzufügen
if (!empty($search)) {
    $sql .= " AND (p.title LIKE ? OR p.description LIKE ? OR u.username LIKE ?)";
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

// Statusfilter hinzufügen
if ($status === 'active') {
    $sql .= " AND p.is_sold = 0";
} elseif ($status === 'sold') {
    $sql .= " AND p.is_sold = 1";
}

// Gesamtanzahl der Inserate ermitteln
$countSql = "SELECT COUNT(*) FROM ($sql) AS count_table";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalItems = $countStmt->fetchColumn();
$totalPages = ceil($totalItems / $itemsPerPage);

// Sortierung und Paginierung hinzufügen
$sql .= " ORDER BY p.date_posted DESC LIMIT :limit OFFSET :offset";

// Inserate abrufen
$stmt = $pdo->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key + 1, $value);
}
$stmt->bindValue(':limit', $itemsPerPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$listings = $stmt->fetchAll();
?>

<div class="admin-container">
    <div class="admin-header">
        <h1><i class="fas fa-list"></i> Inserate verwalten</h1>
        <p>Hier können Sie alle Inserate auf der Plattform verwalten</p>
    </div>
    
    <?php include '../includes/messages.php'; ?>
    
    <div class="admin-filters">
        <form action="" method="GET" class="filter-form">
            <div class="filter-group">
                <input type="text" name="search" placeholder="Suchen..." value="<?php echo htmlspecialchars($search); ?>" class="form-control">
            </div>
            
            <div class="filter-group">
                <select name="status" class="form-control">
                    <option value="all" <?php echo $status === 'all' ? 'selected' : ''; ?>>Alle Status</option>
                    <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Aktiv</option>
                    <option value="sold" <?php echo $status === 'sold' ? 'selected' : ''; ?>>Verkauft</option>
                </select>
            </div>
            
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-search"></i> Filtern
            </button>
            
            <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="btn btn-outline">
                <i class="fas fa-redo"></i> Zurücksetzen
            </a>
        </form>
    </div>
    
    <div class="admin-content">
        <?php if (empty($listings)): ?>
            <div class="no-items">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> Keine Inserate gefunden.
                </div>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Bild</th>
                            <th>Titel</th>
                            <th>Preis</th>
                            <th>Status</th>
                            <th>Aufrufe</th>
                            <th>Datum</th>
                            <th>Aktionen</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($listings as $listing): ?>
                            <tr>
                                <td class="listing-image-cell">
                                    <?php if (!empty($listing['image_path'])): ?>
                                        <img src="<?php echo '../' . htmlspecialchars($listing['image_path']); ?>" alt="<?php echo htmlspecialchars($listing['title']); ?>" class="listing-thumbnail">
                                    <?php else: ?>
                                        <div class="no-image-small">
                                            <i class="fas fa-image"></i>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="listing-title-cell">
                                        <a href="../listing.php?id=<?php echo $listing['part_id']; ?>" target="_blank" class="listing-title-link">
                                            <?php echo htmlspecialchars($listing['title']); ?>
                                        </a>
                                        <div class="listing-meta-small">
                                            <span class="listing-category-small">
                                                <i class="fas fa-tag"></i> <?php echo htmlspecialchars($listing['category_name']); ?>
                                            </span>
                                            <span class="listing-user-small">
                                                <i class="fas fa-user"></i> <?php echo htmlspecialchars($listing['username']); ?>
                                            </span>
                                        </div>
                                    </div>
                                </td>
                                <td class="listing-price-cell">
                                    <?php echo number_format($listing['price'], 2, ',', '.'); ?> €
                                    <?php if ($listing['is_negotiable']): ?>
                                        <span class="negotiable-small">VB</span>
                                    <?php endif; ?>
                                </td>
                                <td class="listing-status-cell">
                                    <span class="status-badge <?php echo $listing['is_sold'] ? 'status-sold' : 'status-active'; ?>">
                                        <?php echo $listing['is_sold'] ? 'Verkauft' : 'Aktiv'; ?>
                                    </span>
                                </td>
                                <td class="listing-views-cell">
                                    <span class="views-count">
                                        <i class="far fa-eye"></i> <?php echo $listing['view_count']; ?>
                                    </span>
                                </td>
                                <td class="listing-date-cell">
                                    <?php echo date('d.m.Y', strtotime($listing['date_posted'])); ?>
                                </td>
                                <td class="listing-actions-cell">
                                    <div class="action-buttons">
                                        <a href="../listing.php?id=<?php echo $listing['part_id']; ?>" class="btn btn-sm btn-outline" title="Ansehen">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Möchten Sie den Status dieses Inserats wirklich ändern?');">
                                            <input type="hidden" name="listing_id" value="<?php echo $listing['part_id']; ?>">
                                            <input type="hidden" name="current_status" value="<?php echo $listing['is_sold']; ?>">
                                            <button type="submit" name="toggle_status" class="btn btn-sm <?php echo $listing['is_sold'] ? 'btn-success' : 'btn-warning'; ?>" title="<?php echo $listing['is_sold'] ? 'Als aktiv markieren' : 'Als verkauft markieren'; ?>">
                                                <i class="fas <?php echo $listing['is_sold'] ? 'fa-check-circle' : 'fa-tag'; ?>"></i>
                                            </button>
                                        </form>
                                        
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Möchten Sie dieses Inserat wirklich löschen? Diese Aktion kann nicht rückgängig gemacht werden.');">
                                            <input type="hidden" name="listing_id" value="<?php echo $listing['part_id']; ?>">
                                            <button type="submit" name="delete_listing" class="btn btn-sm btn-danger" title="Löschen">
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
            
            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div class="pagination-container">
                    <ul class="pagination">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status; ?>" aria-label="Vorherige">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <?php
                        $startPage = max(1, $page - 2);
                        $endPage = min($totalPages, $page + 2);
                        
                        if ($startPage > 1) {
                            echo '<li class="page-item"><a class="page-link" href="?page=1&search=' . urlencode($search) . '&status=' . $status . '">1</a></li>';
                            if ($startPage > 2) {
                                echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                            }
                        }
                        
                        for ($i = $startPage; $i <= $endPage; $i++) {
                            $activeClass = ($i === $page) ? 'active' : '';
                            echo '<li class="page-item ' . $activeClass . '"><a class="page-link" href="?page=' . $i . '&search=' . urlencode($search) . '&status=' . $status . '">' . $i . '</a></li>';
                        }
                        
                        if ($endPage < $totalPages) {
                            if ($endPage < $totalPages - 1) {
                                echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                            }
                            echo '<li class="page-item"><a class="page-link" href="?page=' . $totalPages . '&search=' . urlencode($search) . '&status=' . $status . '">' . $totalPages . '</a></li>';
                        }
                        ?>
                        
                        <?php if ($page < $totalPages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status; ?>" aria-label="Nächste">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<style>
.admin-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

.admin-header {
    margin-bottom: 30px;
}

.admin-header h1 {
    font-size: 2rem;
    color: #2c3e50;
    margin-bottom: 10px;
    display: flex;
    align-items: center;
}

.admin-header h1 i {
    margin-right: 15px;
    color: #3498db;
}

.admin-header p {
    color: #7f8c8d;
    font-size: 1.1rem;
}

.admin-filters {
    margin-bottom: 30px;
    background-color: #f8f9fa;
    padding: 20px;
    border-radius: 8px;
}

.filter-form {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
    align-items: center;
}

.filter-group {
    flex: 1;
    min-width: 200px;
}

.form-control {
    width: 100%;
    padding: 10px 15px;
    border: 1px solid #ddd;
    border-radius: 5px;
    font-size: 1rem;
}

.btn {
    padding: 10px 20px;
    border-radius: 5px;
    font-weight: 600;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    transition: all 0.3s;
}

.btn-primary {
    background-color: #3498db;
    color: white;
    border: none;
}

.btn-primary:hover {
    background-color: #2980b9;
}

.btn-outline {
    background-color: transparent;
    color: #3498db;
    border: 1px solid #3498db;
}

.btn-outline:hover {
    background-color: rgba(52, 152, 219, 0.1);
}

.btn-sm {
    padding: 6px 10px;
    font-size: 0.85rem;
}

.btn-success {
    background-color: #2ecc71;
    color: white;
    border: none;
}

.btn-success:hover {
    background-color: #27ae60;
}

.btn-warning {
    background-color: #f39c12;
    color: white;
    border: none;
}

.btn-warning:hover {
    background-color: #e67e22;
}

.btn-danger {
    background-color: #e74c3c;
    color: white;
    border: none;
}

.btn-danger:hover {
    background-color: #c0392b;
}

.admin-content {
    background-color: white;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    overflow: hidden;
}

.table-responsive {
    overflow-x: auto;
}

.admin-table {
    width: 100%;
    border-collapse: collapse;
}

.admin-table th, .admin-table td {
    padding: 15px;
    text-align: left;
    border-bottom: 1px solid #eee;
}

.admin-table th {
    background-color: #f8f9fa;
    font-weight: 600;
    color: #2c3e50;
}

.admin-table tr:hover {
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
    max-width: 300px;
}

.listing-title-link {
    color: #2c3e50;
    text-decoration: none;
    font-weight: 600;
    display: block;
    margin-bottom: 5px;
}

.listing-title-link:hover {
    color: #3498db;
}

.listing-meta-small {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    font-size: 0.8rem;
    color: #7f8c8d;
}

.listing-category-small, .listing-user-small {
    display: flex;
    align-items: center;
}

.listing-meta-small i {
    margin-right: 5px;
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

.listing-views-cell {
    text-align: center;
}

.views-count {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
    color: #7f8c8d;
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

.no-items {
    padding: 30px;
    text-align: center;
}

.alert {
    padding: 15px 20px;
    border-radius: 5px;
    margin-bottom: 20px;
}

.alert-info {
    background-color: #d1ecf1;
    color: #0c5460;
    border: 1px solid #bee5eb;
}

.alert i {
    margin-right: 10px;
}

/* Pagination */
.pagination-container {
    display: flex;
    justify-content: center;
    padding: 20px;
}

.pagination {
    display: flex;
    list-style: none;
    padding: 0;
    margin: 0;
}

.page-item {
    margin: 0 5px;
}

.page-link {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background-color: white;
    color: #3498db;
    text-decoration: none;
    transition: all 0.3s;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
}

.page-item.active .page-link {
    background-color: #3498db;
    color: white;
}

.page-item.disabled .page-link {
    color: #ccc;
    pointer-events: none;
}

.page-link:hover {
    background-color: #f8f9fa;
}

.page-item.active .page-link:hover {
    background-color: #2980b9;
}

/* Responsive adjustments */
@media (max-width: 992px) {
    .filter-form {
        flex-direction: column;
        align-items: stretch;
    }
    
    .filter-group {
        width: 100%;
    }
    
    .admin-table th, .admin-table td {
        padding: 10px;
    }
    
    .listing-meta-small {
        flex-direction: column;
        gap: 5px;
    }
}

@media (max-width: 768px) {
    .admin-table {
        font-size: 0.9rem;
    }
    
    .listing-thumbnail, .no-image-small {
        width: 50px;
        height: 50px;
    }
    
    .action-buttons {
        flex-direction: column;
        gap: 5px;
    }
    
    .btn-sm {
        width: 100%;
    }
}
</style>

<?php require_once '../includes/admin-footer.php'; ?>

