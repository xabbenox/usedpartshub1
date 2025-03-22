<?php
$pageTitle = 'Anmelden';
require_once 'includes/header.php';

// Redirect if already logged in
if (isLoggedIn()) {
    redirect(SITE_URL . '/account/dashboard.php');
}

$errors = [];
$email = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitizeInput($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // Validate email
    if (empty($email)) {
        $errors['email'] = 'E-Mail-Adresse ist erforderlich';
    }
    
    // Validate password
    if (empty($password)) {
        $errors['password'] = 'Passwort ist erforderlich';
    }
    
    // If no validation errors, attempt to log in
    if (empty($errors)) {
        // Check if user exists
        $stmt = $pdo->prepare("SELECT user_id, username, password FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            // Update last login time
            $updateStmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?");
            $updateStmt->execute([$user['user_id']]);
            
            // Set session variables
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            
            // Redirect to dashboard
            $_SESSION['success'] = 'Anmeldung erfolgreich! Willkommen zurück.';
            
            // Redirect to intended page if set, otherwise to dashboard
            $redirect = isset($_SESSION['redirect_after_login']) ? $_SESSION['redirect_after_login'] : SITE_URL . '/account/dashboard.php';
            unset($_SESSION['redirect_after_login']);
            redirect($redirect);
        } else {
            $errors['login'] = 'Ungültige E-Mail-Adresse oder Passwort';
        }
    }
}
?>

<div class="auth-container">
    <div class="auth-form">
        <h2>Anmelden</h2>
        <p>Melden Sie sich an, um Ihre Angebote zu verwalten und mit Verkäufern zu kommunizieren.</p>
        
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
                <label for="email">E-Mail-Adresse</label>
                <input type="email" id="email" name="email" class="form-control" value="<?php echo htmlspecialchars($email); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="password">Passwort</label>
                <input type="password" id="password" name="password" class="form-control" required>
            </div>
            
            <div class="form-group">
                <button type="submit" class="btn btn-primary btn-block">Anmelden</button>
            </div>
            
            <p class="text-center">Noch kein Konto? <a href="register.php">Registrieren</a></p>
            <p class="text-center"><a href="forgot-password.php">Passwort vergessen?</a></p>
        </form>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>