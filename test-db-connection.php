<?php
// Aktiviere Fehleranzeige für Debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>Datenbank-Verbindungstest</h1>";

try {
    // Datenbankverbindung herstellen
    require_once 'includes/config.php';
    
    echo "<p>Datenbankverbindung erfolgreich hergestellt.</p>";
    
    // Teste, ob die car_makes Tabelle existiert und Daten enthält
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM car_makes");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "<p>Anzahl der Fahrzeugmarken in der Datenbank: " . $result['count'] . "</p>";
    
    // Zeige die ersten 10 Marken an
    echo "<h2>Verfügbare Fahrzeugmarken:</h2>";
    echo "<ul>";
    
    $stmt = $pdo->query("SELECT make_id, name FROM car_makes ORDER BY name LIMIT 10");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "<li>ID: " . $row['make_id'] . " - Name: " . htmlspecialchars($row['name']) . "</li>";
    }
    
    echo "</ul>";
    
    // Teste, ob die car_models Tabelle existiert
    echo "<h2>Modelle-Tabelle Test:</h2>";
    
    $stmt = $pdo->query("SHOW TABLES LIKE 'car_models'");
    if ($stmt->rowCount() > 0) {
        echo "<p>Die Tabelle 'car_models' existiert.</p>";
        
        // Prüfe die Struktur der Tabelle
        echo "<h3>Struktur der car_models Tabelle:</h3>";
        echo "<pre>";
        $stmt = $pdo->query("DESCRIBE car_models");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            print_r($row);
        }
        echo "</pre>";
        
        // Zeige einige Beispielmodelle
        $stmt = $pdo->query("SELECT model_id, make_id, name FROM car_models LIMIT 5");
        if ($stmt->rowCount() > 0) {
            echo "<h3>Beispielmodelle:</h3>";
            echo "<ul>";
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                echo "<li>ID: " . $row['model_id'] . " - Make ID: " . $row['make_id'] . " - Name: " . htmlspecialchars($row['name']) . "</li>";
            }
            echo "</ul>";
        } else {
            echo "<p>Keine Modelle in der Datenbank gefunden.</p>";
        }
    } else {
        echo "<p>Die Tabelle 'car_models' existiert nicht!</p>";
    }
    
} catch (PDOException $e) {
    echo "<p>Fehler bei der Datenbankverbindung: " . $e->getMessage() . "</p>";
}
?>