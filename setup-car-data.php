<?php
// Aktiviere Fehleranzeige für Debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Datenbankverbindung herstellen
require_once 'includes/config.php';

echo "<h1>Einrichtung der Fahrzeugdaten</h1>";

try {
    // Überprüfe, ob die Tabellen existieren
    $transactionStarted = false;
    
    // Beginne nur eine Transaktion, wenn wir tatsächlich Änderungen vornehmen werden
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM car_makes");
    $makesResult = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM car_models");
    $modelsResult = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Wenn entweder Marken oder Modelle hinzugefügt werden müssen, beginnen wir eine Transaktion
    if ($makesResult['count'] == 0 || $modelsResult['count'] == 0) {
        $pdo->beginTransaction();
        $transactionStarted = true;
    }
    
    // Erstelle car_makes Tabelle, falls sie nicht existiert
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS car_makes (
            make_id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            UNIQUE KEY (name)
        )
    ");
    
    // Erstelle car_models Tabelle, falls sie nicht existiert
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS car_models (
            model_id INT AUTO_INCREMENT PRIMARY KEY,
            make_id INT NOT NULL,
            name VARCHAR(100) NOT NULL,
            FOREIGN KEY (make_id) REFERENCES car_makes(make_id),
            UNIQUE KEY (make_id, name)
        )
    ");
    
    // Füge Beispieldaten für Marken hinzu, wenn die Tabelle leer ist
    if ($makesResult['count'] == 0) {
        echo "<p>Füge Beispiel-Fahrzeugmarken hinzu...</p>";
        
        $makes = [
            'Audi', 'BMW', 'Ford', 'Honda', 'Hyundai', 'Kia', 'Mazda', 'Mercedes-Benz', 
            'Nissan', 'Opel', 'Peugeot', 'Renault', 'Seat', 'Skoda', 'Toyota', 'Volkswagen', 'Volvo'
        ];
        
        $insertMake = $pdo->prepare("INSERT INTO car_makes (name) VALUES (?)");
        
        foreach ($makes as $make) {
            $insertMake->execute([$make]);
            echo "<p>Marke hinzugefügt: $make</p>";
        }
    } else {
        echo "<p>Fahrzeugmarken sind bereits vorhanden.</p>";
    }
    
    // Füge Beispieldaten für Modelle hinzu
    if ($modelsResult['count'] == 0) {
        echo "<p>Füge Beispiel-Fahrzeugmodelle hinzu...</p>";
        
        $models = [
            'Audi' => ['A1', 'A3', 'A4', 'A5', 'A6', 'Q3', 'Q5', 'Q7', 'TT'],
            'BMW' => ['1er', '2er', '3er', '4er', '5er', '6er', '7er', 'X1', 'X3', 'X5', 'Z4'],
            'Ford' => ['Fiesta', 'Focus', 'Kuga', 'Mondeo', 'Mustang', 'Puma', 'Ranger'],
            'Honda' => ['Accord', 'Civic', 'CR-V', 'Jazz'],
            'Hyundai' => ['i10', 'i20', 'i30', 'Kona', 'Tucson'],
            'Kia' => ['Ceed', 'Picanto', 'Rio', 'Sorento', 'Sportage'],
            'Mazda' => ['2', '3', '6', 'CX-3', 'CX-5', 'MX-5'],
            'Mercedes-Benz' => ['A-Klasse', 'B-Klasse', 'C-Klasse', 'E-Klasse', 'S-Klasse', 'GLA', 'GLC', 'GLE'],
            'Nissan' => ['Juke', 'Leaf', 'Micra', 'Qashqai', 'X-Trail'],
            'Opel' => ['Astra', 'Corsa', 'Grandland X', 'Insignia', 'Mokka'],
            'Peugeot' => ['108', '208', '308', '508', '2008', '3008', '5008'],
            'Renault' => ['Captur', 'Clio', 'Kadjar', 'Megane', 'Twingo', 'Zoe'],
            'Seat' => ['Arona', 'Ateca', 'Ibiza', 'Leon', 'Tarraco'],
            'Skoda' => ['Fabia', 'Kamiq', 'Karoq', 'Kodiaq', 'Octavia', 'Superb'],
            'Toyota' => ['Auris', 'Aygo', 'Corolla', 'Prius', 'RAV4', 'Yaris'],
            'Volkswagen' => ['Golf', 'Passat', 'Polo', 'T-Cross', 'T-Roc', 'Tiguan', 'Touareg', 'Touran'],
            'Volvo' => ['S60', 'S90', 'V40', 'V60', 'V90', 'XC40', 'XC60', 'XC90']
        ];
        
        // Hole alle Marken aus der Datenbank
        $stmt = $pdo->query("SELECT make_id, name FROM car_makes");
        $makes = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $makes[$row['name']] = $row['make_id'];
        }
        
        $insertModel = $pdo->prepare("INSERT INTO car_models (make_id, name) VALUES (?, ?)");
        
        foreach ($models as $makeName => $modelNames) {
            if (isset($makes[$makeName])) {
                $makeId = $makes[$makeName];
                
                foreach ($modelNames as $modelName) {
                    try {
                        $insertModel->execute([$makeId, $modelName]);
                        echo "<p>Modell hinzugefügt: $makeName - $modelName</p>";
                    } catch (PDOException $e) {
                        echo "<p>Fehler beim Hinzufügen des Modells $makeName - $modelName: " . $e->getMessage() . "</p>";
                        // Ignoriere Duplikate
                        if (!str_contains($e->getMessage(), 'Duplicate entry')) {
                            throw $e; // Wirf den Fehler weiter, wenn es kein Duplikat ist
                        }
                    }
                }
            } else {
                echo "<p>Marke '$makeName' nicht in der Datenbank gefunden.</p>";
            }
        }
    } else {
        echo "<p>Fahrzeugmodelle sind bereits vorhanden.</p>";
    }
    
    // Commit nur, wenn wir eine Transaktion gestartet haben
    if ($transactionStarted) {
        $pdo->commit();
    }
    
    echo "<h2>Einrichtung abgeschlossen!</h2>";
    echo "<p>Die Tabellen car_makes und car_models wurden erfolgreich erstellt und mit Beispieldaten gefüllt.</p>";
    echo "<p><a href='test-db-connection.php'>Datenbankverbindung testen</a></p>";
    echo "<p><a href='create-listing.php'>Zurück zum Inserat erstellen</a></p>";
    
} catch (Exception $e) {
    // Rollback nur, wenn wir eine Transaktion gestartet haben
    if (isset($transactionStarted) && $transactionStarted) {
        try {
            $pdo->rollBack();
        } catch (PDOException $rollbackException) {
            echo "<p>Rollback fehlgeschlagen: " . $rollbackException->getMessage() . "</p>";
        }
    }
    
    echo "<h2>Fehler!</h2>";
    echo "<p>Bei der Einrichtung ist ein Fehler aufgetreten: " . $e->getMessage() . "</p>";
}
?>