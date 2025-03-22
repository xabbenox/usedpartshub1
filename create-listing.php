<?php
// Aktiviere Fehleranzeige für Debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$pageTitle = 'Inserat erstellen';
require_once 'includes/header.php';

// Require login
requireLogin();

$errors = [];
$formData = [
'title' => '',
'description' => '',
'category_id' => '',
'make_id' => '',
'model_id' => '',
'year_from' => '',
'year_to' => '',
'condition_rating' => '',
'price' => '',
'is_negotiable' => false,
'location' => '',
'part_number' => '',
'compatible_vehicles' => ''
];

// Get user's city as default location
$stmt = $pdo->prepare("SELECT city FROM users WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();
if ($user && !empty($user['city'])) {
$formData['location'] = $user['city'];
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
// Get form data
$formData = [
    'title' => isset($_POST['title']) ? sanitizeInput($_POST['title']) : '',
    'description' => isset($_POST['description']) ? sanitizeInput($_POST['description']) : '',
    'category_id' => isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0,
    'make_id' => !empty($_POST['make_id']) ? (int)$_POST['make_id'] : null,
    'model_id' => (!empty($_POST['model_id']) && (int)$_POST['model_id'] > 0) ? (int)$_POST['model_id'] : null,
    'year_from' => !empty($_POST['year_from']) ? (int)$_POST['year_from'] : null,
    'year_to' => !empty($_POST['year_to']) ? (int)$_POST['year_to'] : null,
    'condition_rating' => isset($_POST['condition_rating']) ? sanitizeInput($_POST['condition_rating']) : '',
    'price' => isset($_POST['price']) ? (float)str_replace(',', '.', $_POST['price']) : 0,
    'is_negotiable' => isset($_POST['is_negotiable']),
    'location' => isset($_POST['location']) ? sanitizeInput($_POST['location']) : '',
    'part_number' => isset($_POST['part_number']) ? sanitizeInput($_POST['part_number']) : '',
    'compatible_vehicles' => isset($_POST['compatible_vehicles']) ? sanitizeInput($_POST['compatible_vehicles']) : ''
];

// Validate title
if (empty($formData['title'])) {
    $errors['title'] = 'Titel ist erforderlich';
} elseif (strlen($formData['title']) < 5 || strlen($formData['title']) > 100) {
    $errors['title'] = 'Titel muss zwischen 5 und 100 Zeichen lang sein';
}

// Validate description
if (empty($formData['description'])) {
    $errors['description'] = 'Beschreibung ist erforderlich';
} elseif (strlen($formData['description']) < 20) {
    $errors['description'] = 'Beschreibung muss mindestens 20 Zeichen lang sein';
}

// Validate category
if (empty($formData['category_id'])) {
    $errors['category_id'] = 'Kategorie ist erforderlich';
}

// Validate condition
if (empty($formData['condition_rating'])) {
    $errors['condition_rating'] = 'Zustand ist erforderlich';
}

// Validate price
if (empty($formData['price']) || $formData['price'] <= 0) {
    $errors['price'] = 'Gültiger Preis ist erforderlich';
}

// Validate location
if (empty($formData['location'])) {
    $errors['location'] = 'Standort ist erforderlich';
}

// Validate images
$allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
$maxFileSize = 10 * 1024 * 1024; // 10MB erhöht von 5MB
$minWidth = 800; // Mindestbreite für Bilder
$minHeight = 600; // Mindesthöhe für Bilder
$uploadedImages = [];

if (!isset($_FILES['images']) || empty($_FILES['images']['name'][0])) {
    $errors['images'] = 'Mindestens ein Bild ist erforderlich';
} else {
    for ($i = 0; $i < count($_FILES['images']['name']); $i++) {
        if ($_FILES['images']['error'][$i] === 0) {
            $fileType = $_FILES['images']['type'][$i];
            $fileSize = $_FILES['images']['size'][$i];
            $tmpName = $_FILES['images']['tmp_name'][$i];
            
            // Check file type
            if (!in_array($fileType, $allowedTypes)) {
                $errors['images'] = 'Nur JPG, PNG und GIF Dateien sind erlaubt';
                break;
            }
            
            // Check file size
            if ($fileSize > $maxFileSize) {
                $errors['images'] = 'Maximale Dateigröße ist 10MB';
                break;
            }
            
            // Check image dimensions
            $imageInfo = getimagesize($tmpName);
            if ($imageInfo === false) {
                $errors['images'] = 'Ungültiges Bildformat';
                break;
            }
            
            $width = $imageInfo[0];
            $height = $imageInfo[1];
            
            if ($width < $minWidth || $height < $minHeight) {
                $errors['images'] = "Bilder müssen mindestens ${minWidth}x${minHeight} Pixel groß sein";
                break;
            }
            
            $uploadedImages[] = [
                'name' => $_FILES['images']['name'][$i],
                'tmp_name' => $tmpName,
                'is_primary' => ($i === 0), // First image is primary
                'width' => $width,
                'height' => $height,
                'type' => $fileType
            ];
        }
    }
}

// If no errors, create the listing
if (empty($errors)) {
    try {
        // Start transaction
        $pdo->beginTransaction();
        
        // Insert part listing
        $stmt = $pdo->prepare("
    INSERT INTO parts (
        user_id, title, description, category_id, make_id, model_id, 
        year_from, year_to, condition_rating, price, is_negotiable, location
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");
        
        $stmt->execute([
    $_SESSION['user_id'],
    $formData['title'],
    $formData['description'],
    $formData['category_id'],
    $formData['make_id'] > 0 ? $formData['make_id'] : null,
    $formData['model_id'] > 0 ? $formData['model_id'] : null,
    $formData['year_from'] > 0 ? $formData['year_from'] : null,
    $formData['year_to'] > 0 ? $formData['year_to'] : null,
    $formData['condition_rating'],
    $formData['price'],
    $formData['is_negotiable'] ? 1 : 0,
    $formData['location']
]);
        
        $partId = $pdo->lastInsertId();
        
        // Upload images
        $uploadDir = 'uploads/parts/' . $partId . '/';

        // Create directory if it doesn't exist
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        foreach ($uploadedImages as $image) {
            $fileName = uniqid() . '_' . basename($image['name']);
            $filePath = $uploadDir . $fileName;
            
            // Speichere das Originalbild ohne Komprimierung für beste Qualität
            if (move_uploaded_file($image['tmp_name'], $filePath)) {
                // Erstelle ein Thumbnail für die Listenansicht
                $thumbPath = $uploadDir . 'thumb_' . $fileName;
                createThumbnail($filePath, $thumbPath, 300, 300);
                
                // Insert image record
                $stmt = $pdo->prepare("
                    INSERT INTO images (part_id, file_path, is_primary)
                    VALUES (?, ?, ?)
                ");
                
                $stmt->execute([
                    $partId,
                    $filePath,
                    $image['is_primary'] ? 1 : 0
                ]);
            }
        }
        
        // Commit transaction
        $pdo->commit();
        
        // Redirect to listing page
        $_SESSION['success'] = 'Inserat erfolgreich erstellt!';
        redirect(SITE_URL . '/listing.php?id=' . $partId);
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $pdo->rollBack();
        $errors['general'] = 'Ein Fehler ist aufgetreten: ' . $e->getMessage();
    }
}
}
?>

<div class="create-listing-container">
<div class="create-listing-header">
    <h1><i class="fas fa-plus-circle"></i> Inserat erstellen</h1>
    <p>Verkaufen Sie Ihr Autoteil schnell und einfach</p>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <h4><i class="fas fa-exclamation-triangle"></i> Bitte korrigieren Sie die folgenden Fehler:</h4>
        <ul>
            <?php foreach ($errors as $error): ?>
                <li><?php echo $error; ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="create-listing-progress">
    <div class="progress-step active" data-step="1">
        <div class="step-number">1</div>
        <div class="step-label">Allgemein</div>
    </div>
    <div class="progress-step" data-step="2">
        <div class="step-number">2</div>
        <div class="step-label">Fahrzeug</div>
    </div>
    <div class="progress-step" data-step="3">
        <div class="step-number">3</div>
        <div class="step-label">Details</div>
    </div>
    <div class="progress-step" data-step="4">
        <div class="step-number">4</div>
        <div class="step-label">Bilder</div>
    </div>
</div>

<form method="POST" action="" enctype="multipart/form-data" id="create-listing-form" class="card">
    <!-- Step 1: Allgemeine Informationen -->
    <div class="form-step" id="step-1">
        <div class="form-section">
            <h2><i class="fas fa-info-circle"></i> Allgemeine Informationen</h2>
            
            <div class="form-group">
                <label for="title">Titel *</label>
                <input type="text" id="title" name="title" class="form-control" value="<?php echo htmlspecialchars($formData['title']); ?>" required>
                <small>Geben Sie einen aussagekräftigen Titel ein (z.B. "BMW E46 Stoßstange vorne schwarz")</small>
            </div>
            
            <div class="form-group">
                <label for="description">Beschreibung *</label>
                <textarea id="description" name="description" class="form-control" rows="5" required><?php echo htmlspecialchars($formData['description']); ?></textarea>
                <small>Beschreiben Sie das Teil detailliert (Zustand, Besonderheiten, Kompatibilität, etc.)</small>
                <div class="description-tips">
                    <h4>Tipps für eine gute Beschreibung:</h4>
                    <ul>
                        <li>Beschreiben Sie den Zustand des Teils genau</li>
                        <li>Erwähnen Sie eventuelle Mängel oder Gebrauchsspuren</li>
                        <li>Geben Sie an, ob das Teil original oder ein Nachbauteil ist</li>
                        <li>Nennen Sie die genaue Passform (Baujahr, Modellvariante)</li>
                        <li>Erwähnen Sie die Herstellernummer, falls bekannt</li>
                    </ul>
                </div>
            </div>
            
            <div class="form-group">
                <label for="category_id">Kategorie *</label>
                <select id="category_id" name="category_id" class="form-control" required>
                    <option value="">-- Kategorie wählen --</option>
                    <?php
                    $stmt = $pdo->query("SELECT category_id, name FROM categories ORDER BY name");
                    while ($category = $stmt->fetch()) {
                        $selected = ($formData['category_id'] == $category['category_id']) ? 'selected' : '';
                        echo '<option value="' . $category['category_id'] . '" ' . $selected . '>' . htmlspecialchars($category['name']) . '</option>';
                    }
                    ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="part_number">Teilenummer (optional)</label>
                <input type="text" id="part_number" name="part_number" class="form-control" value="<?php echo htmlspecialchars($formData['part_number']); ?>">
                <small>Geben Sie die OEM- oder Herstellernummer an, falls bekannt</small>
            </div>
            
            <div class="form-navigation">
                <button type="button" class="btn btn-primary next-step">Weiter <i class="fas fa-arrow-right"></i></button>
            </div>
        </div>
    </div>
    
    <!-- Step 2: Fahrzeuginformationen -->
    <div class="form-step" id="step-2" style="display: none;">
        <div class="form-section">
            <h2><i class="fas fa-car"></i> Fahrzeuginformationen</h2>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="make_id">Marke</label>
                    <select id="make_id" name="make_id" class="form-control">
                        <option value="">-- Marke wählen --</option>
                        <?php
                        $stmt = $pdo->query("SELECT make_id, name FROM car_makes ORDER BY name");
                        while ($make = $stmt->fetch()) {
                            $selected = ($formData['make_id'] == $make['make_id']) ? 'selected' : '';
                            echo '<option value="' . $make['make_id'] . '" ' . $selected . '>' . htmlspecialchars($make['name']) . '</option>';
                        }
                        ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="model_id">Modell</label>
                    <select id="model_id" name="model_id" class="form-control">
                        <option value="">-- Modell wählen --</option>
                        <?php
                        if (!empty($formData['make_id'])) {
                            $stmt = $pdo->prepare("SELECT model_id, name FROM car_models WHERE make_id = ? ORDER BY name");
                            $stmt->execute([$formData['make_id']]);
                            while ($model = $stmt->fetch()) {
                                $selected = ($formData['model_id'] == $model['model_id']) ? 'selected' : '';
                                echo '<option value="' . $model['model_id'] . '" ' . $selected . '>' . htmlspecialchars($model['name']) . '</option>';
                            }
                        }
                        ?>
                    </select>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="year_from">Baujahr von</label>
                    <select id="year_from" name="year_from" class="form-control">
                        <option value="">-- Jahr wählen --</option>
                        <?php
                        $currentYear = (int)date('Y');
                        for ($year = $currentYear; $year >= 1950; $year--) {
                            $selected = ($formData['year_from'] == $year) ? 'selected' : '';
                            echo '<option value="' . $year . '" ' . $selected . '>' . $year . '</option>';
                        }
                        ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="year_to">Baujahr bis</label>
                    <select id="year_to" name="year_to" class="form-control">
                        <option value="">-- Jahr wählen --</option>
                        <?php
                        for ($year = $currentYear; $year >= 1950; $year--) {
                            $selected = ($formData['year_to'] == $year) ? 'selected' : '';
                            echo '<option value="' . $year . '" ' . $selected . '>' . $year . '</option>';
                        }
                        ?>
                    </select>
                </div>
            </div>
            
            <div class="form-group">
                <label for="compatible_vehicles">Kompatible Fahrzeuge (optional)</label>
                <textarea id="compatible_vehicles" name="compatible_vehicles" class="form-control" rows="3"><?php echo htmlspecialchars($formData['compatible_vehicles']); ?></textarea>
                <small>Geben Sie weitere kompatible Fahrzeuge an, falls das Teil für mehrere Modelle passt</small>
            </div>
            
            <div class="form-navigation">
                <button type="button" class="btn btn-outline prev-step"><i class="fas fa-arrow-left"></i> Zurück</button>
                <button type="button" class="btn btn-primary next-step">Weiter <i class="fas fa-arrow-right"></i></button>
            </div>
        </div>
    </div>
    
    <!-- Step 3: Zustand und Preis -->
    <div class="form-step" id="step-3" style="display: none;">
        <div class="form-section">
            <h2><i class="fas fa-tag"></i> Zustand und Preis</h2>
            
            <div class="form-group condition-group">
                <label>Zustand *</label>
                <div class="condition-options">
                    <div class="condition-option">
                        <input type="radio" id="condition-new" name="condition_rating" value="New" <?php echo ($formData['condition_rating'] === 'New') ? 'checked' : ''; ?>>
                        <label for="condition-new">
                            <div class="condition-icon"><i class="fas fa-star"></i></div>
                            <div class="condition-name">Neu</div>
                            <div class="condition-desc">Originalverpackt, unbenutzt</div>
                        </label>
                    </div>
                    <div class="condition-option">
                        <input type="radio" id="condition-like-new" name="condition_rating" value="Like New" <?php echo ($formData['condition_rating'] === 'Like New') ? 'checked' : ''; ?>>
                        <label for="condition-like-new">
                            <div class="condition-icon"><i class="fas fa-star-half-alt"></i></div>
                            <div class="condition-name">Wie neu</div>
                            <div class="condition-desc">Kaum benutzt, wie neu</div>
                        </label>
                    </div>
                    <div class="condition-option">
                        <input type="radio" id="condition-good" name="condition_rating" value="Good" <?php echo ($formData['condition_rating'] === 'Good') ? 'checked' : ''; ?>>
                        <label for="condition-good">
                            <div class="condition-icon"><i class="fas fa-check-circle"></i></div>
                            <div class="condition-name">Gut</div>
                            <div class="condition-desc">Gebraucht, guter Zustand</div>
                        </label>
                    </div>
                    <div class="condition-option">
                        <input type="radio" id="condition-fair" name="condition_rating" value="Fair" <?php echo ($formData['condition_rating'] === 'Fair') ? 'checked' : ''; ?>>
                        <label for="condition-fair">
                            <div class="condition-icon"><i class="fas fa-check"></i></div>
                            <div class="condition-name">Gebraucht</div>
                            <div class="condition-desc">Gebrauchsspuren vorhanden</div>
                        </label>
                    </div>
                    <div class="condition-option">
                        <input type="radio" id="condition-poor" name="condition_rating" value="Poor" <?php echo ($formData['condition_rating'] === 'Poor') ? 'checked' : ''; ?>>
                        <label for="condition-poor">
                            <div class="condition-icon"><i class="fas fa-tools"></i></div>
                            <div class="condition-name">Stark gebraucht</div>
                            <div class="condition-desc">Deutliche Abnutzung</div>
                        </label>
                    </div>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group price-group">
                    <label for="price">Preis (€) *</label>
                    <div class="price-input-wrapper">
                        <span class="price-currency">€</span>
                        <input type="text" id="price" name="price" class="form-control" value="<?php echo htmlspecialchars($formData['price']); ?>" required>
                    </div>
                </div>
                
                <div class="form-group checkbox-group">
                    <div class="custom-checkbox">
                        <input type="checkbox" id="is_negotiable" name="is_negotiable" <?php echo $formData['is_negotiable'] ? 'checked' : ''; ?>>
                        <label for="is_negotiable">
                            <span class="checkbox-icon"><i class="fas fa-check"></i></span>
                            <span>Preis verhandelbar</span>
                        </label>
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <label for="location">Standort *</label>
                <div class="location-input-wrapper">
                    <span class="location-icon"><i class="fas fa-map-marker-alt"></i></span>
                    <input type="text" id="location" name="location" class="form-control" value="<?php echo htmlspecialchars($formData['location']); ?>" required>
                </div>
            </div>
            
            <div class="form-navigation">
                <button type="button" class="btn btn-outline prev-step"><i class="fas fa-arrow-left"></i> Zurück</button>
                <button type="button" class="btn btn-primary next-step">Weiter <i class="fas fa-arrow-right"></i></button>
            </div>
        </div>
    </div>
    
    <!-- Step 4: Bilder -->
    <div class="form-step" id="step-4" style="display: none;">
        <div class="form-section">
            <h2><i class="fas fa-images"></i> Bilder</h2>
            <p class="image-upload-info">Laden Sie mindestens ein Bild hoch. Das erste Bild wird als Hauptbild verwendet.</p>
            
            <div class="form-group">
                <div class="image-upload-container">
                    <div class="image-upload-dropzone" id="image-dropzone">
                        <i class="fas fa-cloud-upload-alt"></i>
                        <p>Bilder hier ablegen oder klicken zum Auswählen</p>
                        <p class="small">Erlaubte Formate: JPG, PNG, GIF. Maximale Größe: 5MB pro Bild.</p>
                        <input type="file" id="images" name="images[]" multiple accept="image/*" required style="display: none;">
                    </div>
                    <div id="image-preview" class="image-preview-container"></div>
                </div>
            </div>
            
            <div class="image-tips">
                <h4>Tipps für gute Bilder:</h4>
                <ul>
                    <li>Machen Sie Bilder bei gutem Licht</li>
                    <li>Zeigen Sie das Teil aus verschiedenen Perspektiven</li>
                    <li>Fotografieren Sie eventuelle Mängel oder Besonderheiten</li>
                    <li>Zeigen Sie die Teilenummer, falls vorhanden</li>
                    <li>Vermeiden Sie unscharfe oder zu dunkle Bilder</li>
                </ul>
            </div>
            
            <div class="form-navigation">
                <button type="button" class="btn btn-outline prev-step"><i class="fas fa-arrow-left"></i> Zurück</button>
                <button type="submit" class="btn btn-success"><i class="fas fa-check"></i> Inserat erstellen</button>
            </div>
        </div>
    </div>
</form>
</div>

<style>
/* Verbesserte Styles für die Inserat erstellen Seite */
.create-listing-container {
max-width: 900px;
margin: 0 auto;
padding: 20px 0;
}

.create-listing-header {
text-align: center;
margin-bottom: 30px;
}

.create-listing-header h1 {
color: #2c3e50;
font-size: 2.2rem;
margin-bottom: 10px;
}

.create-listing-header p {
color: #7f8c8d;
font-size: 1.1rem;
}

.create-listing-progress {
display: flex;
justify-content: space-between;
margin-bottom: 30px;
position: relative;
}

.create-listing-progress::before {
content: '';
position: absolute;
top: 25px;
left: 0;
right: 0;
height: 2px;
background-color: #e0e0e0;
z-index: 1;
}

.progress-step {
display: flex;
flex-direction: column;
align-items: center;
position: relative;
z-index: 2;
}

.step-number {
width: 50px;
height: 50px;
border-radius: 50%;
background-color: #e0e0e0;
color: #7f8c8d;
display: flex;
align-items: center;
justify-content: center;
font-weight: bold;
font-size: 1.2rem;
margin-bottom: 10px;
transition: all 0.3s ease;
}

.step-label {
color: #7f8c8d;
font-weight: 500;
transition: all 0.3s ease;
}

.progress-step.active .step-number {
background-color: #3498db;
color: white;
}

.progress-step.active .step-label {
color: #3498db;
font-weight: 600;
}

.progress-step.completed .step-number {
background-color: #2ecc71;
color: white;
}

.card {
background-color: white;
border-radius: 10px;
box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
overflow: hidden;
margin-bottom: 30px;
}

.form-section {
padding: 30px;
}

.form-section h2 {
color: #2c3e50;
font-size: 1.5rem;
margin-bottom: 25px;
padding-bottom: 15px;
border-bottom: 1px solid #eee;
}

.form-section h2 i {
margin-right: 10px;
color: #3498db;
}

.form-group {
margin-bottom: 25px;
}

.form-row {
display: flex;
gap: 20px;
margin-bottom: 20px;
}

.form-row .form-group {
flex: 1;
margin-bottom: 0;
}

label {
display: block;
margin-bottom: 8px;
font-weight: 600;
color: #2c3e50;
}

.form-control {
width: 100%;
padding: 12px 15px;
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

small {
display: block;
margin-top: 5px;
color: #7f8c8d;
font-size: 0.85rem;
}

.description-tips, .image-tips {
background-color: #f8f9fa;
border-left: 4px solid #3498db;
padding: 15px;
margin-top: 15px;
border-radius: 4px;
}

.description-tips h4, .image-tips h4 {
color: #2c3e50;
margin-bottom: 10px;
font-size: 1rem;
}

.description-tips ul, .image-tips ul {
padding-left: 20px;
margin: 0;
}

.description-tips li, .image-tips li {
margin-bottom: 5px;
color: #555;
}

.condition-group {
margin-bottom: 30px;
}

.condition-options {
display: flex;
flex-wrap: wrap;
gap: 15px;
margin-top: 10px;
}

.condition-option {
flex: 1;
min-width: 150px;
position: relative;
}

.condition-option input[type="radio"] {
position: absolute;
opacity: 0;
cursor: pointer;
height: 0;
width: 0;
}

.condition-option label {
display: block;
padding: 15px;
border: 1px solid #ddd;
border-radius: 6px;
text-align: center;
cursor: pointer;
transition: all 0.3s ease;
}

.condition-option input[type="radio"]:checked + label {
border-color: #3498db;
background-color: rgba(52, 152, 219, 0.1);
box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.3);
}

.condition-icon {
font-size: 1.5rem;
margin152,219,0.1);
box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.3);
}

.condition-icon {
font-size: 1.5rem;
margin-bottom: 10px;
color: #3498db;
}

.condition-name {
font-weight: 600;
margin-bottom: 5px;
color: #2c3e50;
}

.condition-desc {
font-size: 0.85rem;
color: #7f8c8d;
}

.price-input-wrapper {
position: relative;
}

.price-currency {
position: absolute;
left: 15px;
top: 50%;
transform: translateY(-50%);
color: #7f8c8d;
font-weight: 600;
}

.price-input-wrapper input {
padding-left: 30px;
}

.location-input-wrapper {
position: relative;
}

.location-icon {
position: absolute;
left: 15px;
top: 50%;
transform: translateY(-50%);
color: #7f8c8d;
}

.location-input-wrapper input {
padding-left: 40px;
}

.custom-checkbox {
display: flex;
align-items: center;
margin-top: 30px;
}

.custom-checkbox input[type="checkbox"] {
display: none;
}

.custom-checkbox label {
display: flex;
align-items: center;
cursor: pointer;
margin-bottom: 0;
}

.checkbox-icon {
width: 24px;
height: 24px;
border: 2px solid #ddd;
border-radius: 4px;
margin-right: 10px;
display: flex;
align-items: center;
justify-content: center;
transition: all 0.3s ease;
}

.checkbox-icon i {
color: white;
font-size: 0.8rem;
opacity: 0;
transition: opacity 0.3s ease;
}

.custom-checkbox input[type="checkbox"]:checked + label .checkbox-icon {
background-color: #3498db;
border-color: #3498db;
}

.custom-checkbox input[type="checkbox"]:checked + label .checkbox-icon i {
opacity: 1;
}

.image-upload-container {
margin-bottom: 20px;
}

.image-upload-dropzone {
border: 2px dashed #ddd;
border-radius: 6px;
padding: 40px 20px;
text-align: center;
cursor: pointer;
transition: all 0.3s ease;
margin-bottom: 20px;
}

.image-upload-dropzone:hover {
border-color: #3498db;
background-color: rgba(52, 152, 219, 0.05);
}

.image-upload-dropzone i {
font-size: 3rem;
color: #3498db;
margin-bottom: 15px;
}

.image-upload-dropzone p {
margin-bottom: 5px;
color: #2c3e50;
}

.image-upload-dropzone p.small {
font-size: 0.85rem;
color: #7f8c8d;
}

.image-preview-container {
display: flex;
flex-wrap: wrap;
gap: 15px;
margin-top: 20px;
}

.image-preview-item {
position: relative;
width: 150px;
height: 150px;
border-radius: 6px;
overflow: hidden;
box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
}

.image-preview-item img {
width: 100%;
height: 100%;
object-fit: cover;
}

.image-preview-item .remove-image {
position: absolute;
top: 5px;
right: 5px;
width: 24px;
height: 24px;
background-color: rgba(255, 255, 255, 0.8);
border-radius: 50%;
display: flex;
align-items: center;
justify-content: center;
cursor: pointer;
color: #e74c3c;
font-size: 0.8rem;
transition: all 0.3s ease;
}

.image-preview-item .remove-image:hover {
background-color: #e74c3c;
color: white;
}

.image-preview-item .primary-badge {
position: absolute;
bottom: 0;
left: 0;
right: 0;
background-color: rgba(52, 152, 219, 0.8);
color: white;
text-align: center;
padding: 5px;
font-size: 0.8rem;
}

.form-navigation {
display: flex;
justify-content: space-between;
margin-top: 30px;
padding-top: 20px;
border-top: 1px solid #eee;
}

.btn {
padding: 12px 25px;
border-radius: 6px;
font-weight: 600;
transition: all 0.3s ease;
cursor: pointer;
display: inline-flex;
align-items: center;
justify-content: center;
gap: 8px;
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

.btn-success {
background-color: #2ecc71;
color: white;
border: none;
}

.btn-success:hover {
background-color: #27ae60;
}

/* Responsive Anpassungen */
@media (max-width: 768px) {
.form-row {
    flex-direction: column;
    gap: 15px;
}

.condition-options {
    flex-direction: column;
}

.form-section {
    padding: 20px;
}

.step-label {
    display: none;
}

.create-listing-progress::before {
    top: 15px;
}

.step-number {
    width: 30px;
    height: 30px;
    font-size: 0.9rem;
}
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
// Multi-step form navigation
const steps = document.querySelectorAll('.form-step');
const progressSteps = document.querySelectorAll('.progress-step');
let currentStep = 0;

// Next button click handler
document.querySelectorAll('.next-step').forEach(button => {
    button.addEventListener('click', function() {
        // Validate current step
        if (validateStep(currentStep)) {
            steps[currentStep].style.display = 'none';
            progressSteps[currentStep].classList.add('completed');
            currentStep++;
            steps[currentStep].style.display = 'block';
            progressSteps[currentStep].classList.add('active');
            window.scrollTo(0, 0);
        }
    });
});

// Previous button click handler
document.querySelectorAll('.prev-step').forEach(button => {
    button.addEventListener('click', function() {
        steps[currentStep].style.display = 'none';
        progressSteps[currentStep].classList.remove('active');
        currentStep--;
        steps[currentStep].style.display = 'block';
        progressSteps[currentStep].classList.remove('completed');
        window.scrollTo(0, 0);
    });
});

// Step validation
function validateStep(step) {
    let isValid = true;
    
    // Step 1 validation
    if (step === 0) {
        const title = document.getElementById('title');
        const description = document.getElementById('description');
        const category = document.getElementById('category_id');
        
        if (!title.value.trim()) {
            markInvalid(title, 'Bitte geben Sie einen Titel ein');
            isValid = false;
        } else if (title.value.length < 5) {
            markInvalid(title, 'Der Titel muss mindestens 5 Zeichen lang sein');
            isValid = false;
        } else {
            markValid(title);
        }
        
        if (!description.value.trim()) {
            markInvalid(description, 'Bitte geben Sie eine Beschreibung ein');
            isValid = false;
        } else if (description.value.length < 20) {
            markInvalid(description, 'Die Beschreibung muss mindestens 20 Zeichen lang sein');
            isValid = false;
        } else {
            markValid(description);
        }
        
        if (!category.value) {
            markInvalid(category, 'Bitte wählen Sie eine Kategorie');
            isValid = false;
        } else {
            markValid(category);
        }
    }
    
    // Step 3 validation
    if (step === 2) {
        const condition = document.querySelector('input[name="condition_rating"]:checked');
        const price = document.getElementById('price');
        const location = document.getElementById('location');
        
        if (!condition) {
            alert('Bitte wählen Sie einen Zustand');
            isValid = false;
        }
        
        if (!price.value.trim() || isNaN(parseFloat(price.value)) || parseFloat(price.value) <= 0) {
            markInvalid(price, 'Bitte geben Sie einen gültigen Preis ein');
            isValid = false;
        } else {
            markValid(price);
        }
        
        if (!location.value.trim()) {
            markInvalid(location, 'Bitte geben Sie einen Standort ein');
            isValid = false;
        } else {
            markValid(location);
        }
    }
    
    return isValid;
}

function markInvalid(element, message) {
    element.classList.add('is-invalid');
    
    // Remove existing error message if any
    const existingError = element.parentNode.querySelector('.error-message');
    if (existingError) {
        existingError.remove();
    }
    
    // Add error message
    const errorDiv = document.createElement('div');
    errorDiv.className = 'error-message';
    errorDiv.textContent = message;
    element.parentNode.appendChild(errorDiv);
}

function markValid(element) {
    element.classList.remove('is-invalid');
    
    // Remove error message if any
    const existingError = element.parentNode.querySelector('.error-message');
    if (existingError) {
        existingError.remove();
    }
}

// Dynamic model dropdown based on selected make
const makeSelect = document.getElementById('make_id');
const modelSelect = document.getElementById('model_id');

if (makeSelect && modelSelect) {
    makeSelect.addEventListener('change', function() {
        const makeId = this.value;
        
        // Clear current options
        modelSelect.innerHTML = '<option value="">-- Modell wählen --</option>';
        
        if (makeId) {
            // Zeige Ladeindikator
            modelSelect.disabled = true;
            const loadingOption = document.createElement('option');
            loadingOption.textContent = 'Lade Modelle...';
            modelSelect.appendChild(loadingOption);
            
            // Fetch models for selected make with timestamp to prevent caching
            const timestamp = new Date().getTime();
            fetch(`get-models.php?make_id=${makeId}&_=${timestamp}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! Status: ${response.status}`);
                    }
                    return response.text().then(text => {
                        try {
                            // Versuche den Text als JSON zu parsen
                            return JSON.parse(text);
                        } catch (e) {
                            // Wenn das Parsen fehlschlägt, zeige den Rohtext im Fehler
                            console.error('Erhaltene Antwort ist kein gültiges JSON:', text.substring(0, 100) + '...');
                            throw new Error('Ungültiges JSON erhalten');
                        }
                    });
                })
                .then(data => {
                    // Entferne Ladeindikator
                    modelSelect.innerHTML = '<option value="">-- Modell wählen --</option>';
                    
                    if (Array.isArray(data) && data.length > 0) {
                        data.forEach(model => {
                            const option = document.createElement('option');
                            option.value = model.model_id;
                            option.textContent = model.name;
                            modelSelect.appendChild(option);
                        });
                    } else if (data.error) {
                        console.error('Fehler beim Laden der Modelle:', data.error);
                        const errorOption = document.createElement('option');
                        errorOption.textContent = 'Fehler beim Laden der Modelle';
                        modelSelect.appendChild(errorOption);
                    } else {
                        const noModelsOption = document.createElement('option');
                        noModelsOption.textContent = 'Keine Modelle verfügbar';
                        modelSelect.appendChild(noModelsOption);
                    }
                })
                .catch(error => {
                    console.error('Error fetching models:', error);
                    modelSelect.innerHTML = '<option value="">-- Fehler beim Laden --</option>';
                    const errorOption = document.createElement('option');
                    errorOption.textContent = 'Bitte versuchen Sie es später erneut';
                    modelSelect.appendChild(errorOption);
                })
                .finally(() => {
                    modelSelect.disabled = false;
                });
        }
    });
}

// Make condition options clickable
document.querySelectorAll('.condition-option label').forEach(label => {
    label.addEventListener('click', function() {
        // Find the associated radio input and check it
        const radioInput = this.parentNode.querySelector('input[type="radio"]');
        if (radioInput) {
            radioInput.checked = true;
        }
    });
});

// Image upload preview
const imageInput = document.getElementById('images');
const imagePreviewContainer = document.getElementById('image-preview');
const imageDropzone = document.getElementById('image-dropzone');

if (imageInput && imagePreviewContainer && imageDropzone) {
    // Click on dropzone to trigger file input
    imageDropzone.addEventListener('click', function() {
        imageInput.click();
    });
    
    // Drag and drop functionality
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        imageDropzone.addEventListener(eventName, preventDefaults, false);
    });
    
    function preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }
    
    ['dragenter', 'dragover'].forEach(eventName => {
        imageDropzone.addEventListener(eventName, highlight, false);
    });
    
    ['dragleave', 'drop'].forEach(eventName => {
        imageDropzone.addEventListener(eventName, unhighlight, false);
    });
    
    function highlight() {
        imageDropzone.classList.add('highlight');
    }
    
    function unhighlight() {
        imageDropzone.classList.remove('highlight');
    }
    
    imageDropzone.addEventListener('drop', handleDrop, false);
    
    function handleDrop(e) {
        const dt = e.dataTransfer;
        const files = dt.files;
        
        imageInput.files = files;
        handleFiles(files);
    }
    
    imageInput.addEventListener('change', function() {
        handleFiles(this.files);
    });
    
    function handleFiles(files) {
        imagePreviewContainer.innerHTML = '';
        
        Array.from(files).forEach((file, index) => {
            if (!file.type.match('image.*')) {
                return;
            }
            
            const reader = new FileReader();
            
            reader.onload = function(e) {
                const previewDiv = document.createElement('div');
                previewDiv.className = 'image-preview-item';
                
                const img = document.createElement('img');
                img.src = e.target.result;
                
                const removeButton = document.createElement('div');
                removeButton.className = 'remove-image';
                removeButton.innerHTML = '<i class="fas fa-times"></i>';
                removeButton.addEventListener('click', function(e) {
                    e.stopPropagation();
                    previewDiv.remove();
                    // Note: This doesn't actually remove the file from the input
                    // In a real implementation, you'd need to create a new FileList
                });
                
                if (index === 0) {
                    const primaryBadge = document.createElement('div');
                    primaryBadge.className = 'primary-badge';
                    primaryBadge.textContent = 'Hauptbild';
                    previewDiv.appendChild(primaryBadge);
                }
                
                previewDiv.appendChild(img);
                previewDiv.appendChild(removeButton);
                imagePreviewContainer.appendChild(previewDiv);
            };
            
            reader.readAsDataURL(file);
        });
    }
}

// Price input formatting
const priceInput = document.getElementById('price');
if (priceInput) {
    priceInput.addEventListener('input', function(e) {
        let value = e.target.value;
        
        // Remove all characters except digits and decimal point
        value = value.replace(/[^\d.,]/g, '');
        
        // Replace comma with dot for decimal
        value = value.replace(',', '.');
        
        // Ensure only one decimal point
        const parts = value.split('.');
        if (parts.length > 2) {
            value = parts[0] + '.' + parts.slice(1).join('');
        }
        
        // Format to 2 decimal places if there's a decimal point
        if (value.includes('.')) {
            const decimalPart = parts[1].substring(0, 2);
            value = parts[0] + '.' + decimalPart;
        }
        
        e.target.value = value;
    });
}
});
</script>

<?php 
/**
 * Erstellt ein Thumbnail mit der angegebenen Breite und Höhe
 * 
 * @param string $sourcePath Pfad zum Originalbild
 * @param string $targetPath Pfad, wo das Thumbnail gespeichert werden soll
 * @param int $width Zielbreite
 * @param int $height Zielhöhe
 * @return bool Erfolg oder Misserfolg
 */
function createThumbnail($sourcePath, $targetPath, $width, $height) {
    // Bildtyp ermitteln
    $imageInfo = getimagesize($sourcePath);
    if (!$imageInfo) return false;
    
    $sourceWidth = $imageInfo[0];
    $sourceHeight = $imageInfo[1];
    $imageType = $imageInfo[2];
    
    // Quellbild laden
    switch ($imageType) {
        case IMAGETYPE_JPEG:
            $sourceImage = imagecreatefromjpeg($sourcePath);
            break;
        case IMAGETYPE_PNG:
            $sourceImage = imagecreatefrompng($sourcePath);
            break;
        case IMAGETYPE_GIF:
            $sourceImage = imagecreatefromgif($sourcePath);
            break;
        default:
            return false;
    }
    
    if (!$sourceImage) return false;
    
    // Seitenverhältnis beibehalten
    $ratio = min($width / $sourceWidth, $height / $sourceHeight);
    $targetWidth = round($sourceWidth * $ratio);
    $targetHeight = round($sourceHeight * $ratio);
    
    // Neues Bild erstellen
    $targetImage = imagecreatetruecolor($targetWidth, $targetHeight);
    
    // Transparenz beibehalten für PNG
    if ($imageType == IMAGETYPE_PNG) {
        imagealphablending($targetImage, false);
        imagesavealpha($targetImage, true);
        $transparent = imagecolorallocatealpha($targetImage, 255, 255, 255, 127);
        imagefilledrectangle($targetImage, 0, 0, $targetWidth, $targetHeight, $transparent);
    }
    
    // Bild skalieren
    imagecopyresampled(
        $targetImage, $sourceImage,
        0, 0, 0, 0,
        $targetWidth, $targetHeight, $sourceWidth, $sourceHeight
    );
    
    // Bild speichern
    $success = false;
    switch ($imageType) {
        case IMAGETYPE_JPEG:
            $success = imagejpeg($targetImage, $targetPath, 85); // 85% Qualität
            break;
        case IMAGETYPE_PNG:
            $success = imagepng($targetImage, $targetPath, 8); // Komprimierungsstufe 8 (0-9)
            break;
        case IMAGETYPE_GIF:
            $success = imagegif($targetImage, $targetPath);
            break;
    }
    
    // Speicher freigeben
    imagedestroy($sourceImage);
    imagedestroy($targetImage);
    
    return $success;
}
?>

<?php require_once 'includes/footer.php'; ?>

