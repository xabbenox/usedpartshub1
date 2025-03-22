<?php
$pageTitle = 'Profil bearbeiten';
require_once '../includes/header.php';

// Require login
requireLogin();

// Get user data
$userId = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    $_SESSION['error'] = 'Benutzer nicht gefunden';
    redirect(SITE_URL . '/account/dashboard.php');
}

// Überprüfen, ob die Spalte profile_image existiert
$checkColumn = $pdo->query("SHOW COLUMNS FROM users LIKE 'profile_image'");
$profileImageColumnExists = $checkColumn->rowCount() > 0;

if (!$profileImageColumnExists) {
    $_SESSION['error'] = 'Die Profilbild-Funktion ist noch nicht verfügbar. Bitte führen Sie zuerst das Datenbank-Update durch.';
    redirect(SITE_URL . '/update-database.php');
}

$errors = [];
$success = false;

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $firstName = sanitizeInput($_POST['first_name'] ?? '');
    $lastName = sanitizeInput($_POST['last_name'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '');
    $phone = sanitizeInput($_POST['phone'] ?? '');
    $address = sanitizeInput($_POST['address'] ?? '');
    $city = sanitizeInput($_POST['city'] ?? '');
    $postalCode = sanitizeInput($_POST['postal_code'] ?? '');
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    // Validate email
    if (empty($email)) {
        $errors['email'] = 'E-Mail-Adresse ist erforderlich';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Ungültige E-Mail-Adresse';
    } else {
        // Check if email already exists for another user
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ? AND user_id != ?");
        $stmt->execute([$email, $userId]);
        if ($stmt->fetchColumn() > 0) {
            $errors['email'] = 'Diese E-Mail-Adresse wird bereits verwendet';
        }
    }
    
    // Validate password change if requested
    if (!empty($currentPassword) || !empty($newPassword) || !empty($confirmPassword)) {
        // Verify current password
        if (!password_verify($currentPassword, $user['password'])) {
            $errors['current_password'] = 'Aktuelles Passwort ist nicht korrekt';
        }
        
        // Validate new password
        if (empty($newPassword)) {
            $errors['new_password'] = 'Neues Passwort ist erforderlich';
        } elseif (strlen($newPassword) < 8) {
            $errors['new_password'] = 'Passwort muss mindestens 8 Zeichen lang sein';
        }
        
        // Validate password confirmation
        if ($newPassword !== $confirmPassword) {
            $errors['confirm_password'] = 'Passwörter stimmen nicht überein';
        }
    }
    
    // Process profile image upload
    $profileImage = $user['profile_image'] ?? null; // Keep existing image by default
    
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === 0) {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        $maxFileSize = 5 * 1024 * 1024; // 5MB
        
        $fileType = $_FILES['profile_image']['type'];
        $fileSize = $_FILES['profile_image']['size'];
        
        // Validate file type
        if (!in_array($fileType, $allowedTypes)) {
            $errors['profile_image'] = 'Nur JPG, PNG und GIF Dateien sind erlaubt';
        }
        
        // Validate file size
        if ($fileSize > $maxFileSize) {
            $errors['profile_image'] = 'Maximale Dateigröße ist 5MB';
        }
        
        if (empty($errors['profile_image'])) {
            // Create upload directory if it doesn't exist
            $uploadDir = '../uploads/profile/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            // Generate unique filename
            $fileName = uniqid() . '_' . basename($_FILES['profile_image']['name']);
            $filePath = $uploadDir . $fileName;
            
            // Move uploaded file
            if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $filePath)) {
                // Delete old profile image if exists
                if (!empty($user['profile_image']) && file_exists('../' . $user['profile_image'])) {
                    unlink('../' . $user['profile_image']);
                }
                
                $profileImage = 'uploads/profile/' . $fileName;
            } else {
                $errors['profile_image'] = 'Fehler beim Hochladen des Bildes';
            }
        }
    }
    
    // If no errors, update user profile
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            // Update user data
            $sql = "UPDATE users SET 
                    first_name = ?, 
                    last_name = ?, 
                    email = ?, 
                    phone = ?, 
                    address = ?, 
                    city = ?, 
                    postal_code = ?";
            
            $params = [
                $firstName,
                $lastName,
                $email,
                $phone,
                $address,
                $city,
                $postalCode
            ];
            
            // Add profile image if column exists
            if ($profileImageColumnExists) {
                $sql .= ", profile_image = ?";
                $params[] = $profileImage;
            }
            
            // Add password update if requested
            if (!empty($newPassword)) {
                $sql .= ", password = ?";
                $params[] = password_hash($newPassword, PASSWORD_DEFAULT);
            }
            
            $sql .= " WHERE user_id = ?";
            $params[] = $userId;
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            $pdo->commit();
            
            // Update session email if changed
            if ($email !== $user['email']) {
                $_SESSION['email'] = $email;
            }
            
            $success = true;
            $_SESSION['success'] = 'Profil erfolgreich aktualisiert';
            
            // Refresh user data
            $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors['general'] = 'Ein Fehler ist aufgetreten: ' . $e->getMessage();
        }
    }
}
?>

<div class="dashboard-container">
    <div class="dashboard-sidebar">
        <div class="user-info">
            <?php if ($profileImageColumnExists && !empty($user['profile_image'])): ?>
                <img src="<?php echo SITE_URL . '/' . $user['profile_image']; ?>" alt="<?php echo htmlspecialchars($user['username']); ?>" class="user-avatar">
            <?php else: ?>
                <img src="<?php echo SITE_URL; ?>/assets/images/avatar-placeholder.jpg" alt="<?php echo htmlspecialchars($user['username']); ?>" class="user-avatar">
            <?php endif; ?>
            <h3 class="user-name"><?php echo htmlspecialchars($user['username']); ?></h3>
            <p class="user-email"><?php echo htmlspecialchars($user['email']); ?></p>
        </div>
        
        <div class="dashboard-nav">
            <ul>
                <li><a href="<?php echo SITE_URL; ?>/account/dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li><a href="<?php echo SITE_URL; ?>/account/listings.php"><i class="fas fa-list"></i> Meine Inserate</a></li>
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
                <li><a href="<?php echo SITE_URL; ?>/account/profile.php" class="active"><i class="fas fa-user"></i> Profil bearbeiten</a></li>
                <li><a href="<?php echo SITE_URL; ?>/account/settings.php"><i class="fas fa-cog"></i> Einstellungen</a></li>
                <li><a href="<?php echo SITE_URL; ?>/logout.php"><i class="fas fa-sign-out-alt"></i> Abmelden</a></li>
            </ul>
        </div>
    </div>
    
    <div class="dashboard-content">
        <div class="dashboard-header">
            <h1 class="dashboard-title">Profil bearbeiten</h1>
        </div>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> Profil erfolgreich aktualisiert
            </div>
        <?php endif; ?>
        
        <?php if (!empty($errors['general'])): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?php echo $errors['general']; ?>
            </div>
        <?php endif; ?>
        
        <div class="profile-form-container">
            <form method="POST" action="" enctype="multipart/form-data" class="profile-form">
                <?php if ($profileImageColumnExists): ?>
                <div class="form-section">
                    <h2><i class="fas fa-user-circle"></i> Profilbild</h2>
                    
                    <div class="profile-image-upload">
                        <div class="current-profile-image">
                            <?php if (!empty($user['profile_image'])): ?>
                                <img src="<?php echo SITE_URL . '/' . $user['profile_image']; ?>" alt="Profilbild" id="profile-preview">
                            <?php else: ?>
                                <img src="<?php echo SITE_URL; ?>/assets/images/avatar-placeholder.jpg" alt="Profilbild" id="profile-preview">
                            <?php endif; ?>
                        </div>
                        
                        <div class="profile-image-controls">
                            <label for="profile_image" class="btn btn-outline">
                                <i class="fas fa-upload"></i> Profilbild auswählen
                            </label>
                            <input type="file" id="profile_image" name="profile_image" accept="image/*" style="display: none;">
                            <p class="image-help-text">Maximale Größe: 5MB. Erlaubte Formate: JPG, PNG, GIF</p>
                            
                            <?php if (!empty($errors['profile_image'])): ?>
                                <p class="error-message"><?php echo $errors['profile_image']; ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="form-section">
                    <h2><i class="fas fa-user"></i> Persönliche Informationen</h2>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="first_name">Vorname</label>
                            <input type="text" id="first_name" name="first_name" class="form-control" value="<?php echo htmlspecialchars($user['first_name']); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="last_name">Nachname</label>
                            <input type="text" id="last_name" name="last_name" class="form-control" value="<?php echo htmlspecialchars($user['last_name']); ?>">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">E-Mail-Adresse *</label>
                        <input type="email" id="email" name="email" class="form-control <?php echo isset($errors['email']) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                        <?php if (isset($errors['email'])): ?>
                            <p class="error-message"><?php echo $errors['email']; ?></p>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <label for="phone">Telefonnummer</label>
                        <input type="tel" id="phone" name="phone" class="form-control" value="<?php echo htmlspecialchars($user['phone']); ?>">
                    </div>
                </div>
                
                <div class="form-section">
                    <h2><i class="fas fa-map-marker-alt"></i> Adresse</h2>
                    
                    <div class="form-group">
                        <label for="address">Straße und Hausnummer</label>
                        <input type="text" id="address" name="address" class="form-control" value="<?php echo htmlspecialchars($user['address']); ?>">
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="city">Stadt</label>
                            <input type="text" id="city" name="city" class="form-control" value="<?php echo htmlspecialchars($user['city']); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="postal_code">Postleitzahl</label>
                            <input type="text" id="postal_code" name="postal_code" class="form-control" value="<?php echo htmlspecialchars($user['postal_code']); ?>">
                        </div>
                    </div>
                </div>
                
                <div class="form-section">
                    <h2><i class="fas fa-lock"></i> Passwort ändern</h2>
                    <p class="section-info">Lassen Sie die Felder leer, wenn Sie Ihr Passwort nicht ändern möchten.</p>
                    
                    <div class="form-group">
                        <label for="current_password">Aktuelles Passwort</label>
                        <input type="password" id="current_password" name="current_password" class="form-control <?php echo isset($errors['current_password']) ? 'is-invalid' : ''; ?>">
                        <?php if (isset($errors['current_password'])): ?>
                            <p class="error-message"><?php echo $errors['current_password']; ?></p>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="new_password">Neues Passwort</label>
                            <input type="password" id="new_password" name="new_password" class="form-control <?php echo isset($errors['new_password']) ? 'is-invalid' : ''; ?>">
                            <?php if (isset($errors['new_password'])): ?>
                                <p class="error-message"><?php echo $errors['new_password']; ?></p>
                            <?php endif; ?>
                            <p class="password-hint">Mindestens 8 Zeichen</p>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password">Passwort bestätigen</label>
                            <input type="password" id="confirm_password" name="confirm_password" class="form-control <?php echo isset($errors['confirm_password']) ? 'is-invalid' : ''; ?>">
                            <?php if (isset($errors['confirm_password'])): ?>
                                <p class="error-message"><?php echo $errors['confirm_password']; ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Änderungen speichern
                    </button>
                    <a href="<?php echo SITE_URL; ?>/account/dashboard.php" class="btn btn-outline">
                        <i class="fas fa-times"></i> Abbrechen
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
/* Profile Page Styles */
.profile-form-container {
    margin-bottom: 30px;
}

.form-section {
    background-color: #f8f9fa;
    border-radius: 10px;
    padding: 25px;
    margin-bottom: 25px;
}

.form-section h2 {
    font-size: 1.3rem;
    color: #2c3e50;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 1px solid #eee;
    display: flex;
    align-items: center;
}

.form-section h2 i {
    margin-right: 10px;
    color: #3498db;
}

.section-info {
    color: #7f8c8d;
    margin-bottom: 20px;
    font-size: 0.9rem;
}

.form-row {
    display: flex;
    gap: 20px;
    margin-bottom: 20px;
}

.form-row .form-group {
    flex: 1;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
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

.form-control.is-invalid {
    border-color: #e74c3c;
}

.error-message {
    color: #e74c3c;
    font-size: 0.85rem;
    margin-top: 5px;
}

.password-hint {
    color: #7f8c8d;
    font-size: 0.85rem;
    margin-top: 5px;
}

.profile-image-upload {
    display: flex;
    align-items: center;
    gap: 30px;
    margin-bottom: 20px;
}

.current-profile-image {
    width: 150px;
    height: 150px;
    border-radius: 50%;
    overflow: hidden;
    border: 5px solid white;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
}

.current-profile-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.profile-image-controls {
    flex: 1;
}

.image-help-text {
    color: #7f8c8d;
    font-size: 0.85rem;
    margin-top: 10px;
}

.form-actions {
    display: flex;
    gap: 15px;
    margin-top: 30px;
}

.alert {
    padding: 15px 20px;
    border-radius: 6px;
    margin-bottom: 25px;
    display: flex;
    align-items: center;
}

.alert i {
    margin-right: 10px;
    font-size: 1.2rem;
}

.alert-success {
    background-color: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.alert-danger {
    background-color: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .form-row {
        flex-direction: column;
        gap: 0;
    }
    
    .profile-image-upload {
        flex-direction: column;
        align-items: center;
        text-align: center;
    }
    
    .profile-image-controls {
        width: 100%;
        margin-top: 20px;
    }
    
    .form-actions {
        flex-direction: column;
    }
    
    .form-actions .btn {
        width: 100%;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Profile image preview
    const profileInput = document.getElementById('profile_image');
    const profilePreview = document.getElementById('profile-preview');
    
    if (profileInput && profilePreview) {
        profileInput.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    profilePreview.src = e.target.result;
                };
                
                reader.readAsDataURL(this.files[0]);
            }
        });
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>

