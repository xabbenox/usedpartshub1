<?php
// Aktiviere Fehleranzeige für Debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Datenbankverbindung herstellen
require_once 'includes/config.php';

echo "<h1>Fahrzeugdatenbank erweitern</h1>";

// Erweiterte Fahrzeugdaten
$vehicleData = [
    "Audi" => [
        "A1", "A2", "A3", "A4", "A5", "A6", "A7", "A8", 
        "Q2", "Q3", "Q5", "Q7", "Q8", 
        "TT", "R8", "e-tron", "e-tron GT"
    ],
    "BMW" => [
        "1er", "2er", "3er", "4er", "5er", "6er", "7er", "8er",
        "X1", "X2", "X3", "X4", "X5", "X6", "X7",
        "Z3", "Z4", "i3", "i4", "i8", "iX"
    ],
    "Mercedes-Benz" => [
        "A-Klasse", "B-Klasse", "C-Klasse", "E-Klasse", "S-Klasse", 
        "CLA", "CLS", "GLA", "GLB", "GLC", "GLE", "GLS", 
        "SL", "SLC", "AMG GT", "EQA", "EQB", "EQC", "EQE", "EQS"
    ],
    "Volkswagen" => [
        "Polo", "Golf", "Passat", "Arteon", "Touran", "Tiguan", "Touareg",
        "T-Cross", "T-Roc", "ID.3", "ID.4", "ID.5", "ID.6", "ID.Buzz",
        "Caddy", "Transporter", "Multivan", "California"
    ],
    "Opel" => [
        "Corsa", "Astra", "Insignia", "Crossland", "Grandland", "Mokka",
        "Combo", "Zafira", "Vivaro", "Movano"
    ],
    "Ford" => [
        "Fiesta", "Focus", "Mondeo", "Kuga", "Puma", "EcoSport", "Edge",
        "Mustang", "Ranger", "Transit", "Explorer", "S-Max", "Galaxy"
    ],
    "Toyota" => [
        "Aygo", "Yaris", "Corolla", "Camry", "C-HR", "RAV4", "Highlander",
        "Land Cruiser", "Prius", "Mirai", "Supra", "bZ4X", "Proace"
    ],
    "Honda" => [
        "Jazz", "Civic", "Accord", "HR-V", "CR-V", "e", "NSX"
    ],
    "Hyundai" => [
        "i10", "i20", "i30", "Kona", "Tucson", "Santa Fe", "IONIQ", "IONIQ 5", "IONIQ 6"
    ],
    "Kia" => [
        "Picanto", "Rio", "Ceed", "XCeed", "Proceed", "Stonic", "Niro", "Sportage", "Sorento", "EV6"
    ],
    "Mazda" => [
        "2", "3", "6", "CX-3", "CX-30", "CX-5", "CX-60", "MX-5", "MX-30"
    ],
    "Nissan" => [
        "Micra", "Juke", "Qashqai", "X-Trail", "Leaf", "Ariya", "370Z", "GT-R"
    ],
    "Renault" => [
        "Clio", "Captur", "Megane", "Arkana", "Kadjar", "Koleos", "Twingo", "Zoe"
    ],
    "Peugeot" => [
        "108", "208", "308", "508", "2008", "3008", "5008", "Rifter", "Traveller", "e-208", "e-2008"
    ],
    "Citroën" => [
        "C1", "C3", "C4", "C5", "C3 Aircross", "C5 Aircross", "Berlingo", "SpaceTourer", "ë-C4"
    ],
    "Fiat" => [
        "500", "Panda", "Tipo", "500X", "500L", "Doblo", "Ducato", "500e"
    ],
    "Seat" => [
        "Ibiza", "Leon", "Arona", "Ateca", "Tarraco", "Alhambra"
    ],
    "Škoda" => [
        "Fabia", "Scala", "Octavia", "Superb", "Kamiq", "Karoq", "Kodiaq", "Enyaq"
    ],
    "Volvo" => [
        "S60", "S90", "V60", "V90", "XC40", "XC60", "XC90", "C40"
    ],
    "Porsche" => [
        "911", "718 Boxster", "718 Cayman", "Panamera", "Cayenne", "Macan", "Taycan"
    ],
    "Jaguar" => [
        "XE", "XF", "F-Type", "E-Pace", "F-Pace", "I-Pace"
    ],
    "Land Rover" => [
        "Defender", "Discovery", "Discovery Sport", "Range Rover", "Range Rover Sport", "Range Rover Evoque", "Range Rover Velar"
    ],
    "Mini" => [
        "Hatch", "Clubman", "Countryman", "Convertible", "Electric"
    ],
    "Alfa Romeo" => [
        "Giulia", "Stelvio", "Tonale"
    ],
    "Jeep" => [
        "Renegade", "Compass", "Cherokee", "Grand Cherokee", "Wrangler", "Gladiator"
    ],
    "Suzuki" => [
        "Ignis", "Swift", "Vitara", "S-Cross", "Jimny"
    ],
    "Dacia" => [
        "Sandero", "Logan", "Duster", "Spring", "Jogger"
    ],
    "Tesla" => [
        "Model 3", "Model S", "Model X", "Model Y", "Cybertruck", "Roadster"
    ]
];

try {
    // Beginne eine Transaktion
    $pdo->beginTransaction();
    
    $addedMakes = 0;
    $addedModels = 0;
    $skippedMakes = 0;
    $skippedModels = 0;
    
    foreach ($vehicleData as $make => $models) {
        // Prüfen, ob die Marke bereits existiert
        $checkStmt = $pdo->prepare("SELECT make_id FROM car_makes WHERE name = ?");
        $checkStmt->execute([$make]);
        $makeResult = $checkStmt->fetch();
        
        if ($makeResult) {
            $makeId = $makeResult['make_id'];
            $skippedMakes++;
            echo "<p>Marke '$make' existiert bereits (ID: $makeId).</p>";
        } else {
            // Marke hinzufügen
            $makeStmt = $pdo->prepare("INSERT INTO car_makes (name) VALUES (?)");
            $makeStmt->execute([$make]);
            $makeId = $pdo->lastInsertId();
            $addedMakes++;
            echo "<p>Marke '$make' wurde hinzugefügt (ID: $makeId).</p>";
        }
        
        // Modelle hinzufügen
        foreach ($models as $model) {
            // Prüfen, ob das Modell bereits existiert
            $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM car_models WHERE make_id = ? AND name = ?");
            $checkStmt->execute([$makeId, $model]);
            
            if ($checkStmt->fetchColumn() > 0) {
                $skippedModels++;
            } else {
                $modelStmt = $pdo->prepare("INSERT INTO car_models (make_id, name) VALUES (?, ?)");
                $modelStmt->execute([$makeId, $model]);
                $addedModels++;
            }
        }
    }
    
    // Transaktion abschließen
    $pdo->commit();
    
    echo "<div class='success-message'>";
    echo "<h2>Fahrzeugdatenbank erfolgreich erweitert!</h2>";
    echo "<p>$addedMakes neue Marken hinzugefügt.</p>";
    echo "<p>$addedModels neue Modelle hinzugefügt.</p>";
    echo "<p>$skippedMakes existierende Marken übersprungen.</p>";
    echo "<p>$skippedModels existierende Modelle übersprungen.</p>";
    echo "</div>";
    
    echo "<div class='links'>";
    echo "<a href='admin/vehicle-database.php' class='btn'>Fahrzeugdatenbank verwalten</a>";
    echo "<a href='advanced-search.php' class='btn'>Zur erweiterten Suche</a>";
    echo "</div>";
    
} catch (Exception $e) {
    // Bei Fehler Transaktion zurückrollen
    $pdo->rollBack();
    echo "<div class='error-message'>";
    echo "<h2>Fehler beim Erweitern der Fahrzeugdatenbank</h2>";
    echo "<p>" . $e->getMessage() . "</p>";
    echo "</div>";
}
?>

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
    margin-top: 30px;
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

.links {
    margin-top: 30px;
    display: flex;
    gap: 15px;
    justify-content: center;
}

.btn {
    display: inline-block;
    background-color: #3498db;
    color: white;
    padding: 10px 20px;
    text-decoration: none;
    border-radius: 5px;
    transition: background-color 0.3s;
}

.btn:hover {
    background-color: #2980b9;
}
</style>

