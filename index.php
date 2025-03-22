<?php
$pageTitle = 'Startseite';
require_once 'includes/header.php';

// Hole neueste Inserate
$stmt = $pdo->query("
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
    LIMIT 8
");
$latestListings = $stmt->fetchAll();

// Hole beliebte Kategorien
$stmt = $pdo->query("
    SELECT c.*, COUNT(p.part_id) AS listing_count
    FROM categories c
    LEFT JOIN parts p ON c.category_id = p.category_id
    GROUP BY c.category_id
    ORDER BY listing_count DESC
    LIMIT 6
");
$popularCategories = $stmt->fetchAll();

// Hole beliebte Marken
$stmt = $pdo->query("
    SELECT cm.*, COUNT(p.part_id) AS listing_count
    FROM car_makes cm
    LEFT JOIN parts p ON cm.make_id = p.make_id
    GROUP BY cm.make_id
    ORDER BY listing_count DESC
    LIMIT 6
");
$popularMakes = $stmt->fetchAll();
?>

<div class="hero-section">
  <div class="hero-overlay"></div>
  <div class="container">
    <div class="hero-content">
      <h1>Gebrauchte Autoteile kaufen und verkaufen</h1>
      <p>Finden Sie das richtige Teil für Ihr Fahrzeug oder verkaufen Sie Ihre gebrauchten Teile</p>
      
      <div class="search-box">
        <form action="search.php" method="GET">
          <div class="search-input-group">
            <div class="search-icon">
              <i class="fas fa-search"></i>
            </div>
            <input type="text" name="q" placeholder="Was suchen Sie? z.B. BMW Stoßstange, Scheinwerfer, Bremsen..." class="form-control">
            <button type="submit" class="btn btn-primary">
              Suchen
            </button>
          </div>
          <div class="advanced-search-link">
            <a href="advanced-search.php">Erweiterte Suche <i class="fas fa-angle-right"></i></a>
          </div>
        </form>
      </div>
      
      <div class="hero-features">
        <div class="feature">
          <div class="feature-icon">
            <i class="fas fa-car"></i>
          </div>
          <div class="feature-text">Tausende Teile</div>
        </div>
        <div class="feature">
          <div class="feature-icon">
            <i class="fas fa-check-circle"></i>
          </div>
          <div class="feature-text">Geprüfte Verkäufer</div>
        </div>
        <div class="feature">
          <div class="feature-icon">
            <i class="fas fa-shield-alt"></i>
          </div>
          <div class="feature-text">Sicherer Kauf</div>
        </div>
        <div class="feature">
          <div class="feature-icon">
            <i class="fas fa-truck"></i>
          </div>
          <div class="feature-text">Schneller Versand</div>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="container">
    <!-- Kategorien -->
    <section class="home-section">
        <div class="section-header">
            <h2>Beliebte Kategorien</h2>
            <a href="categories.php" class="view-all">Alle anzeigen <i class="fas fa-angle-right"></i></a>
        </div>
        
        <div class="categories-grid">
            <?php foreach ($popularCategories as $category): ?>
                <a href="search.php?category=<?php echo $category['category_id']; ?>" class="category-card">
                    <div class="category-icon">
                        <i class="<?php echo !empty($category['icon']) ? $category['icon'] : 'fas fa-cog'; ?>"></i>
                    </div>
                    <h3><?php echo htmlspecialchars($category['name']); ?></h3>
                    <span class="listing-count"><?php echo $category['listing_count']; ?> Teile</span>
                </a>
            <?php endforeach; ?>
        </div>
    </section>
    
    <!-- Marken -->
    <section class="home-section">
        <div class="section-header">
            <h2>Beliebte Marken</h2>
            <a href="makes.php" class="view-all">Alle anzeigen <i class="fas fa-angle-right"></i></a>
        </div>
        
        <div class="makes-grid">
            <?php foreach ($popularMakes as $make): ?>
                <a href="search.php?make=<?php echo $make['make_id']; ?>" class="make-card">
                    <h3><?php echo htmlspecialchars($make['name']); ?></h3>
                    <span class="listing-count"><?php echo $make['listing_count']; ?> Teile</span>
                </a>
            <?php endforeach; ?>
        </div>
    </section>
    
    <!-- Neueste Inserate -->
    <section class="home-section">
        <div class="section-header">
            <h2>Neueste Inserate</h2>
            <a href="search.php" class="view-all">Alle anzeigen <i class="fas fa-angle-right"></i></a>
        </div>
        
        <div class="latest-listings">
            <?php if (empty($latestListings)): ?>
                <div class="no-listings">
                    <p>Keine Inserate gefunden.</p>
                </div>
            <?php else: ?>
                <div class="listings-grid">
                    <?php foreach ($latestListings as $listing): ?>
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
                                    <span class="listing-views">
                                        <i class="far fa-eye"></i> <?php echo $listing['view_count']; ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>
    
    <!-- Wie es funktioniert -->
    <section class="home-section how-it-works">
        <div class="section-header">
            <h2>Wie es funktioniert</h2>
        </div>
        
        <div class="steps-container">
            <div class="step">
                <div class="step-icon">
                    <i class="fas fa-search"></i>
                </div>
                <h3>Finden</h3>
                <p>Suchen Sie nach dem benötigten Autoteil in unserer umfangreichen Datenbank</p>
            </div>
            
            <div class="step">
                <div class="step-icon">
                    <i class="fas fa-comments"></i>
                </div>
                <h3>Kontaktieren</h3>
                <p>Kontaktieren Sie den Verkäufer direkt und klären Sie alle Details</p>
            </div>
            
            <div class="step">
                <div class="step-icon">
                    <i class="fas fa-handshake"></i>
                </div>
                <h3>Kaufen</h3>
                <p>Vereinbaren Sie die Zahlung und den Versand oder die Abholung</p>
            </div>
            
            <div class="step">
                <div class="step-icon">
                    <i class="fas fa-plus-circle"></i>
                </div>
                <h3>Verkaufen</h3>
                <p>Erstellen Sie ein Inserat und verkaufen Sie Ihre gebrauchten Autoteile</p>
            </div>
        </div>
    </section>
</div>

<?php require_once 'includes/footer.php'; ?>

