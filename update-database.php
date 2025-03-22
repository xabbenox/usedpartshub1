<?php
// Datenbankverbindung herstellen
require_once 'includes/config.php';

echo "<h1>Datenbank-Update</h1>";

try {
    // Überprüfen, ob die Spalte profile_image bereits existiert
    $checkColumn = $pdo->query("SHOW COLUMNS FROM users LIKE 'profile_image'");
    $columnExists = $checkColumn->rowCount() > 0;
    
    if (!$columnExists) {
        // Spalte hinzufügen, wenn sie nicht existiert
        $pdo->exec("ALTER TABLE users ADD COLUMN profile_image VARCHAR(255) DEFAULT NULL");
        echo "<p class='success'>Die Spalte 'profile_image' wurde erfolgreich zur Tabelle 'users' hinzugefügt.</p>";
    } else {
        echo "<p>Die Spalte 'profile_image' existiert bereits in der Tabelle 'users'.</p>";
    }
    
    echo "<p>Datenbank-Update abgeschlossen.</p>";
    echo "<p><a href='account/profile.php'>Zurück zum Profil</a></p>";
    
} catch (PDOException $e) {
    echo "<p class='error'>Fehler beim Aktualisieren der Datenbank: " . $e->getMessage() . "</p>";
}
?>

<style>
    body {
        font-family: Arial, sans-serif;
        max-width: 800px;
        margin: 0 auto;
        padding: 20px;
    }
    h1 {
        color: #333;
    }
    p {
        margin: 10px 0;
    }
    .success {
        color: green;
        font-weight: bold;
    }
    .error {
        color: red;
        font-weight: bold;
    }
    a {
        display: inline-block;
        margin-top: 20px;
        padding: 10px 15px;
        background-color: #3498db;
        color: white;
        text-decoration: none;
        border-radius: 4px;
    }
    a:hover {
        background-color: #2980b9;
    }
</style>

