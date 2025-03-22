<?php
// Deaktiviere HTML-Fehlerausgabe für API-Endpunkte
ini_set('display_errors', 0);
error_reporting(0);

// Setze den Content-Type auf JSON
header('Content-Type: application/json');

try {
    // Datenbankverbindung herstellen
    require_once 'includes/config.php';
    
    // Überprüfe, ob make_id vorhanden ist
    if (!isset($_GET['make_id']) || empty($_GET['make_id'])) {
        throw new Exception('Keine Marken-ID angegeben');
    }
    
    $makeId = (int)$_GET['make_id'];
    
    // Hole Modelle für die angegebene Marke
    $stmt = $pdo->prepare("SELECT model_id, name FROM car_models WHERE make_id = ? ORDER BY name");
    $stmt->execute([$makeId]);
    
    $models = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($models)) {
        echo json_encode([]);
    } else {
        echo json_encode($models);
    }
    
} catch (Exception $e) {
    // Bei Fehlern gib einen JSON-Fehler zurück
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>