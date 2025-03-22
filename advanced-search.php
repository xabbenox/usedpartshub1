<?php
$pageTitle = 'Erweiterte Suche';
require_once 'includes/header.php';

// Suchparameter abrufen
$query = isset($_GET['q']) ? sanitizeInput($_GET['q']) : '';
$categoryId = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;
$makeId = isset($_GET['make_id']) ? (int)$_GET['make_id'] : 0;
$modelId = isset($_GET['model_id']) ? (int)$_GET['model_id'] : 0;
$minPrice = isset($_GET['min_price']) ? (float)$_GET['min_price'] : 0;
$maxPrice = isset($_GET['max_price']) ? (float)$_GET['max_price'] : 0;
$condition = isset($_GET['condition']) ? sanitizeInput($_GET['condition']) : '';
$location = isset($_GET['location']) ? sanitizeInput($_GET['location']) : '';
$yearFrom = isset($_GET['year_from']) ? (int)$_GET['year_from'] : 0;
$yearTo = isset($_GET['year_to']) ? (int)$_GET['year_to'] : 0;
$sort = isset($_GET['sort']) ? sanitizeInput($_GET['sort']) : 'newest';

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 12;
$offset = ($page - 1) * $perPage;

// Suche nur ausführen, wenn mindestens ein Suchparameter gesetzt ist
$isSearch = !empty($query) || $categoryId > 0 || $makeId > 0 || $modelId > 0 || 
            $minPrice > 0 || $maxPrice > 0 || !empty($condition) || !empty($location) ||
            $yearFrom > 0 || $yearTo > 0;

// SQL-Abfrage für die Suche
if ($isSearch) {
    $sql = "
        SELECT p.*, 
               u.username, u.city as user_city,
               c.name as category_name,
               (SELECT file_path FROM images WHERE part_id = p.part_id AND is_primary = 1 LIMIT 1) AS image_path,
               m.name as make_name, 
               mo.name as model_name
        FROM parts p
        JOIN users u ON p.user_id = u.user_id
        JOIN categories c ON p.category_id = c.category_id
        LEFT JOIN car_makes m ON p.make_id = m.make_id
        LEFT JOIN car_models mo ON p.model_id = mo.model_id
        WHERE p.is_sold = 0
    ";

    $countSql = "
        SELECT COUNT(*) 
        FROM parts p
        JOIN users u ON p.user_id = u.user_id
        JOIN categories c ON p.category_id = c.category_id
        LEFT JOIN car_makes m ON p.make_id = m.make_id
        LEFT JOIN car_models mo ON p.model_id = mo.model_id
        WHERE p.is_sold = 0
    ";

    $params = [];

    // Suchbegriff
    if (!empty($query)) {
        $sql .= " AND (p.title LIKE ? OR p.description LIKE ?)";
        $countSql .= " AND (p.title LIKE ? OR p.description LIKE ?)";
        $params[] = "%$query%";
        $params[] = "%$query%";
    }

    // Kategorie
    if ($categoryId > 0) {
        $sql .= " AND p.category_id = ?";
        $countSql .= " AND p.category_id = ?";
        $params[] = $categoryId;
    }

    // Marke
    if ($makeId > 0) {
        $sql .= " AND p.make_id = ?";
        $countSql .= " AND p.make_id = ?";
        $params[] = $makeId;
        
        // Modell (nur wenn Marke ausgewählt ist)
        if ($modelId > 0) {
            $sql .= " AND p.model_id = ?";
            $countSql .= " AND p.model_id = ?";
            $params[] = $modelId;
        }
    }

    // Preis
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

    // Zustand
    if (!empty($condition)) {
        $sql .= " AND p.condition_rating = ?";
        $countSql .= " AND p.condition_rating = ?";
        $params[] = $condition;
    }

    // Standort
    if (!empty($location)) {
        $sql .= " AND (p.location LIKE ? OR u.city LIKE ?)";
        $countSql .= " AND (p.location LIKE ? OR u.city LIKE ?)";
        $params[] = "%$location%";
        $params[] = "%$location%";
    }

    // Baujahr
    if ($yearFrom > 0) {
        $sql .= " AND (p.year_from >= ? OR p.year_to >= ?)";
        $countSql .= " AND (p.year_from >= ? OR p.year_to >= ?)";
        $params[] = $yearFrom;
        $params[] = $yearFrom;
    }

    if ($yearTo > 0) {
        $sql .= " AND (p.year_from <= ? OR p.year_to <= ?)";
        $countSql .= " AND (p.year_from <= ? OR p.year_to <= ?)";
        $params[] = $yearTo;
        $params[] = $yearTo;
    }

    // Sortierung
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

    // Gesamtanzahl der Ergebnisse ermitteln
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $totalResults = $countStmt->fetchColumn();
    $totalPages = ceil($totalResults / $perPage);

    // Pagination hinzufügen
    $sql .= " LIMIT $perPage OFFSET $offset";

    // Suchergebnisse abrufen
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $results = $stmt->fetchAll();
}

// Kategorien für das Suchformular abrufen
$categoriesStmt = $pdo->query("SELECT category_id, name FROM categories ORDER BY name");
$categories = $categoriesStmt->fetchAll();

// Marken für das Suchformular abrufen
$makesStmt = $pdo->query("SELECT make_id, name FROM car_makes ORDER BY name");
$makes = $makesStmt->fetchAll();

// Modelle abrufen, wenn eine Marke ausgewählt ist
$models = [];
if ($makeId > 0) {
    $modelsStmt = $pdo->prepare("SELECT model_id, name FROM car_models WHERE make_id = ? ORDER BY name");
    $modelsStmt->execute([$makeId]);
    $models = $modelsStmt->fetchAll();
}

// Funktion zum Erstellen der Pagination-URL
function buildPaginationUrl($page) {
    $params = $_GET;
    $params['page'] = $page;
    return '?' . http_build_query($params);
}
?>

<div class="advanced-search-container">
    <div class="search-header">
        <h1><i class="fas fa-search"></i> Erweiterte Suche</h1>
        <p>Finden Sie genau das Autoteil, das Sie suchen</p>
    </div>
    
    <div class="search-content">
        <div class="search-sidebar">
            <form method="GET" action="" class="advanced-search-form">
                <div class="form-section">
                    <h3>Suchbegriff</h3>
                    <div class="form-group">
                        <input type="text" name="q" placeholder="Suchbegriff eingeben..." class="form-control" value="<?php echo htmlspecialchars($query); ?>">
                    </div>
                </div>
                
                <div class="form-section">
                    <h3>Kategorie & Fahrzeug</h3>
                    <div class="form-group">
                        <label for="category_id">Kategorie</label>
                        <select id="category_id" name="category_id" class="form-control">
                            <option value="">Alle Kategorien</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['category_id']; ?>" <?php echo ($categoryId == $category['category_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="make_id">Marke</label>
                        <select id="make_id" name="make_id" class="form-control">
                            <option value="">Alle Marken</option>
                            <?php foreach ($makes as $make): ?>
                                <option value="<?php echo $make['make_id']; ?>" <?php echo ($makeId == $make['make_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($make['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="model_id">Modell</label>
                        <select id="model_id" name="model_id" class="form-control" <?php echo empty($makeId) ? 'disabled' : ''; ?>>
                            <option value="">Alle Modelle</option>
                            <?php foreach ($models as $model): ?>
                                <option value="<?php echo $model['model_id']; ?>" <?php echo ($modelId == $model['model_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($model['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="year_from">Baujahr von</label>
                            <select id="year_from" name="year_from" class="form-control">
                                <option value="">Alle Jahre</option>
                                <?php
                                $currentYear = (int)date('Y');
                                for ($year = $currentYear; $year >= 1950; $year--): 
                                ?>
                                    <option value="<?php echo $year; ?>" <?php echo ($yearFrom == $year) ? 'selected' : ''; ?>>
                                        <?php echo $year; ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="year_to">Baujahr bis</label>
                            <select id="year_to" name="year_to" class="form-control">
                                <option value="">Alle Jahre</option>
                                <?php for ($year = $currentYear; $year >= 1950; $year--): ?>
                                    <option value="<?php echo $year; ?>" <?php echo ($yearTo == $year) ? 'selected' : ''; ?>>
                                        <?php echo $year; ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="form-section">
                    <h3>Preis & Zustand</h3>
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
                </div>
                
                <div class="form-section">
                    <h3>Standort & Sortierung</h3>
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
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary btn-block">
                        <i class="fas fa-search"></i> Suchen
                    </button>
                    <a href="<?php echo SITE_URL; ?>/advanced-search.php" class="btn btn-outline btn-block">
                        <i class="fas fa-redo"></i> Filter zurücksetzen
                    </a>
                </div>
            </form>
        </div>
        
        <div class="search-results">
            <?php if ($isSearch): ?>
                <div class="results-header">
                    <h2>Suchergebnisse</h2>
                    <?php if (isset($totalResults)): ?>
                        <p><?php echo $totalResults; ?> Ergebnisse gefunden</p>
                    <?php endif; ?>
                </div>
                
                <?php if (isset($results) && count($results) > 0): ?>
                    <div class="listings-grid">
                        <?php foreach ($results as $result): ?>
                            <div class="listing-card">
                                <div class="listing-image">
                                    <a href="<?php echo SITE_URL; ?>/listing.php?id=<?php echo $result['part_id']; ?>">
                                        <?php if (!empty($result['image_path'])): ?>
                                            <img src="<?php echo SITE_URL . '/' . $result['image_path']; ?>" alt="<?php echo htmlspecialchars($result['title']); ?>">
                                        <?php else: ?>
                                            <div class="no-image">
                                                <i class="fas fa-image"></i>
                                                <span>Kein Bild</span>
                                            </div>
                                        <?php endif; ?>
                                    </a>
                                    <?php if ($result['is_negotiable']): ?>
                                        <span class="negotiable-badge">VB</span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="listing-details">
                                    <h3 class="listing-title">
                                        <a href="<?php echo SITE_URL; ?>/listing.php?id=<?php echo $result['part_id']; ?>">
                                            <?php echo htmlspecialchars($result['title']); ?>
                                        </a>
                                    </h3>
                                    
                                    <div class="listing-meta">
                                        <span class="listing-category">
                                            <i class="fas fa-tag"></i> <?php echo htmlspecialchars($result['category_name']); ?>
                                        </span>
                                        <?php if (!empty($result['make_name'])): ?>
                                            <span class="listing-make">
                                                <i class="fas fa-car"></i> 
                                                <?php 
                                                    echo htmlspecialchars($result['make_name']);
                                                    if (!empty($result['model_name'])) {
                                                        echo ' ' . htmlspecialchars($result['model_name']);
                                                    }
                                                ?>
                                            </span>
                                        <?php endif; ?>
                                        <span class="listing-location">
                                            <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($result['location'] ?: $result['user_city'] ?: 'Österreich'); ?>
                                        </span>
                                    </div>
                                    
                                    <div class="listing-price">
                                        <?php echo number_format($result['price'], 2, ',', '.'); ?> €
                                    </div>
                                    
                                    <div class="listing-footer">
                                        <span class="listing-date">
                                            <i class="far fa-clock"></i> <?php echo date('d.m.Y', strtotime($result['date_posted'])); ?>
                                        </span>
                                        <span class="listing-views">
                                            <i class="far fa-eye"></i> <?php echo $result['views']; ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <?php if ($totalPages > 1): ?>
                        <div class="pagination">
                            <?php if ($page > 1): ?>
                                <a href="<?php echo buildPaginationUrl($page - 1); ?>" class="pagination-link">
                                    <i class="fas fa-chevron-left"></i> Zurück
                                </a>
                            <?php endif; ?>
                            
                            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                <a href="<?php echo buildPaginationUrl($i); ?>" class="pagination-link <?php echo ($i == $page) ? 'active' : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>
                            
                            <?php if ($page < $totalPages): ?>
                                <a href="<?php echo buildPaginationUrl($page + 1); ?>" class="pagination-link">
                                    Weiter <i class="fas fa-chevron-right"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="no-results">
                        <i class="fas fa-search fa-3x"></i>
                        <h3>Keine Ergebnisse gefunden</h3>
                        <p>Versuchen Sie es mit anderen Suchkriterien oder weniger Filtern.</p>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="search-intro">
                    <div class="search-intro-icon">
                        <i class="fas fa-search"></i>
                    </div>
                    <h2>Finden Sie das perfekte Autoteil</h2>
                    <p>Verwenden Sie die Filter auf der linken Seite, um Ihre Suche zu starten.</p>
                    <p>Sie können nach Kategorie, Marke, Modell, Preis und vielem mehr filtern.</p>
                </div>
                
                <div class="popular-searches">
                    <h3>Beliebte Kategorien</h3>
                    <div class="popular-categories">
                        <?php
                        $popularCategoriesStmt = $pdo->query("
                            SELECT c.category_id, c.name, COUNT(p.part_id) as count
                            FROM categories c
                            JOIN parts p ON c.category_id = p.category_id
                            WHERE p.is_sold = 0
                            GROUP BY c.category_id
                            ORDER BY count DESC
                            LIMIT 6
                        ");
                        $popularCategories = $popularCategoriesStmt->fetchAll();
                        
                        foreach ($popularCategories as $category):
                        ?>
                            <a href="?category_id=<?php echo $category['category_id']; ?>" class="category-badge">
                                <?php echo htmlspecialchars($category['name']); ?> (<?php echo $category['count']; ?>)
                            </a>
                        <?php endforeach; ?>
                    </div>
                    
                    <h3>Beliebte Marken</h3>
                    <div class="popular-makes">
                        <?php
                        $popularMakesStmt = $pdo->query("
                            SELECT m.make_id, m.name, COUNT(p.part_id) as count
                            FROM car_makes m
                            JOIN parts p ON m.make_id = p.make_id
                            WHERE p.is_sold = 0
                            GROUP BY m.make_id
                            ORDER BY count DESC
                            LIMIT 6
                        ");
                        $popularMakes = $popularMakesStmt->fetchAll();
                        
                        foreach ($popularMakes as $make):
                        ?>
                            <a href="?make_id=<?php echo $make['make_id']; ?>" class="make-badge">
                                <?php echo htmlspecialchars($make['name']); ?> (<?php echo $make['count']; ?>)
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
/* Advanced Search Page Styles */
.advanced-search-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px 15px;
}

.search-header {
    text-align: center;
    margin-bottom: 30px;
}

.search-header h1 {
    font-size: 2.2rem;
    color: #2c3e50;
    margin-bottom: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.search-header h1 i {
    margin-right: 15px;
    color: #3498db;
}

.search-header p {
    color: #7f8c8d;
    font-size: 1.1rem;
}

.search-content {
    display: grid;
    grid-template-columns: 300px 1fr;
    gap: 30px;
}

.search-sidebar {
    align-self: start;
}

.advanced-search-form {
    position: sticky;
    top: 20px;
}

.form-section {
    background-color: white;
    border-radius: 10px;
    padding: 20px;
    margin-bottom: 20px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
}

.form-section h3 {
    font-size: 1.2rem;
    color: #2c3e50;
    margin-top: 0;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 1px solid #eee;
}

.form-group {
    margin-bottom: 15px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: #2c3e50;
}

.form-control {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid #ddd;
    border-radius: 6px;
    font-size: 1rem;
    transition: border-color 0.3s ease, box-shadow 0.3s ease;
}

.form-control:focus {
    border-color: #3498db;
    box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.2);
    outline: none;
}

.form-row {
    display: flex;
    gap: 10px;
}

.form-row .form-group {
    flex: 1;
}

.price-range {
    display: flex;
    align-items: center;
    gap: 10px;
}

.price-range input {
    flex: 1;
}

.price-range span {
    color: #7f8c8d;
}

.form-actions {
    margin-top: 20px;
}

.btn-block {
    display: block;
    width: 100%;
    margin-bottom: 10px;
}

.results-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.results-header h2 {
    font-size: 1.5rem;
    color: #2c3e50;
    margin: 0;
}

.results-header p {
    color: #7f8c8d;
}

.listings-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.listing-card {
    background-color: white;
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
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
    flex-direction: column;
    gap: 5px;
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
    width: 16px;
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

.listing-date, .listing-views {
    display: flex;
    align-items: center;
}

.listing-footer i {
    margin-right: 5px;
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

.no-results {
    text-align: center;
    padding: 50px 0;
    background-color: white;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
}

.no-results i {
    margin-bottom: 20px;
    color: #bdc3c7;
}

.no-results h3 {
    margin-bottom: 10px;
    color: #2c3e50;
}

.no-results p {
    color: #7f8c8d;
}

.search-intro {
    text-align: center;
    padding: 50px 0;
    background-color: white;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    margin-bottom: 30px;
}

.search-intro-icon {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background-color: #f0f7ff;
    color: #3498db;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 20px;
    font-size: 2rem;
}

.search-intro h2 {
    margin-bottom: 15px;
    color: #2c3e50;
}

.search-intro p {
    color: #7f8c8d;
    max-width: 600px;
    margin: 0 auto 10px;
}

.popular-searches {
    background-color: white;
    border-radius: 10px;
    padding: 25px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
}

.popular-searches h3 {
    font-size: 1.3rem;
    color: #2c3e50;
    margin-top: 0;
    margin-bottom: 15px;
}

.popular-categories, .popular-makes {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-bottom: 25px;
}

.category-badge, .make-badge {
    display: inline-block;
    padding: 8px 15px;
    background-color: #f8f9fa;
    color: #2c3e50;
    border-radius: 20px;
    text-decoration: none;
    transition: all 0.3s ease;
}

.category-badge:hover, .make-badge:hover {
    background-color: #3498db;
    color: white;
}

/* Responsive adjustments */
@media (max-width: 992px) {
    .search-content {
        grid-template-columns: 1fr;
    }
    
    .advanced-search-form {
        position: static;
    }
    
    .search-sidebar {
        order: 2;
    }
    
    .search-results {
        order: 1;
    }
}

@media (max-width: 768px) {
    .form-row {
        flex-direction: column;
        gap: 0;
    }
    
    .listings-grid {
        grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    }
}

@media (max-width: 576px) {
    .listings-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Dynamisches Laden der Modelle basierend auf der ausgewählten Marke
    const makeSelect = document.getElementById('make_id');
    const modelSelect = document.getElementById('model_id');
    
    if (makeSelect && modelSelect) {
        makeSelect.addEventListener('change', function() {
            const makeId = this.value;
            
            // Modell-Dropdown zurücksetzen
            modelSelect.innerHTML = '<option value="">Alle Modelle</option>';
            
            if (makeId) {
                // Modelle für die ausgewählte Marke laden
                modelSelect.disabled = true;
                
                fetch(`get-models.php?make_id=${makeId}`)
                    .then(response => response.json())
                    .then(data => {
                        modelSelect.disabled = false;
                        
                        if (Array.isArray(data) && data.length > 0) {
                            data.forEach(model => {
                                const option = document.createElement('option');
                                option.value = model.model_id;
                                option.textContent = model.name;
                                modelSelect.appendChild(option);
                            });
                        }
                    })
                    .catch(error => {
                        console.error('Fehler beim Laden der Modelle:', error);
                        modelSelect.disabled = false;
                    });
            } else {
                modelSelect.disabled = true;
            }
        });
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>

