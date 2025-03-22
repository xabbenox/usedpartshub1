<?php
$pageTitle = 'Kategorie';
require_once 'includes/header.php';

// Aktiviere Fehleranzeige für Debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Kategorie-ID aus der URL abrufen
$categoryId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$categoryId) {
    $_SESSION['error'] = 'Ungültige Kategorie-ID';
    redirect(SITE_URL);
}

// Debug-Ausgabe
echo "<!-- DEBUG: Kategorie-ID = $categoryId -->";

// Kategorie-Informationen abrufen
$stmt = $pdo->prepare("SELECT * FROM categories WHERE category_id = ?");
$stmt->execute([$categoryId]);
$category = $stmt->fetch();

if (!$category) {
    $_SESSION['error'] = 'Kategorie nicht gefunden';
    redirect(SITE_URL);
}

// Seitennummerierung
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 12;
$offset = ($page - 1) * $perPage;

// Filter
$sort = isset($_GET['sort']) ? sanitizeInput($_GET['sort']) : 'newest';
$makeId = isset($_GET['make_id']) ? (int)$_GET['make_id'] : 0;
$minPrice = isset($_GET['min_price']) ? (float)$_GET['min_price'] : 0;
$maxPrice = isset($_GET['max_price']) ? (float)$_GET['max_price'] : 0;
$condition = isset($_GET['condition']) ? sanitizeInput($_GET['condition']) : '';

// Überprüfe die Datenbankstruktur
echo "<!-- DEBUG: Überprüfe Datenbankstruktur -->";
try {
    // Überprüfe, ob die Tabelle 'parts' existiert
    $tableCheckStmt = $pdo->query("SHOW TABLES");
    $tables = $tableCheckStmt->fetchAll(PDO::FETCH_COLUMN);
    echo "<!-- DEBUG: Gefundene Tabellen: " . implode(", ", $tables) . " -->";
    
    if (!in_array('parts', $tables)) {
        echo "<div class='alert alert-danger'>Die Tabelle 'parts' existiert nicht in der Datenbank.</div>";
    } else {
        // Überprüfe die Spalten der Tabelle 'parts'
        $columnsStmt = $pdo->query("DESCRIBE parts");
        $columns = [];
        while ($row = $columnsStmt->fetch(PDO::FETCH_ASSOC)) {
            $columns[] = $row['Field'];
        }
        echo "<!-- DEBUG: Spalten in 'parts': " . implode(", ", $columns) . " -->";
        
        if (!in_array('category_id', $columns)) {
            echo "<div class='alert alert-danger'>Die Spalte 'category_id' existiert nicht in der Tabelle 'parts'.</div>";
        }
    }
} catch (PDOException $e) {
    echo "<div class='alert alert-danger'>Datenbankfehler: " . $e->getMessage() . "</div>";
}

// Direkte Überprüfung, ob Teile mit dieser Kategorie-ID existieren
try {
    $checkCategoryStmt = $pdo->prepare("SELECT COUNT(*) FROM parts WHERE category_id = ?");
    $checkCategoryStmt->execute([$categoryId]);
    $categoryCount = $checkCategoryStmt->fetchColumn();
    echo "<!-- DEBUG: Anzahl der Teile in Kategorie $categoryId: $categoryCount -->";
    
    // Zeige ein Beispiel-Teil aus dieser Kategorie
    if ($categoryCount > 0) {
        $sampleStmt = $pdo->prepare("SELECT part_id, title, category_id FROM parts WHERE category_id = ? LIMIT 1");
        $sampleStmt->execute([$categoryId]);
        $samplePart = $sampleStmt->fetch(PDO::FETCH_ASSOC);
        echo "<!-- DEBUG: Beispiel-Teil: " . json_encode($samplePart) . " -->";
    }
} catch (PDOException $e) {
    echo "<div class='alert alert-danger'>Fehler bei der Überprüfung der Kategorie: " . $e->getMessage() . "</div>";
}

// Versuche eine einfachere Abfrage ohne Joins
$simpleSql = "SELECT * FROM parts WHERE category_id = ? AND is_sold = 0 LIMIT ?";
$simpleStmt = $pdo->prepare($simpleSql);
$simpleStmt->bindValue(1, $categoryId, PDO::PARAM_INT);
$simpleStmt->bindValue(2, $perPage, PDO::PARAM_INT);
$simpleStmt->execute();
$simpleParts = $simpleStmt->fetchAll();
echo "<!-- DEBUG: Anzahl der Teile mit einfacher Abfrage: " . count($simpleParts) . " -->";

// SQL-Abfrage für Teile in dieser Kategorie
$sql = "
    SELECT p.*, 
           u.username, 
           c.name AS category_name,
           (SELECT file_path FROM images WHERE part_id = p.part_id AND is_primary = 1 LIMIT 1) AS image_path,
           m.name as make_name, 
           mo.name as model_name
    FROM parts p
    JOIN users u ON p.user_id = u.user_id
    JOIN categories c ON p.category_id = c.category_id
    LEFT JOIN car_makes m ON p.make_id = m.make_id
    LEFT JOIN car_models mo ON p.model_id = mo.model_id
    WHERE p.category_id = ? AND p.is_sold = 0
";

$countSql = "
    SELECT COUNT(*) 
    FROM parts p
    WHERE p.category_id = ? AND p.is_sold = 0
";

// Parameter für die Abfrage
$params = [$categoryId];

// Filter hinzufügen
if ($makeId > 0) {
    $sql .= " AND p.make_id = ?";
    $countSql .= " AND p.make_id = ?";
    $params[] = $makeId;
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

// Pagination
$sql .= " LIMIT ? OFFSET ?";
$paginationParams = $params;
$paginationParams[] = $perPage;
$paginationParams[] = $offset;

// Gesamtanzahl der Teile in dieser Kategorie
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalItems = $countStmt->fetchColumn();
$totalPages = ceil($totalItems / $perPage);

// Debug-Ausgabe der SQL-Abfrage
echo "<!-- DEBUG: SQL = $sql -->";
echo "<!-- DEBUG: Parameter = " . implode(", ", $params) . " -->";
echo "<!-- DEBUG: Pagination Parameter = " . implode(", ", $paginationParams) . " -->";

// Teile abrufen
$stmt = $pdo->prepare($sql);
$stmt->execute($paginationParams);
$parts = $stmt->fetchAll();

// Wenn die Hauptabfrage keine Ergebnisse liefert, verwende die einfache Abfrage
if (empty($parts) && !empty($simpleParts)) {
    $parts = $simpleParts;
    echo "<!-- DEBUG: Verwende Ergebnisse der einfachen Abfrage -->";
}

// Beliebte Marken in dieser Kategorie
$makeStmt = $pdo->prepare("
    SELECT m.*, COUNT(p.part_id) as count
    FROM car_makes m
    JOIN parts p ON m.make_id = p.make_id
    WHERE p.category_id = ? AND p.is_sold = 0
    GROUP BY m.make_id
    ORDER BY count DESC
    LIMIT 10
");
$makeStmt->execute([$categoryId]);
$popularMakes = $makeStmt->fetchAll();

// Funktion zum Erstellen der Pagination-URL
function buildPaginationUrl($page, $sort, $makeId, $minPrice, $maxPrice, $condition, $categoryId) {
    $url = "?id=$categoryId&page=$page";
    if ($sort) $url .= "&sort=$sort";
    if ($makeId) $url .= "&make_id=$makeId";
    if ($minPrice) $url .= "&min_price=$minPrice";
    if ($maxPrice) $url .= "&max_price=$maxPrice";
    if ($condition) $url .= "&condition=$condition";
    return $url;
}

$pageTitle = htmlspecialchars($category['name']);
?>

<div class="category-container">
    <div class="category-header">
        <h1><?php echo htmlspecialchars($category['name']); ?></h1>
        <?php if (!empty($category['description'])): ?>
            <p class="category-description"><?php echo htmlspecialchars($category['description']); ?></p>
        <?php endif; ?>
        <div class="category-stats">
            <span><?php echo $totalItems; ?> Teile gefunden</span>
        </div>
    </div>

    <div class="category-content">
        <div class="category-sidebar">
            <div class="filter-section">
                <h3>Filter</h3>
                <form action="" method="GET" class="filter-form">
                    <input type="hidden" name="id" value="<?php echo $categoryId; ?>">
                    
                    <?php if (!empty($popularMakes)): ?>
                        <div class="filter-group">
                            <label for="make_id">Marke</label>
                            <select id="make_id" name="make_id" class="form-control">
                                <option value="">Alle Marken</option>
                                <?php foreach ($popularMakes as $make): ?>
                                    <option value="<?php echo $make['make_id']; ?>" <?php echo ($makeId == $make['make_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($make['name']); ?> (<?php echo $make['count']; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>
                    
                    <div class="filter-group">
                        <label>Preis (€)</label>
                        <div class="price-range">
                            <input type="number" name="min_price" placeholder="Min" class="form-control" value="<?php echo $minPrice > 0 ? $minPrice : ''; ?>">
                            <span>-</span>
                            <input type="number" name="max_price" placeholder="Max" class="form-control" value="<?php echo $maxPrice > 0 ? $maxPrice : ''; ?>">
                        </div>
                    </div>
                    
                    <div class="filter-group">
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
                    
                    <div class="filter-group">
                        <label for="sort">Sortieren nach</label>
                        <select id="sort" name="sort" class="form-control">
                            <option value="newest" <?php echo ($sort === 'newest') ? 'selected' : ''; ?>>Neueste zuerst</option>
                            <option value="oldest" <?php echo ($sort === 'oldest') ? 'selected' : ''; ?>>Älteste zuerst</option>
                            <option value="price_asc" <?php echo ($sort === 'price_asc') ? 'selected' : ''; ?>>Preis aufsteigend</option>
                            <option value="price_desc" <?php echo ($sort === 'price_desc') ? 'selected' : ''; ?>>Preis absteigend</option>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-block">Filter anwenden</button>
                    <a href="<?php echo "category.php?id=$categoryId"; ?>" class="btn btn-outline btn-block">Filter zurücksetzen</a>
                </form>
            </div>
        </div>
        
        <div class="category-main">
            <?php if (empty($parts)): ?>
                <div class="no-results">
                    <i class="fas fa-search fa-3x"></i>
                    <h2>Keine Teile gefunden</h2>
                    <p>Versuchen Sie es mit anderen Filtereinstellungen oder schauen Sie später wieder vorbei.</p>
                </div>
            <?php else: ?>
                <div class="listings-grid">
                    <?php foreach ($parts as $part): ?>
                        <div class="listing-card">
                            <div class="listing-image">
                                <a href="listing.php?id=<?php echo $part['part_id']; ?>">
                                    <?php if (!empty($part['image_path'])): ?>
                                        <img src="<?php echo htmlspecialchars($part['image_path']); ?>" alt="<?php echo htmlspecialchars($part['title']); ?>">
                                    <?php else: ?>
                                        <div class="no-image">
                                            <i class="fas fa-image"></i>
                                            <span>Kein Bild</span>
                                        </div>
                                    <?php endif; ?>
                                </a>
                                <?php if (isset($part['is_negotiable']) && $part['is_negotiable']): ?>
                                    <span class="negotiable-badge">VB</span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="listing-details">
                                <h3 class="listing-title">
                                    <a href="listing.php?id=<?php echo $part['part_id']; ?>">
                                        <?php echo htmlspecialchars($part['title']); ?>
                                    </a>
                                </h3>
                                
                                <div class="listing-meta">
                                    <?php if (isset($part['make_name']) && $part['make_name']): ?>
                                        <span class="listing-make">
                                            <i class="fas fa-car"></i> 
                                            <?php 
                                                echo htmlspecialchars($part['make_name']);
                                                if (isset($part['model_name']) && $part['model_name']) {
                                                    echo ' ' . htmlspecialchars($part['model_name']);
                                                }
                                            ?>
                                        </span>
                                    <?php endif; ?>
                                    <span class="listing-location">
                                        <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($part['location']); ?>
                                    </span>
                                </div>
                                
                                <div class="listing-price">
                                    <?php echo number_format($part['price'], 2, ',', '.'); ?> €
                                </div>
                                
                                <div class="listing-footer">
                                    <span class="listing-date">
                                        <i class="far fa-clock"></i> <?php echo date('d.m.Y', strtotime($part['date_posted'])); ?>
                                    </span>
                                    <span class="listing-views">
                                        <i class="far fa-eye"></i> <?php echo $part['views']; ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <?php if ($totalPages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="<?php echo buildPaginationUrl($page - 1, $sort, $makeId, $minPrice, $maxPrice, $condition, $categoryId); ?>">
                                &laquo; Zurück
                            </a>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                            <?php if ($i == $page): ?>
                                <span class="active"><?php echo $i; ?></span>
                            <?php else: ?>
                                <a href="<?php echo buildPaginationUrl($i, $sort, $makeId, $minPrice, $maxPrice, $condition, $categoryId); ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endif; ?>
                        <?php endfor; ?>
                        
                        <?php if ($page < $totalPages): ?>
                            <a href="<?php echo buildPaginationUrl($page + 1, $sort, $makeId, $minPrice, $maxPrice, $condition, $categoryId); ?>">
                                Weiter &raquo;
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.category-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px 15px;
}

.category-header {
    margin-bottom: 30px;
    text-align: center;
}

.category-header h1 {
    font-size: 2.2rem;
    color: #2c3e50;
    margin-bottom: 10px;
}

.category-description {
    color: #7f8c8d;
    margin-bottom: 15px;
    max-width: 800px;
    margin-left: auto;
    margin-right: auto;
}

.category-stats {
    color: #95a5a6;
    font-size: 0.9rem;
}

.category-content {
    display: grid;
    grid-template-columns: 250px 1fr;
    gap: 30px;
}

.category-sidebar {
    align-self: start;
}

.filter-section {
    background-color: white;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
}

.filter-section h3 {
    margin-top: 0;
    margin-bottom: 20px;
    font-size: 1.2rem;
    color: #2c3e50;
    border-bottom: 1px solid #eee;
    padding-bottom: 10px;
}

.filter-group {
    margin-bottom: 20px;
}

.filter-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 500;
    color: #2c3e50;
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

.btn-block {
    display: block;
    width: 100%;
    margin-bottom: 10px;
}

.no-results {
    text-align: center;
    padding: 50px 0;
    color: #7f8c8d;
}

.no-results i {
    margin-bottom: 20px;
    color: #bdc3c7;
}

.no-results h2 {
    margin-bottom: 10px;
    color: #2c3e50;
}

.alert {
    padding: 15px;
    margin-bottom: 20px;
    border-radius: 5px;
}

.alert-danger {
    background-color: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.alert-warning {
    background-color: #fff3cd;
    color: #856404;
    border: 1px solid #ffeeba;
}

@media (max-width: 768px) {
    .category-content {
        grid-template-columns: 1fr;
    }
    
    .category-sidebar {
        order: 2;
    }
    
    .category-main {
        order: 1;
    }
}
</style>

<?php require_once 'includes/footer.php'; ?>

