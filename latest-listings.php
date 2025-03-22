<?php
$pageTitle = 'Neueste Inserate';
require_once 'includes/header.php';

// Anzahl der Inserate pro Seite
$itemsPerPage = 12;

// Aktuelle Seite ermitteln
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max(1, $page);
$offset = ($page - 1) * $itemsPerPage;

// Gesamtanzahl der Inserate ermitteln
$countStmt = $pdo->query("SELECT COUNT(*) FROM parts WHERE is_sold = 0");
$totalItems = $countStmt->fetchColumn();
$totalPages = ceil($totalItems / $itemsPerPage);

// Inserate abrufen
$stmt = $pdo->prepare("
    SELECT p.*, 
           u.username, 
           c.name AS category_name,
           (SELECT file_path FROM images WHERE part_id = p.part_id AND is_primary = 1 LIMIT 1) AS image_path,
           (SELECT COUNT(*) FROM favorites WHERE part_id = p.part_id) AS favorite_count,
           p.views AS view_count
    FROM parts p
    JOIN users u ON p.user_id = u.user_id
    JOIN categories c ON p.category_id = c.category_id
    WHERE p.is_sold = 0
    ORDER BY p.date_posted DESC
    LIMIT :limit OFFSET :offset
");
$stmt->bindValue(':limit', $itemsPerPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$listings = $stmt->fetchAll();
?>

<div class="container">
    <div class="page-header">
        <h1>Neueste Inserate</h1>
        <p>Entdecken Sie die neuesten Autoteile in unserem Marktplatz</p>
    </div>
    
    <?php if (empty($listings)): ?>
        <div class="no-listings">
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> Derzeit sind keine Inserate verfügbar.
            </div>
        </div>
    <?php else: ?>
        <div class="listings-grid">
            <?php foreach ($listings as $listing): ?>
                <div class="listing-card">
                    <div class="listing-image">
                        <a href="listing.php?id=<?php echo $listing['part_id']; ?>">
                            <?php if (!empty($listing['image_path'])): ?>
                                <img src="<?php echo htmlspecialchars($listing['image_path']); ?>" alt="<?php echo htmlspecialchars($listing['title']); ?>">
                            <?php else: ?>
                                <div class="no-image">
                                    <i class="fas fa-image"></i>
                                    <span>Kein Bild</span>
                                </div>
                            <?php endif; ?>
                        </a>
                        <?php if ($listing['is_negotiable']): ?>
                            <span class="negotiable-badge">VB</span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="listing-details">
                        <h3 class="listing-title">
                            <a href="listing.php?id=<?php echo $listing['part_id']; ?>">
                                <?php echo htmlspecialchars($listing['title']); ?>
                            </a>
                        </h3>
                        
                        <div class="listing-meta">
                            <span class="listing-category">
                                <i class="fas fa-tag"></i> <?php echo htmlspecialchars($listing['category_name']); ?>
                            </span>
                            <span class="listing-location">
                                <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($listing['location']); ?>
                            </span>
                        </div>
                        
                        <div class="listing-price">
                            <?php echo number_format($listing['price'], 2, ',', '.'); ?> €
                        </div>
                        
                        <div class="listing-footer">
                            <span class="listing-date">
                                <i class="far fa-clock"></i> <?php echo date('d.m.Y', strtotime($listing['date_posted'])); ?>
                            </span>
                            <div class="listing-stats">
                                <span class="listing-views" title="Aufrufe">
                                    <i class="far fa-eye"></i> <?php echo $listing['view_count']; ?>
                                </span>
                                <span class="listing-favorites" title="Favoriten">
                                    <i class="far fa-heart"></i> <?php echo $listing['favorite_count']; ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <div class="pagination-container">
                <ul class="pagination">
                    <?php if ($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?php echo $page - 1; ?>" aria-label="Vorherige">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        </li>
                    <?php endif; ?>
                    
                    <?php
                    $startPage = max(1, $page - 2);
                    $endPage = min($totalPages, $page + 2);
                    
                    if ($startPage > 1) {
                        echo '<li class="page-item"><a class="page-link" href="?page=1">1</a></li>';
                        if ($startPage > 2) {
                            echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                        }
                    }
                    
                    for ($i = $startPage; $i <= $endPage; $i++) {
                        $activeClass = ($i === $page) ? 'active' : '';
                        echo '<li class="page-item ' . $activeClass . '"><a class="page-link" href="?page=' . $i . '">' . $i . '</a></li>';
                    }
                    
                    if ($endPage < $totalPages) {
                        if ($endPage < $totalPages - 1) {
                            echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                        }
                        echo '<li class="page-item"><a class="page-link" href="?page=' . $totalPages . '">' . $totalPages . '</a></li>';
                    }
                    ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?php echo $page + 1; ?>" aria-label="Nächste">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<style>
.container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

.page-header {
    margin-bottom: 30px;
    text-align: center;
}

.page-header h1 {
    font-size: 2.2rem;
    color: #2c3e50;
    margin-bottom: 10px;
}

.page-header p {
    color: #7f8c8d;
    font-size: 1.1rem;
}

.no-listings {
    text-align: center;
    padding: 40px 0;
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

.listings-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 25px;
    margin-bottom: 40px;
}

.listing-card {
    background-color: white;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    overflow: hidden;
    transition: transform 0.3s, box-shadow 0.3s;
}

.listing-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
}

.listing-image {
    position: relative;
    height: 180px;
    overflow: hidden;
}

.listing-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s;
}

.listing-card:hover .listing-image img {
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

.negotiable-badge {
    position: absolute;
    top: 10px;
    right: 10px;
    background-color: #e74c3c;
    color: white;
    padding: 5px 8px;
    border-radius: 4px;
    font-size: 0.8rem;
    font-weight: bold;
}

.listing-details {
    padding: 15px;
}

.listing-title {
    margin: 0 0 10px 0;
    font-size: 1.1rem;
    line-height: 1.3;
}

.listing-title a {
    color: #2c3e50;
    text-decoration: none;
}

.listing-title a:hover {
    color: #3498db;
}

.listing-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-bottom: 10px;
    font-size: 0.85rem;
    color: #7f8c8d;
}

.listing-meta span {
    display: flex;
    align-items: center;
}

.listing-meta i {
    margin-right: 5px;
}

.listing-price {
    font-size: 1.2rem;
    font-weight: bold;
    color: #2ecc71;
    margin-bottom: 10px;
}

.listing-footer {
    display: flex;
    justify-content: space-between;
    padding-top: 10px;
    border-top: 1px solid #eee;
    font-size: 0.85rem;
    color: #95a5a6;
}

.listing-stats {
    display: flex;
    gap: 10px;
}

.listing-views, .listing-favorites {
    display: flex;
    align-items: center;
}

.listing-views i, .listing-favorites i {
    margin-right: 5px;
}

/* Pagination */
.pagination-container {
    display: flex;
    justify-content: center;
    margin-top: 30px;
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
@media (max-width: 768px) {
    .listings-grid {
        grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    }
}

@media (max-width: 576px) {
    .listings-grid {
        grid-template-columns: 1fr;
    }
    
    .page-link {
        width: 35px;
        height: 35px;
    }
}
</style>

<?php require_once 'includes/footer.php'; ?>

