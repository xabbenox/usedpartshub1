<?php
// Aktiviere Fehleranzeige für Debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Datenbankverbindung herstellen
require_once 'includes/config.php';

echo "<h1>Administrator-Konto einrichten</h1>";

// Prüfen, ob bereits ein Admin existiert
$checkAdminStmt = $pdo->query("SELECT COUNT(*) FROM users WHERE is_admin = 1");
$adminExists = $checkAdminStmt->fetchColumn() > 0;

if ($adminExists) {
    echo "<div class='info-message'>
        <h2>Administrator existiert bereits</h2>
        <p>Es wurde bereits mindestens ein Administrator-Konto eingerichtet.</p>
        <p>Wenn Sie Administrator-Rechte benötigen, verwenden Sie bitte die <a href='make-admin.php'>Administrator-Anfrage</a> oder kontaktieren Sie einen bestehenden Administrator.</p>
    </div>";
    echo "<p><a href='index.php' class='btn'>Zurück zur Startseite</a></p>";
    exit;
}

// Verarbeiten des Formulars für die Ersteinrichtung
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['setup_admin'])) {
    $username = sanitizeInput($_POST['username']);
    $email = sanitizeInput($_POST['email']);
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirm_password'];
    $setupCode = sanitizeInput($_POST['setup_code']);
    
    $errors = [];
    
    // Validierung
    if (empty($username)) {
        $errors[] = "Benutzername ist erforderlich.";
    }
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Eine gültige E-Mail-Adresse ist erforderlich.";
    }
    
    if (empty($password) || strlen($password) < 8) {
        $errors[] = "Passwort muss mindestens 8 Zeichen lang sein.";
    }
    
    if ($password !== $confirmPassword) {
        $errors[] = "Passwörter stimmen nicht überein.";
    }
    
    // Hier können Sie einen geheimen Setup-Code definieren
    $secretSetupCode = "InitialSetup2023"; // Ändern Sie diesen Code nach Bedarf
    
    if ($setupCode !== $secretSetupCode) {
        $errors[] = "Ungültiger Setup-Code.";
    }
    
    if (empty($errors)) {
        try {
            // Prüfen, ob Benutzer bereits existiert
            $checkUserStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ? OR email = ?");
            $checkUserStmt->execute([$username, $email]);
            
            if ($checkUserStmt->fetchColumn() > 0) {
                echo "<div class='error-message'>Benutzername oder E-Mail existiert bereits.</div>";
            } else {
                // Benutzer erstellen und als Admin markieren
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                
                $insertStmt = $pdo->prepare("
                    INSERT INTO users (username, email, password, is_admin, registration_date)
                    VALUES (?, ?, ?, 1, NOW())
                ");
                
                $insertStmt->execute([$username, $email, $hashedPassword]);
                
                echo "<div class='success-message'>
                    <h2>Administrator-Konto erfolgreich eingerichtet!</h2>
                    <p>Sie können sich jetzt mit Ihren Anmeldedaten einloggen und auf den Admin-Bereich zugreifen.</p>
                </div>";
                echo "<p><a href='login.php' class='btn'>Zum Login</a></p>";
                exit;
            }
        } catch (PDOException $e) {
            echo "<div class='error-message'>Fehler beim Erstellen des Administrator-Kontos: " . $e->getMessage() . "</div>";
        }
    } else {
        echo "<div class='error-message'>
            <h3>Bitte korrigieren Sie die folgenden Fehler:</h3>
            <ul>";
        foreach ($errors as $error) {
            echo "<li>$error</li>";
        }
        echo "</ul></div>";
    }
}
?>

<div class="admin-setup-form">
    <p>Willkommen bei der Ersteinrichtung des Administrator-Kontos für UsedPartsHub. Bitte füllen Sie das folgende Formular aus, um ein Administrator-Konto zu erstellen.</p>
    
    <form method="POST" action="">
        <div class="form-group">
            <label for="username">Benutzername:</label>
            <input type="text" id="username" name="username" class="form-control" required>
        </div>
        
        <div class="form-group">
            <label for="email">E-Mail-Adresse:</label>
            <input type="email" id="email" name="email" class="form-control" required>
        </div>
        
        <div class="form-group">
            <label for="password">Passwort:</label>
            <input type="password" id="password" name="password" class="form-control" required>
            <small>Mindestens 8 Zeichen</small>
        </div>
        
        <div class="form-group">
            <label for="confirm_password">Passwort bestätigen:</label>
            <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
        </div>
        
        <div class="form-group">
            <label for="setup_code">Setup-Code:</label>
            <input type="password" id="setup_code" name="setup_code" class="form-control" required>
            <small>Der Setup-Code wurde Ihnen vom System-Entwickler mitgeteilt.</small>
        </div>
        
        <button type="submit" name="setup_admin" class="btn">Administrator-Konto erstellen</button>
    </form>
</div>

<style>
body {
    font-family: Arial, sans-serif;
    max-width: 800px;
    margin: 0 auto;
    padding: 20px;
    line-height: 1.6;
}

h1 {
    color: #2c3e50;
    margin-bottom: 30px;
    text-align: center;
}

h2 {
    color: #3498db;
    margin-top: 0;
}

p {
    margin: 10px 0;
}

.success-message {
    background-color: #d4edda;
    color: #155724;
    padding: 20px;
    border-radius: 5px;
    margin: 30px 0;
    border-left: 5px solid #28a745;
}

.error-message {
    background-color: #f8d7da;
    color: #721c24;
    padding: 20px;
    border-radius: 5px;
    margin: 30px 0;
    border-left: 5px solid #dc3545;
}

.info-message {
    background-color: #d1ecf1;
    color: #0c5460;
    padding: 20px;
    border-radius: 5px;
    margin: 30px 0;
    border-left: 5px solid #17a2b8;
}

.admin-setup-form {
    background-color: #f8f9fa;
    padding: 25px;
    border-radius: 5px;
    margin: 30px 0;
    border: 1px solid #dee2e6;
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
    padding: 10px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 1rem;
}

.form-control:focus {
    border-color: #3498db;
    box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.2);
    outline: none;
}

small {
    display: block;
    margin-top: 5px;
    color: #6c757d;
    font-size: 0.85rem;
}

.btn {
    display: inline-block;
    background-color: #3498db;
    color: white;
    padding: 10px 20px;
    text-decoration: none;
    border-radius: 5px;
    transition: background-color 0.3s;
    border: none;
    cursor: pointer;
    font-size: 1rem;
}

.btn:hover {
    background-color: #2980b9;
}

ul {
    margin-top: 10px;
    margin-bottom: 10px;
    padding-left: 20px;
}

li {
    margin-bottom: 5px;
}
</style>

