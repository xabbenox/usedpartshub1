<?php
$pageTitle = 'Suche';
require_once 'includes/header.php';

// Get search parameters
$query = isset($_GET['q']) ? sanitizeInput($_GET['q']) : '';
$categoryId = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;
$makeId = isset($_GET['make_id']) ? (int)$_GET['make_id'] : 0;
$modelId = isset($_GET['model_id']) ? (int)$_GET['model_id'] : 0;
$minPrice = isset($_GET['min_price']) ? (float)$_GET['min_price'] : 0;
$maxPrice = isset($_GET['max_price']) ? (float)$_GET['max_price'] : 0;
$condition = isset($_GET['condition']) ? sanitizeInput($_GET['condition']) : '';
$location = isset($_GET['location']) ? sanitizeInput($_GET['location']) : '';
$sort = isset($_GET['sort']) ? sanitizeInput($_GET['sort']) : 'newest';

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 12;
$offset = ($page - 1) * $perPage;

// Build query
$sql = "
    SELECT p.*, i.file_path, u.city, c.name as category_name, m.name as make_name, mo.name as model_name
    FROM parts p
    LEFT JOIN images i ON p.part_id = i.part_id AND i.is_primary = 1
    LEFT JOIN users u ON p.user_id = u.user_id
    LEFT JOIN categories c ON p.category_id = c.category_id
    LEFT JOIN car_makes m ON p.make_id = m.make_id
    LEFT JOIN car_models mo ON p.model_id = mo.model_id
    WHERE p.is_sold = 0
";

$countSql = "
    SELECT COUNT(*) 
    FROM parts p
    WHERE p.is_sold = 0
";

$params = [];

// Add search conditions
if (!empty($query)) {
    $sql .= " AND (p.title LIKE ? OR p.description LIKE ?)";
    $countSql .= " AND (p.title LIKE ? OR p.description LIKE ?)";
    $params[] = "%$query%";
    $params[] = "%$query%";
}

if ($categoryId > 0) {
    $sql .= " AND p.category_id = ?";
    $countSql .= " AND p.category_id = ?";
    $params[] = $categoryId;
}

if ($makeId > 0) {
    $sql .= " AND p.make_id = ?";
    $countSql .= " AND p.make_id = ?";
    $params[] = $makeId;
    
    if ($modelId > 0) {
        $sql .= " AND p.model_id = ?";
        $countSql .= " AND p.model_id = ?";
        $params[] = $modelId;
    }
}

if ($minPrice > 0) {
    $sql .= " AND p.price >= ?";
    $countSql .= " AND p.price >= ?";
    $params[] = $minPrice;
}

if ($maxPrice > 0) {
    $sql .= " AND p.price <= ?";
    $countSql .= " AND p.price <= ?";
    $params[] = $maxPrice;
}

if (!empty($condition)) {
    $sql .= " AND p.condition_rating = ?";
    $countSql .= " AND p.condition_rating = ?";
    $params[] = $condition;
}

if (!empty($location)) {
    $sql .= " AND (p.location LIKE ? OR u.city LIKE ?)";
    $countSql .= " AND (p.location LIKE ? OR u.city LIKE ?)";
    $params[] = "%$location%";
    $params[] = "%$location%";
}

// Add sorting
switch ($sort) {
    case 'price_asc':
        $sql .= " ORDER BY p.price ASC";
        break;
    case 'price_desc':
        $sql .= " ORDER BY p.price DESC";
        break;
    case 'oldest':
        $sql .= " ORDER BY p.date_posted ASC";
        break;
    case 'newest':
    default:
        $sql .= " ORDER BY p.date_posted DESC";
        break;
}

// Add pagination
$sql .= " LIMIT $perPage OFFSET $offset";

// Execute count query
$stmt = $pdo->prepare($countSql);
$stmt->execute($params);
$totalResults = $stmt->fetchColumn();

// Calculate total pages
$totalPages = ceil($totalResults / $perPage);

// Execute search query
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$results = $stmt->fetchAll();

// Build pagination URL
function buildPaginationUrl($page) {
    $params = $_GET;
    $params['page'] = $page;
    return '?' . http_build_query($params);
}
?>

<div class="search-container">
    <div class="search-sidebar">
        <h2>Filter</h2>
        <form method="GET" action="" class="search-filter-form">
            <?php if (!empty($query)): ?>
                <input type="hidden" name="q" value="<?php echo htmlspecialchars($query); ?>">
            <?php endif; ?>
            
            <div class="form-group">
                <label for="category_id">Kategorie</label>
                <select id="category_id" name="category_id" class="form-control">
                    <option value="">Alle Kategorien</option>
                    <?php
                    $stmt = $pdo->query("SELECT category_id, name FROM categories ORDER BY name");
                    while ($category = $stmt->fetch()) {
                        $selected = ($categoryId == $category['category_id']) ? 'selected' : '';
                        echo '<option value="' . $category['category_id'] . '" ' . $selected . '>' . htmlspecialchars($category['name']) . '</option>';
                    }
                    ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="make_id">Marke</label>
                <select id="make_id" name="make_id" class="form-control">
                    <option value="">Alle Marken</option>
                    <?php
                    $stmt = $pdo->query("SELECT make_id, name FROM car_makes ORDER BY name");
                    while ($make = $stmt->fetch()) {
                        $selected = ($makeId == $make['make_id']) ? 'selected' : '';
                        echo '<option value="' . $make['make_id'] . '" ' . $selected . '>' . htmlspecialchars($make['name']) . '</option>';
                    }
                    ?>
                </select>
            </div>
            
            <?php if ($makeId > 0): ?>
                <div class="form-group">
                    <label for="model_id">Modell</label>
                    <select id="model_id" name="model_id" class="form-control">
                        <option value="">Alle Modelle</option>
                        <?php
                        $stmt = $pdo->prepare("SELECT model_id, name FROM car_models WHERE make_id = ? ORDER BY name");
                        $stmt->execute([$makeId]);
                        while ($model = $stmt->fetch()) {
                            $selected = ($modelId == $model['model_id']) ? 'selected' : '';
                            echo '<option value="' . $model['model_id'] . '" ' . $selected . '>' . htmlspecialchars($model['name']) . '</option>';
                        }
                        ?>
                    </select>
                </div>
            <?php endif; ?>
            
            <div class="form-group">
                <label>Preis (€)</label>
                <div class="price-range">
                    <input type="number" name="min_price" placeholder="Min" class="form-control" value="<?php echo $minPrice > 0 ? $minPrice : ''; ?>">
                    <span>-</span>
                    <input type="number" name="max_price" placeholder="Max" class="form-control" value="<?php echo $maxPrice > 0 ? $maxPrice : ''; ?>">
                </div>
            </div>
            
            <div class="form-group">
                <label for="condition">Zustand</label>
                <select id="condition" name="condition" class="form-control">
                    <option value="">Alle Zustände</option>
                    <option value="New" <?php echo ($condition === 'New') ? 'selected' : ''; ?>>Neu</option>
                    <option value="Like New" <?php echo ($condition === 'Like New') ? 'selected' : ''; ?>>Wie neu</option>
                    <option value="Good" <?php echo ($condition === 'Good') ? 'selected' : ''; ?>>Gut</option>
                    <option value="Fair" <?php echo ($condition === 'Fair') ? 'selected' : ''; ?>>Gebraucht</option>
                    <option value="Poor" <?php echo ($condition === 'Poor') ? 'selected' : ''; ?>>Stark gebraucht</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="location">Standort</label>
                <input type="text" id="location" name="location" class="form-control" value="<?php echo htmlspecialchars($location); ?>" placeholder="Stadt oder Region">
            </div>
            
            <div class="form-group">
                <label for="sort">Sortieren nach</label>
                <select id="sort" name="sort" class="form-control">
                    <option value="newest" <?php echo ($sort === 'newest') ? 'selected' : ''; ?>>Neueste zuerst</option>
                    <option value="oldest" <?php echo ($sort === 'oldest') ? 'selected' : ''; ?>>Älteste zuerst</option>
                    <option value="price_asc" <?php echo ($sort === 'price_asc') ? 'selected' : ''; ?>>Preis aufsteigend</option>
                    <option value="price_desc" <?php echo ($sort === 'price_desc') ? 'selected' : ''; ?>>Preis absteigend</option>
                </select>
            </div>
            
            <button type="submit" class="btn btn-primary btn-block">Filter anwenden</button>
            <a href="<?php echo SITE_URL; ?>/search.php<?php echo !empty($query) ? '?q=' . urlencode($query) : ''; ?>" class="btn btn-outline btn-block">Filter zurücksetzen</a>
        </form>
    </div>
    
    <div class="search-results">
        <div class="search-header">
            <h1>
                <?php if (!empty($query)): ?>
                    Suchergebnisse für "<?php echo htmlspecialchars($query); ?>"
                <?php else: ?>
                    Alle Angebote
                <?php endif; ?>
            </h1>
            <p><?php echo $totalResults; ?> Ergebnisse gefunden</p>
        </div>
        
        <?php if (count($results) > 0): ?>
            <div class="listings-grid">
                <?php foreach ($results as $listing): ?>
                    <a href="<?php echo SITE_URL; ?>/listing.php?id=<?php echo $listing['part_id']; ?>" class="listing-card">
                        <div class="listing-card-img">
                            <img src="<?php echo $listing['file_path'] ? SITE_URL . '/' . $listing['file_path'] : SITE_URL . '/assets/images/placeholder.jpg'; ?>" 
                                 alt="<?php echo htmlspecialchars($listing['title']); ?>">
                            <div class="listing-card-price">€ <?php echo number_format($listing['price'], 2, ',', '.'); ?></div>
                        </div>
                        <div class="listing-card-content">
                            <h3 class="listing-card-title"><?php echo htmlspecialchars($listing['title']); ?></h3>
                            <div class="listing-card-details">
                                <span><?php echo htmlspecialchars($listing['category_name']); ?></span>
                                <span>
                                    <?php 
                                    if ($listing['make_name']) {
                                        echo htmlspecialchars($listing['make_name']);
                                        if ($listing['model_name']) {
                                            echo ' ' . htmlspecialchars($listing['model_name']);
                                        }
                                    }
                                    ?>
                                </span>
                            </div>
                            <div class="listing-card-footer">
                                <div class="listing-card-location">
                                    <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($listing['location'] ?: $listing['city'] ?: 'Österreich'); ?>
                                </div>
                                <div class="listing-card-date">
                                    <?php 
                                    $date = new DateTime($listing['date_posted']);
                                    echo $date->format('d.m.Y'); 
                                    ?>
                                </div>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
            
            <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="<?php echo buildPaginationUrl($page - 1); ?>">&laquo; Zurück</a>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                        <?php if ($i == $page): ?>
                            <span class="active"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="<?php echo buildPaginationUrl($i); ?>"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <a href="<?php echo buildPaginationUrl($page + 1); ?>">Weiter &raquo;</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="no-results">
                <i class="fas fa-search fa-3x"></i>
                <h2>Keine Ergebnisse gefunden</h2>
                <p>Versuchen Sie es mit anderen Suchbegriffen oder weniger Filtern.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>