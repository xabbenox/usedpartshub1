<?php
$pageTitle = 'Inserat Details';
require_once 'includes/header.php';

// Get listing ID
$partId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$partId) {
    $_SESSION['error'] = 'Ungültige Inserat-ID';
    redirect(SITE_URL);
}

// Get listing details
$stmt = $pdo->prepare("
    SELECT p.*, c.name as category_name, m.name as make_name, mo.name as model_name,
           u.username, u.city, u.registration_date
    FROM parts p
    LEFT JOIN categories c ON p.category_id = c.category_id
    LEFT JOIN car_makes m ON p.make_id = m.make_id
    LEFT JOIN car_models mo ON p.model_id = mo.model_id
    LEFT JOIN users u ON p.user_id = u.user_id
    WHERE p.part_id = ?
");
$stmt->execute([$partId]);
$listing = $stmt->fetch();

if (!$listing) {
    $_SESSION['error'] = 'Inserat nicht gefunden';
    redirect(SITE_URL);
}

// Update view count
$stmt = $pdo->prepare("UPDATE parts SET views = views + 1 WHERE part_id = ?");
$stmt->execute([$partId]);

// Get listing images
$stmt = $pdo->prepare("SELECT * FROM images WHERE part_id = ? ORDER BY is_primary DESC, image_id ASC");
$stmt->execute([$partId]);
$images = $stmt->fetchAll();

// Check if user has favorited this listing
$isFavorite = false;
if (isLoggedIn()) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM favorites WHERE user_id = ? AND part_id = ?");
    $stmt->execute([$_SESSION['user_id'], $partId]);
    $isFavorite = ($stmt->fetchColumn() > 0);
}

// Get similar listings
$stmt = $pdo->prepare("
    SELECT p.*, i.file_path
    FROM parts p
    LEFT JOIN images i ON p.part_id = i.part_id AND i.is_primary = 1
    WHERE p.category_id = ? AND p.part_id != ? AND p.is_sold = 0
    ORDER BY p.date_posted DESC
    LIMIT 4
");
$stmt->execute([$listing['category_id'], $partId]);
$similarListings = $stmt->fetchAll();

// Process message form
$messageSent = false;
$messageError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    if (!isLoggedIn()) {
        // Store current URL for redirect after login
        $_SESSION['redirect_after_login'] = SITE_URL . '/listing.php?id=' . $partId;
        redirect(SITE_URL . '/login.php');
    }
    
    $message = sanitizeInput($_POST['message'] ?? '');
    
    if (empty($message)) {
        $messageError = 'Bitte geben Sie eine Nachricht ein';
    } else {
        // Insert message
        $stmt = $pdo->prepare("
            INSERT INTO messages (sender_id, receiver_id, part_id, subject, message)
            VALUES (?, ?, ?, ?, ?)
        ");
        
        $subject = 'Anfrage: ' . $listing['title'];
        
        $stmt->execute([
            $_SESSION['user_id'],
            $listing['user_id'],
            $partId,
            $subject,
            $message
        ]);
        
        $messageSent = true;
    }
}

// Format price
$formattedPrice = number_format($listing['price'], 2, ',', '.');

// Set page title
$pageTitle = $listing['title'];
?>

<div class="listing-container">
    <div class="listing-detail">
        <div class="listing-gallery">
    <?php if (count($images) > 0): ?>
        <div class="main-image-container">
            <img src="<?php echo SITE_URL . '/' . $images[0]['file_path']; ?>" alt="<?php echo htmlspecialchars($listing['title']); ?>" class="main-image" id="main-image" data-index="0">
            <button class="gallery-nav prev-image" id="prev-image"><i class="fas fa-chevron-left"></i></button>
            <button class="gallery-nav next-image" id="next-image"><i class="fas fa-chevron-right"></i></button>
            <button class="fullscreen-toggle" id="fullscreen-toggle"><i class="fas fa-expand"></i></button>
        </div>
        
        <?php if (count($images) > 1): ?>
            <div class="thumbnail-container">
                <?php foreach ($images as $index => $image): ?>
                    <?php 
                    // Prüfen, ob ein Thumbnail existiert
                    $thumbPath = str_replace(basename($image['file_path']), 'thumb_' . basename($image['file_path']), $image['file_path']);
                    $thumbUrl = file_exists($thumbPath) ? SITE_URL . '/' . $thumbPath : SITE_URL . '/' . $image['file_path'];
                    ?>
                    <img src="<?php echo $thumbUrl; ?>" 
                         alt="Thumbnail <?php echo $index + 1; ?>" 
                         class="thumbnail <?php echo ($index === 0) ? 'active' : ''; ?>"
                         data-index="<?php echo $index; ?>"
                         data-full="<?php echo SITE_URL . '/' . $image['file_path']; ?>">
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <img src="<?php echo SITE_URL; ?>/assets/images/placeholder.jpg" alt="<?php echo htmlspecialchars($listing['title']); ?>" class="main-image">
    <?php endif; ?>
</div>

<!-- Lightbox für Vollbildansicht -->
<div id="image-lightbox" class="lightbox">
    <div class="lightbox-content">
        <button class="lightbox-close" id="lightbox-close"><i class="fas fa-times"></i></button>
        <button class="lightbox-nav prev-image" id="lightbox-prev"><i class="fas fa-chevron-left"></i></button>
        <button class="lightbox-nav next-image" id="lightbox-next"><i class="fas fa-chevron-right"></i></button>
        <div class="lightbox-image-container">
            <img src="/placeholder.svg" alt="Vollbild" id="lightbox-image" class="lightbox-image">
        </div>
        <div class="lightbox-counter" id="lightbox-counter">1 / 1</div>
    </div>
</div>
        
        <div class="listing-info">
            <div class="listing-header">
                <div>
                    <h1 class="listing-title"><?php echo htmlspecialchars($listing['title']); ?></h1>
                    <div class="listing-meta">
                        <span><i class="fas fa-eye"></i> <?php echo $listing['views']; ?> Aufrufe</span>
                        <span><i class="fas fa-calendar"></i> Eingestellt am <?php echo date('d.m.Y', strtotime($listing['date_posted'])); ?></span>
                        <span><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($listing['location']); ?></span>
                    </div>
                </div>
                <div class="listing-price-container">
                    <div class="listing-price">€ <?php echo $formattedPrice; ?></div>
                    <?php if ($listing['is_negotiable']): ?>
                        <div class="negotiable-badge">Verhandelbar</div>
                    <?php endif; ?>
                    
                    <?php if (isLoggedIn() && $_SESSION['user_id'] != $listing['user_id']): ?>
                        <a href="<?php echo SITE_URL; ?>/toggle-favorite.php?part_id=<?php echo $partId; ?>&redirect=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>" 
                           class="favorite-button <?php echo $isFavorite ? 'active' : ''; ?>">
                            <i class="<?php echo $isFavorite ? 'fas' : 'far'; ?> fa-heart"></i>
                            <?php echo $isFavorite ? 'Aus Favoriten entfernen' : 'Zu Favoriten hinzufügen'; ?>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="listing-section">
                <h2>Beschreibung</h2>
                <div class="listing-description">
                    <?php echo nl2br(htmlspecialchars($listing['description'])); ?>
                </div>
            </div>
            
            <div class="listing-section">
                <h2>Details</h2>
                <div class="listing-details">
                    <div class="detail-item">
                        <span class="detail-label">Kategorie</span>
                        <span><?php echo htmlspecialchars($listing['category_name']); ?></span>
                    </div>
                    
                    <?php if ($listing['make_name']): ?>
                        <div class="detail-item">
                            <span class="detail-label">Marke</span>
                            <span><?php echo htmlspecialchars($listing['make_name']); ?></span>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($listing['model_name']): ?>
                        <div class="detail-item">
                            <span class="detail-label">Modell</span>
                            <span><?php echo htmlspecialchars($listing['model_name']); ?></span>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($listing['year_from'] || $listing['year_to']): ?>
                        <div class="detail-item">
                            <span class="detail-label">Baujahr</span>
                            <span>
                                <?php 
                                if ($listing['year_from'] && $listing['year_to']) {
                                    echo $listing['year_from'] . ' - ' . $listing['year_to'];
                                } elseif ($listing['year_from']) {
                                    echo 'ab ' . $listing['year_from'];
                                } elseif ($listing['year_to']) {
                                    echo 'bis ' . $listing['year_to'];
                                }
                                ?>
                            </span>
                        </div>
                    <?php endif; ?>
                    
                    <div class="detail-item">
                        <span class="detail-label">Zustand</span>
                        <span>
                            <?php 
                            $conditions = [
                                'New' => 'Neu',
                                'Like New' => 'Wie neu',
                                'Good' => 'Gut',
                                'Fair' => 'Gebraucht',
                                'Poor' => 'Stark gebraucht'
                            ];
                            echo $conditions[$listing['condition_rating']] ?? $listing['condition_rating'];
                            ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="seller-info">
        <div class="seller-header">
            <img src="<?php echo SITE_URL; ?>/assets/images/avatar-placeholder.jpg" alt="Verkäufer" class="seller-avatar">
            <div>
                <h3 class="seller-name"><?php echo htmlspecialchars($listing['username']); ?></h3>
                <p>Mitglied seit <?php echo date('m/Y', strtotime($listing['registration_date'])); ?></p>
                <?php if ($listing['city']): ?>
                    <p><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($listing['city']); ?></p>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if (isLoggedIn() && $_SESSION['user_id'] != $listing['user_id']): ?>
            <div class="contact-seller">
                <?php if ($messageSent): ?>
                    <div class="alert alert-success">Ihre Nachricht wurde erfolgreich gesendet!</div>
                <?php else: ?>
                    <h3>Verkäufer kontaktieren</h3>
                    
                    <?php if ($messageError): ?>
                        <div class="alert alert-danger"><?php echo $messageError; ?></div>
                    <?php endif; ?>
                    
                    <form method="POST" action="" id="message-form">
                        <div class="form-group">
                            <label for="message">Ihre Nachricht</label>
                            <textarea id="message" name="message" class="form-control" rows="4" required></textarea>
                        </div>
                        <button type="submit" name="send_message" class="btn btn-primary">Nachricht senden</button>
                    </form>
                <?php endif; ?>
            </div>
        <?php elseif (!isLoggedIn()): ?>
            <div class="contact-seller">
                <p>Um den Verkäufer zu kontaktieren, müssen Sie sich <a href="<?php echo SITE_URL; ?>/login.php">anmelden</a>.</p>
            </div>
        <?php endif; ?>
    </div>
    
    <?php if (count($similarListings) > 0): ?>
        <div class="similar-listings">
            <h2>Ähnliche Angebote</h2>
            <div class="listings-grid">
                <?php foreach ($similarListings as $similar): ?>
                    <a href="<?php echo SITE_URL; ?>/listing.php?id=<?php echo $similar['part_id']; ?>" class="listing-card">
                        <div class="listing-card-img">
                            <img src="<?php echo $similar['file_path'] ? SITE_URL . '/' . $similar['file_path'] : SITE_URL . '/assets/images/placeholder.jpg'; ?>" 
                                 alt="<?php echo htmlspecialchars($similar['title']); ?>">
                            <div class="listing-card-price">€ <?php echo number_format($similar['price'], 2, ',', '.'); ?></div>
                        </div>
                        <div class="listing-card-content">
                            <h3 class="listing-card-title"><?php echo htmlspecialchars($similar['title']); ?></h3>
                            <div class="listing-card-footer">
                                <div class="listing-card-location">
                                    <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($similar['location']); ?>
                                </div>
                                <div class="listing-card-date">
                                    <?php echo date('d.m.Y', strtotime($similar['date_posted'])); ?>
                                </div>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

<style>
/* Bildergalerie Styles */
.main-image-container {
    position: relative;
    width: 100%;
    height: 400px;
    overflow: hidden;
    background-color: #f9f9f9;
    border-radius: 8px;
    margin-bottom: 10px;
}

.main-image {
    width: 100%;
    height: 100%;
    object-fit: contain;
    cursor: pointer;
    transition: transform 0.3s ease;
}

.main-image:hover {
    transform: scale(1.02);
}

.gallery-nav {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    background-color: rgba(255, 255, 255, 0.7);
    border: none;
    border-radius: 50%;
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s ease;
    z-index: 10;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
}

.gallery-nav:hover {
    background-color: rgba(255, 255, 255, 0.9);
    box-shadow: 0 3px 8px rgba(0, 0, 0, 0.3);
}

.prev-image {
    left: 10px;
}

.next-image {
    right: 10px;
}

.fullscreen-toggle {
    position: absolute;
    bottom: 10px;
    right: 10px;
    background-color: rgba(255, 255, 255, 0.7);
    border: none;
    border-radius: 50%;
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s ease;
    z-index: 10;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
}

.fullscreen-toggle:hover {
    background-color: rgba(255, 255, 255, 0.9);
    box-shadow: 0 3px 8px rgba(0, 0, 0, 0.3);
}

.thumbnail-container {
    display: flex;
    gap: 10px;
    overflow-x: auto;
    padding: 5px 0;
    scroll-behavior: smooth;
    -webkit-overflow-scrolling: touch;
}

.thumbnail {
    width: 80px;
    height: 80px;
    object-fit: cover;
    cursor: pointer;
    border: 2px solid transparent;
    border-radius: 4px;
    transition: all 0.3s ease;
    flex-shrink: 0;
}

.thumbnail:hover {
    border-color: #3498db;
    transform: translateY(-2px);
}

.thumbnail.active {
    border-color: #3498db;
    box-shadow: 0 2px 8px rgba(52, 152, 219, 0.5);
}

/* Lightbox Styles */
.lightbox {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.9);
    z-index: 1000;
    justify-content: center;
    align-items: center;
}

.lightbox-content {
    position: relative;
    width: 90%;
    height: 90%;
    max-width: 1200px;
    display: flex;
    justify-content: center;
    align-items: center;
}

.lightbox-image-container {
    width: 100%;
    height: 100%;
    display: flex;
    justify-content: center;
    align-items: center;
}

.lightbox-image {
    max-width: 100%;
    max-height: 100%;
    object-fit: contain;
}

.lightbox-close {
    position: absolute;
    top: -40px;
    right: 0;
    background-color: transparent;
    border: none;
    color: white;
    font-size: 24px;
    cursor: pointer;
    z-index: 1010;
}

.lightbox-nav {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    background-color: rgba(255, 255, 255, 0.2);
    border: none;
    border-radius: 50%;
    width: 50px;
    height: 50px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s ease;
    z-index: 1010;
    color: white;
    font-size: 20px;
}

.lightbox-nav:hover {
    background-color: rgba(255, 255, 255, 0.3);
}

.lightbox-nav.prev-image {
    left: 20px;
}

.lightbox-nav.next-image {
    right: 20px;
}

.lightbox-counter {
    position: absolute;
    bottom: -30px;
    left: 0;
    right: 0;
    text-align: center;
    color: white;
    font-size: 14px;
}

/* Responsive Anpassungen */
@media (max-width: 768px) {
    .main-image-container {
        height: 300px;
    }
    
    .gallery-nav, .fullscreen-toggle {
        width: 36px;
        height: 36px;
    }
    
    .thumbnail {
        width: 60px;
        height: 60px;
    }
    
    .lightbox-nav {
        width: 40px;
        height: 40px;
    }
}

@media (max-width: 480px) {
    .main-image-container {
        height: 250px;
    }
    
    .gallery-nav, .fullscreen-toggle {
        width: 32px;
        height: 32px;
    }
    
    .thumbnail {
        width: 50px;
        height: 50px;
    }
    
    .lightbox-nav {
        width: 36px;
        height: 36px;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Variablen für die Bildergalerie
    const mainImage = document.getElementById('main-image');
    const thumbnails = document.querySelectorAll('.thumbnail');
    const prevButton = document.getElementById('prev-image');
    const nextButton = document.getElementById('next-image');
    const fullscreenToggle = document.getElementById('fullscreen-toggle');
    
    // Variablen für die Lightbox
    const lightbox = document.getElementById('image-lightbox');
    const lightboxImage = document.getElementById('lightbox-image');
    const lightboxClose = document.getElementById('lightbox-close');
    const lightboxPrev = document.getElementById('lightbox-prev');
    const lightboxNext = document.getElementById('lightbox-next');
    const lightboxCounter = document.getElementById('lightbox-counter');
    
    // Aktuelle Bildposition
    let currentIndex = 0;
    const totalImages = thumbnails.length;
    
    // Funktion zum Aktualisieren des Hauptbildes
    function updateMainImage(index) {
        if (index < 0) index = totalImages - 1;
        if (index >= totalImages) index = 0;
        
        currentIndex = index;
        
        // Hauptbild aktualisieren
        const targetThumbnail = thumbnails[index];
        mainImage.src = targetThumbnail.getAttribute('data-full');
        mainImage.setAttribute('data-index', index);
        
        // Aktiven Thumbnail markieren
        thumbnails.forEach(thumb => thumb.classList.remove('active'));
        targetThumbnail.classList.add('active');
        
        // Scroll zum aktiven Thumbnail
        targetThumbnail.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
    }
    
    // Funktion zum Öffnen der Lightbox
    function openLightbox(index) {
        if (index < 0) index = totalImages - 1;
        if (index >= totalImages) index = 0;
        
        currentIndex = index;
        
        // Lightbox-Bild setzen
        lightboxImage.src = thumbnails[index].getAttribute('data-full');
        lightboxCounter.textContent = `${index + 1} / ${totalImages}`;
        
        // Lightbox anzeigen
        lightbox.style.display = 'flex';
        document.body.style.overflow = 'hidden'; // Scrolling verhindern
        
        // Fokus auf Lightbox setzen für Tastaturnavigation
        lightbox.focus();
    }
    
    // Funktion zum Schließen der Lightbox
    function closeLightbox() {
        lightbox.style.display = 'none';
        document.body.style.overflow = ''; // Scrolling wieder erlauben
    }
    
    // Event-Listener für Thumbnails
    thumbnails.forEach((thumbnail, index) => {
        thumbnail.addEventListener('click', function() {
            updateMainImage(index);
        });
    });
    
    // Event-Listener für Hauptbild (öffnet Lightbox)
    if (mainImage) {
        mainImage.addEventListener('click', function() {
            openLightbox(parseInt(this.getAttribute('data-index')));
        });
    }
    
    // Event-Listener für Vollbild-Toggle
    if (fullscreenToggle) {
        fullscreenToggle.addEventListener('click', function() {
            openLightbox(currentIndex);
        });
    }
    
    // Event-Listener für Navigationsbuttons
    if (prevButton) {
        prevButton.addEventListener('click', function(e) {
            e.stopPropagation(); // Verhindert, dass das Hauptbild-Click-Event ausgelöst wird
            updateMainImage(currentIndex - 1);
        });
    }
    
    if (nextButton) {
        nextButton.addEventListener('click', function(e) {
            e.stopPropagation(); // Verhindert, dass das Hauptbild-Click-Event ausgelöst wird
            updateMainImage(currentIndex + 1);
        });
    }
    
    // Event-Listener für Lightbox-Schließen
    if (lightboxClose) {
        lightboxClose.addEventListener('click', closeLightbox);
    }
    
    // Event-Listener für Lightbox-Navigation
    if (lightboxPrev) {
        lightboxPrev.addEventListener('click', function() {
            openLightbox(currentIndex - 1);
        });
    }
    
    if (lightboxNext) {
        lightboxNext.addEventListener('click', function() {
            openLightbox(currentIndex + 1);
        });
    }
    
    // Event-Listener für Klick außerhalb des Lightbox-Inhalts
    lightbox.addEventListener('click', function(e) {
        if (e.target === lightbox) {
            closeLightbox();
        }
    });
    
    // Event-Listener für Tastaturnavigation
    document.addEventListener('keydown', function(e) {
        // Nur reagieren, wenn die Lightbox geöffnet ist oder das Hauptbild im Fokus ist
        const isLightboxOpen = lightbox.style.display === 'flex';
        
        if (isLightboxOpen) {
            // ESC-Taste schließt die Lightbox
            if (e.key === 'Escape') {
                closeLightbox();
            }
            
            // Pfeiltasten für Navigation in der Lightbox
            if (e.key === 'ArrowLeft') {
                openLightbox(currentIndex - 1);
            } else if (e.key === 'ArrowRight') {
                openLightbox(currentIndex + 1);
            }
        } else {
            // Pfeiltasten für Navigation im Hauptbild
            if (e.key === 'ArrowLeft') {
                updateMainImage(currentIndex - 1);
            } else if (e.key === 'ArrowRight') {
                updateMainImage(currentIndex + 1);
            }
        }
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>

