<?php
// Aktiviere Fehleranzeige für Debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Datenbankverbindung herstellen
require_once 'includes/config.php';

echo "<h1>Administrator-Rechte zuweisen</h1>";

// Prüfen, ob der Benutzer eingeloggt ist
if (!isLoggedIn()) {
    echo "<div class='error-message'>Sie müssen angemeldet sein, um diese Seite zu nutzen.</div>";
    echo "<p><a href='login.php' class='btn'>Zum Login</a></p>";
    exit;
}

// Aktuelle Benutzer-ID aus der Session
$userId = $_SESSION['user_id'];

// Benutzerinformationen abrufen
$stmt = $pdo->prepare("SELECT username, email, is_admin FROM users WHERE user_id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    echo "<div class='error-message'>Benutzer nicht gefunden.</div>";
    exit;
}

// Prüfen, ob der Benutzer bereits Admin ist
if ($user['is_admin'] == 1) {
    echo "<div class='info-message'>
        <h2>Sie sind bereits Administrator!</h2>
        <p>Ihr Konto hat bereits Administrator-Rechte.</p>
    </div>";
    echo "<p><a href='admin/vehicle-database.php' class='btn'>Zum Admin-Bereich</a></p>";
    exit;
}

// Verarbeiten des Formulars
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['make_admin'])) {
    $adminCode = sanitizeInput($_POST['admin_code']);
    
    // Hier können Sie einen geheimen Code definieren oder eine andere Validierungsmethode verwenden
    // In einer Produktionsumgebung sollte dies sicherer gestaltet werden
    $secretCode = "UsedPartsHub2023"; // Ändern Sie diesen Code nach Bedarf
    
    if ($adminCode === $secretCode) {
        try {
            // Benutzer zum Admin machen
            $updateStmt = $pdo->prepare("UPDATE users SET is_admin = 1 WHERE user_id = ?");
            $updateStmt->execute([$userId]);
            
            echo "<div class='success-message'>
                <h2>Glückwunsch!</h2>
                <p>Ihr Konto wurde erfolgreich auf Administrator-Status aktualisiert.</p>
            </div>";
            echo "<p><a href='admin/vehicle-database.php' class='btn'>Zum Admin-Bereich</a></p>";
            exit;
        } catch (PDOException $e) {
            echo "<div class='error-message'>Fehler beim Aktualisieren des Benutzerkontos: " . $e->getMessage() . "</div>";
        }
    } else {
        echo "<div class='error-message'>Ungültiger Administrator-Code. Bitte versuchen Sie es erneut.</div>";
    }
}
?>

<div class="admin-request-form">
    <p>Hallo <strong><?php echo htmlspecialchars($user['username']); ?></strong>, um Administrator-Rechte zu erhalten, geben Sie bitte den Administrator-Code ein:</p>
    
    <form method="POST" action="">
        <div class="form-group">
            <label for="admin_code">Administrator-Code:</label>
            <input type="password" id="admin_code" name="admin_code" class="form-control" required>
            <small>Wenn Sie den Code nicht kennen, wenden Sie sich bitte an den Systemadministrator.</small>
        </div>
        
        <button type="submit" name="make_admin" class="btn">Administrator werden</button>
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

.admin-request-form {
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
</style>

