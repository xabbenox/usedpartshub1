<?php
$pageTitle = 'Registrieren';
require_once 'includes/header.php';

// Redirect if already logged in
if (isLoggedIn()) {
    redirect(SITE_URL . '/account/dashboard.php');
}

$errors = [];
$formData = [
    'username' => '',
    'email' => '',
    'first_name' => '',
    'last_name' => '',
    'phone' => '',
    'address' => '',
    'city' => '',
    'postal_code' => '',
];

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $formData = [
        'username' => sanitizeInput($_POST['username'] ?? ''),
        'email' => sanitizeInput($_POST['email'] ?? ''),
        'password' => $_POST['password'] ?? '',
        'confirm_password' => $_POST['confirm_password'] ?? '',
        'first_name' => sanitizeInput($_POST['first_name'] ?? ''),
        'last_name' => sanitizeInput($_POST['last_name'] ?? ''),
        'phone' => sanitizeInput($_POST['phone'] ?? ''),
        'address' => sanitizeInput($_POST['address'] ?? ''),
        'city' => sanitizeInput($_POST['city'] ?? ''),
        'postal_code' => sanitizeInput($_POST['postal_code'] ?? ''),
    ];
    
    // Validate username
    if (empty($formData['username'])) {
        $errors['username'] = 'Benutzername ist erforderlich';
    } elseif (strlen($formData['username']) < 3 || strlen($formData['username']) > 50) {
        $errors['username'] = 'Benutzername muss zwischen 3 und 50 Zeichen lang sein';
    } else {
        // Check if username already exists
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
        $stmt->execute([$formData['username']]);
        if ($stmt->fetchColumn() > 0) {
            $errors['username'] = 'Dieser Benutzername ist bereits vergeben';
        }
    }
    
    // Validate email
    if (empty($formData['email'])) {
        $errors['email'] = 'E-Mail-Adresse ist erforderlich';
    } elseif (!filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Ungültige E-Mail-Adresse';
    } else {
        // Check if email already exists
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
        $stmt->execute([$formData['email']]);
        if ($stmt->fetchColumn() > 0) {
            $errors['email'] = 'Diese E-Mail-Adresse ist bereits registriert';
        }
    }
    
    // Validate password
    if (empty($formData['password'])) {
        $errors['password'] = 'Passwort ist erforderlich';
    } elseif (strlen($formData['password']) < 8) {
        $errors['password'] = 'Passwort muss mindestens 8 Zeichen lang sein';
    }
    
    // Validate confirm password
    if ($formData['password'] !== $formData['confirm_password']) {
        $errors['confirm_password'] = 'Passwörter stimmen nicht überein';
    }
    
    // If no errors, register the user
    if (empty($errors)) {
        // Hash the password
        $hashedPassword = password_hash($formData['password'], PASSWORD_DEFAULT);
        
        // Insert user into database
        $stmt = $pdo->prepare("
            INSERT INTO users (username, email, password, first_name, last_name, phone, address, city, postal_code)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $formData['username'],
            $formData['email'],
            $hashedPassword,
            $formData['first_name'],
            $formData['last_name'],
            $formData['phone'],
            $formData['address'],
            $formData['city'],
            $formData['postal_code']
        ]);
        
        // Get the new user ID
        $userId = $pdo->lastInsertId();
        
        // Log the user in
        $_SESSION['user_id'] = $userId;
        $_SESSION['username'] = $formData['username'];
        
        // Redirect to dashboard
        $_SESSION['success'] = 'Registrierung erfolgreich! Willkommen bei UsedPartsHub.';
        redirect(SITE_URL . '/account/dashboard.php');
    }
}
?>

<div class="auth-container">
    <div class="auth-form">
        <h2>Registrieren</h2>
        <p>Erstellen Sie ein Konto, um Autoteile zu kaufen und zu verkaufen.</p>
        
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo $error; ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="username">Benutzername *</label>
                <input type="text" id="username" name="username" class="form-control" value="<?php echo htmlspecialchars($formData['username']); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="email">E-Mail-Adresse *</label>
                <input type="email" id="email" name="email" class="form-control" value="<?php echo htmlspecialchars($formData['email']); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="password">Passwort *</label>
                <input type="password" id="password" name="password" class="form-control" required>
                <small>Mindestens 8 Zeichen</small>
            </div>
            
            <div class="form-group">
                <label for="confirm_password">Passwort bestätigen *</label>
                <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
            </div>
            
            <h3>Persönliche Informationen</h3>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="first_name">Vorname</label>
                    <input type="text" id="first_name" name="first_name" class="form-control" value="<?php echo htmlspecialchars($formData['first_name']); ?>">
                </div>
                
                <div class="form-group">
                    <label for="last_name">Nachname</label>
                    <input type="text" id="last_name" name="last_name" class="form-control" value="<?php echo htmlspecialchars($formData['last_name']); ?>">
                </div>
            </div>
            
            <div class="form-group">
                <label for="phone">Telefonnummer</label>
                <input type="tel" id="phone" name="phone" class="form-control" value="<?php echo htmlspecialchars($formData['phone']); ?>">
            </div>
            
            <div class="form-group">
                <label for="address">Adresse</label>
                <input type="text" id="address" name="address" class="form-control" value="<?php echo htmlspecialchars($formData['address']); ?>">
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="city">Stadt</label>
                    <input type="text" id="city" name="city" class="form-control" value="<?php echo htmlspecialchars($formData['city']); ?>">
                </div>
                
                <div class="form-group">
                    <label for="postal_code">Postleitzahl</label>
                    <input type="text" id="postal_code" name="postal_code" class="form-control" value="<?php echo htmlspecialchars($formData['postal_code']); ?>">
                </div>
            </div>
            
            <div class="form-group">
                <button type="submit" class="btn btn-primary btn-block">Registrieren</button>
            </div>
            
            <p class="text-center">Bereits registriert? <a href="login.php">Anmelden</a></p>
        </form>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>