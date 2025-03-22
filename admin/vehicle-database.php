<?php
$pageTitle = 'Fahrzeugdatenbank verwalten';
require_once '../includes/admin-header.php';

// Prüfen, ob der Benutzer ein Admin ist
if (!isAdmin()) {
  redirect(SITE_URL);
  exit;
}

// Aktionen verarbeiten
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Marke hinzufügen
  if (isset($_POST['add_make'])) {
    $makeName = sanitizeInput($_POST['make_name']);
    
    if (empty($makeName)) {
      $_SESSION['error'] = "Bitte geben Sie einen Namen für die Marke ein.";
    } else {
      try {
        // Prüfen, ob die Marke bereits existiert
        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM car_makes WHERE name = ?");
        $checkStmt->execute([$makeName]);
        
        if ($checkStmt->fetchColumn() > 0) {
          $_SESSION['error'] = "Diese Marke existiert bereits.";
        } else {
          $stmt = $pdo->prepare("INSERT INTO car_makes (name) VALUES (?)");
          $stmt->execute([$makeName]);
          $_SESSION['success'] = "Marke erfolgreich hinzugefügt.";
        }
      } catch (PDOException $e) {
        $_SESSION['error'] = "Fehler beim Hinzufügen der Marke: " . $e->getMessage();
      }
    }
  }
  
  // Modell hinzufügen
  if (isset($_POST['add_model'])) {
    $makeId = (int)$_POST['make_id'];
    $modelName = sanitizeInput($_POST['model_name']);
    
    if (empty($makeId) || empty($modelName)) {
      $_SESSION['error'] = "Bitte wählen Sie eine Marke und geben Sie einen Namen für das Modell ein.";
    } else {
      try {
        // Prüfen, ob das Modell bereits existiert
        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM car_models WHERE make_id = ? AND name = ?");
        $checkStmt->execute([$makeId, $modelName]);
        
        if ($checkStmt->fetchColumn() > 0) {
          $_SESSION['error'] = "Dieses Modell existiert bereits für diese Marke.";
        } else {
          $stmt = $pdo->prepare("INSERT INTO car_models (make_id, name) VALUES (?, ?)");
          $stmt->execute([$makeId, $modelName]);
          $_SESSION['success'] = "Modell erfolgreich hinzugefügt.";
        }
      } catch (PDOException $e) {
        $_SESSION['error'] = "Fehler beim Hinzufügen des Modells: " . $e->getMessage();
      }
    }
  }
  
  // Marke löschen
  if (isset($_POST['delete_make']) && isset($_POST['make_id'])) {
    $makeId = (int)$_POST['make_id'];
    
    try {
      // Prüfen, ob die Marke in Verwendung ist
      $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM parts WHERE make_id = ?");
      $checkStmt->execute([$makeId]);
      
      if ($checkStmt->fetchColumn() > 0) {
        $_SESSION['error'] = "Diese Marke kann nicht gelöscht werden, da sie in Inseraten verwendet wird.";
      } else {
        // Zuerst alle Modelle dieser Marke löschen
        $pdo->prepare("DELETE FROM car_models WHERE make_id = ?")->execute([$makeId]);
        // Dann die Marke löschen
        $pdo->prepare("DELETE FROM car_makes WHERE make_id = ?")->execute([$makeId]);
        $_SESSION['success'] = "Marke und zugehörige Modelle erfolgreich gelöscht.";
      }
    } catch (PDOException $e) {
      $_SESSION['error'] = "Fehler beim Löschen der Marke: " . $e->getMessage();
    }
  }
  
  // Modell löschen
  if (isset($_POST['delete_model']) && isset($_POST['model_id'])) {
    $modelId = (int)$_POST['model_id'];
    
    try {
      // Prüfen, ob das Modell in Verwendung ist
      $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM parts WHERE model_id = ?");
      $checkStmt->execute([$modelId]);
      
      if ($checkStmt->fetchColumn() > 0) {
        $_SESSION['error'] = "Dieses Modell kann nicht gelöscht werden, da es in Inseraten verwendet wird.";
      } else {
        $pdo->prepare("DELETE FROM car_models WHERE model_id = ?")->execute([$modelId]);
        $_SESSION['success'] = "Modell erfolgreich gelöscht.";
      }
    } catch (PDOException $e) {
      $_SESSION['error'] = "Fehler beim Löschen des Modells: " . $e->getMessage();
    }
  }
  
  // Massenimport von Marken und Modellen
  if (isset($_POST['import_data'])) {
    $importData = $_POST['import_json'];
    
    if (empty($importData)) {
      $_SESSION['error'] = "Bitte geben Sie Daten zum Importieren ein.";
    } else {
      try {
        $data = json_decode($importData, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
          $_SESSION['error'] = "Ungültiges JSON-Format.";
        } else {
          $pdo->beginTransaction();
          
          $addedMakes = 0;
          $addedModels = 0;
          $skippedMakes = 0;
          $skippedModels = 0;
          
          foreach ($data as $make => $models) {
            // Prüfen, ob die Marke bereits existiert
            $checkStmt = $pdo->prepare("SELECT make_id FROM car_makes WHERE name = ?");
            $checkStmt->execute([$make]);
            $makeResult = $checkStmt->fetch();
            
            if ($makeResult) {
              $makeId = $makeResult['make_id'];
              $skippedMakes++;
            } else {
              // Marke hinzufügen
              $makeStmt = $pdo->prepare("INSERT INTO car_makes (name) VALUES (?)");
              $makeStmt->execute([$make]);
              $makeId = $pdo->lastInsertId();
              $addedMakes++;
            }
            
            // Modelle hinzufügen
            if (is_array($models)) {
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
          }
          
          $pdo->commit();
          $_SESSION['success'] = "Import abgeschlossen: $addedMakes Marken und $addedModels Modelle hinzugefügt. $skippedMakes Marken und $skippedModels Modelle übersprungen (bereits vorhanden).";
        }
      } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "Fehler beim Importieren der Daten: " . $e->getMessage();
      }
    }
  }
  
  // Redirect, um Formular-Resubmission zu vermeiden
  redirect($_SERVER['PHP_SELF']);
  exit;
}

// Marken abrufen
$makesStmt = $pdo->query("SELECT m.*, COUNT(mo.model_id) as model_count FROM car_makes m LEFT JOIN car_models mo ON m.make_id = mo.make_id GROUP BY m.make_id ORDER BY m.name");
$makes = $makesStmt->fetchAll();

// Aktive Marke für die Modellanzeige
$activeMakeId = isset($_GET['make_id']) ? (int)$_GET['make_id'] : (count($makes) > 0 ? $makes[0]['make_id'] : 0);

// Modelle für die aktive Marke abrufen
$modelsStmt = $pdo->prepare("SELECT * FROM car_models WHERE make_id = ? ORDER BY name");
$modelsStmt->execute([$activeMakeId]);
$models = $modelsStmt->fetchAll();

// Aktive Marke für die Anzeige ermitteln
$activeMake = null;
foreach ($makes as $make) {
  if ($make['make_id'] == $activeMakeId) {
    $activeMake = $make;
    break;
  }
}
?>

<div class="admin-container">
  <div class="admin-header">
    <h1><i class="fas fa-car"></i> Fahrzeugdatenbank verwalten</h1>
    <p>Hier können Sie Fahrzeugmarken und -modelle verwalten</p>
  </div>
  
  <?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success">
      <i class="fas fa-check-circle"></i> <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
    </div>
  <?php endif; ?>
  
  <?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-danger">
      <i class="fas fa-exclamation-circle"></i> <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
    </div>
  <?php endif; ?>
  
  <div class="admin-content">
    <div class="vehicle-db-grid">
      <!-- Marken-Verwaltung -->
      <div class="vehicle-makes-section">
        <div class="section-header">
          <h2>Marken</h2>
          <button type="button" class="btn btn-sm btn-primary" data-toggle="modal" data-target="#addMakeModal">
            <i class="fas fa-plus"></i> Neue Marke
          </button>
        </div>
        
        <div class="makes-list">
          <?php if (count($makes) > 0): ?>
            <div class="table-responsive">
              <table class="admin-table">
                <thead>
                  <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Modelle</th>
                    <th>Aktionen</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($makes as $make): ?>
                    <tr class="<?php echo ($make['make_id'] == $activeMakeId) ? 'active-row' : ''; ?>">
                      <td><?php echo $make['make_id']; ?></td>
                      <td>
                        <a href="?make_id=<?php echo $make['make_id']; ?>" class="make-name">
                          <?php echo htmlspecialchars($make['name']); ?>
                        </a>
                      </td>
                      <td><?php echo $make['model_count']; ?></td>
                      <td>
                        <div class="action-buttons">
                          <a href="?make_id=<?php echo $make['make_id']; ?>" class="btn btn-sm btn-outline" title="Modelle anzeigen">
                            <i class="fas fa-list"></i>
                          </a>
                          <form method="POST" class="d-inline" onsubmit="return confirm('Möchten Sie diese Marke wirklich löschen? Alle zugehörigen Modelle werden ebenfalls gelöscht.');">
                            <input type="hidden" name="make_id" value="<?php echo $make['make_id']; ?>">
                            <button type="submit" name="delete_make" class="btn btn-sm btn-danger" title="Löschen">
                              <i class="fas fa-trash"></i>
                            </button>
                          </form>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php else: ?>
            <div class="empty-state">
              <i class="fas fa-car fa-3x"></i>
              <h3>Keine Marken gefunden</h3>
              <p>Fügen Sie Ihre erste Fahrzeugmarke hinzu.</p>
            </div>
          <?php endif; ?>
        </div>
      </div>
      
      <!-- Modell-Verwaltung -->
      <div class="vehicle-models-section">
        <div class="section-header">
          <h2>
            <?php if ($activeMake): ?>
              Modelle für <?php echo htmlspecialchars($activeMake['name']); ?>
            <?php else: ?>
              Modelle
            <?php endif; ?>
          </h2>
          <?php if ($activeMakeId > 0): ?>
            <button type="button" class="btn btn-sm btn-primary" data-toggle="modal" data-target="#addModelModal">
              <i class="fas fa-plus"></i> Neues Modell
            </button>
          <?php endif; ?>
        </div>
        
        <div class="models-list">
          <?php if ($activeMakeId > 0): ?>
            <?php if (count($models) > 0): ?>
              <div class="table-responsive">
                <table class="admin-table">
                  <thead>
                    <tr>
                      <th>ID</th>
                      <th>Name</th>
                      <th>Aktionen</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($models as $model): ?>
                      <tr>
                        <td><?php echo $model['model_id']; ?></td>
                        <td><?php echo htmlspecialchars($model['name']); ?></td>
                        <td>
                          <form method="POST" class="d-inline" onsubmit="return confirm('Möchten Sie dieses Modell wirklich löschen?');">
                            <input type="hidden" name="model_id" value="<?php echo $model['model_id']; ?>">
                            <button type="submit" name="delete_model" class="btn btn-sm btn-danger" title="Löschen">
                              <i class="fas fa-trash"></i>
                            </button>
                          </form>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php else: ?>
              <div class="empty-state">
                <i class="fas fa-car-side fa-3x"></i>
                <h3>Keine Modelle gefunden</h3>
                <p>Fügen Sie Modelle für diese Marke hinzu.</p>
              </div>
            <?php endif; ?>
          <?php else: ?>
            <div class="empty-state">
              <i class="fas fa-info-circle fa-3x"></i>
              <h3>Keine Marke ausgewählt</h3>
              <p>Bitte wählen Sie eine Marke aus der Liste links.</p>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
    
    <!-- Massenimport-Bereich -->
    <div class="mass-import-section">
      <div class="section-header">
        <h2><i class="fas fa-file-import"></i> Massenimport</h2>
      </div>
      
      <div class="import-form-container">
        <p>Importieren Sie mehrere Marken und Modelle auf einmal im JSON-Format.</p>
        <p>Beispielformat:</p>
        <pre>{
  "BMW": ["1er", "2er", "3er", "5er", "7er", "X1", "X3", "X5"],
  "Mercedes-Benz": ["A-Klasse", "B-Klasse", "C-Klasse", "E-Klasse", "S-Klasse"]
}</pre>
        
        <form method="POST" action="">
          <div class="form-group">
            <label for="import_json">JSON-Daten:</label>
            <textarea id="import_json" name="import_json" class="form-control" rows="10" placeholder="Fügen Sie hier Ihre JSON-Daten ein..."></textarea>
          </div>
          
          <button type="submit" name="import_data" class="btn btn-primary">
            <i class="fas fa-upload"></i> Daten importieren
          </button>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- Modal: Marke hinzufügen -->
<div class="modal" id="addMakeModal" tabindex="-1" role="dialog">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Neue Marke hinzufügen</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Schließen">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <form method="POST" action="">
        <div class="modal-body">
          <div class="form-group">
            <label for="make_name">Markenname:</label>
            <input type="text" id="make_name" name="make_name" class="form-control" required>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Abbrechen</button>
          <button type="submit" name="add_make" class="btn btn-primary">Hinzufügen</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal: Modell hinzufügen -->
<div class="modal" id="addModelModal" tabindex="-1" role="dialog">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Neues Modell hinzufügen</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Schließen">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <form method="POST" action="">
        <div class="modal-body">
          <div class="form-group">
            <label for="model_make_id">Marke:</label>
            <select id="model_make_id" name="make_id" class="form-control" required>
              <?php foreach ($makes as $make): ?>
                <option value="<?php echo $make['make_id']; ?>" <?php echo ($make['make_id'] == $activeMakeId) ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($make['name']); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label for="model_name">Modellname:</label>
            <input type="text" id="model_name" name="model_name" class="form-control" required>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Abbrechen</button>
          <button type="submit" name="add_model" class="btn btn-primary">Hinzufügen</button>
        </div>
      </form>
    </div>
  </div>
</div>

<style>
/* Fahrzeugdatenbank-Verwaltung Styles */
.admin-container {
  max-width: 1200px;
  margin: 0 auto;
  padding: 20px;
}

.admin-header {
  margin-bottom: 30px;
}

.admin-header h1 {
  font-size: 2rem;
  color: #2c3e50;
  margin-bottom: 10px;
  display: flex;
  align-items: center;
}

.admin-header h1 i {
  margin-right: 15px;
  color: #3498db;
}

.admin-header p {
  color: #7f8c8d;
  font-size: 1.1rem;
}

.admin-content {
  background-color: white;
  border-radius: 10px;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
  padding: 25px;
  margin-bottom: 30px;
}

.vehicle-db-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 30px;
  margin-bottom: 30px;
}

.section-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
  padding-bottom: 10px;
  border-bottom: 1px solid #eee;
}

.section-header h2 {
  font-size: 1.4rem;
  color: #2c3e50;
  margin: 0;
}

.admin-table {
  width: 100%;
  border-collapse: collapse;
}

.admin-table th, .admin-table td {
  padding: 12px 15px;
  text-align: left;
  border-bottom: 1px solid #eee;
}

.admin-table th {
  background-color: #f8f9fa;
  font-weight: 600;
  color: #2c3e50;
}

.admin-table tr:hover {
  background-color: #f8f9fa;
}

.admin-table tr.active-row {
  background-color: #f0f7ff;
}

.make-name {
  font-weight: 600;
  color: #2c3e50;
  text-decoration: none;
}

.make-name:hover {
  color: #3498db;
}

.action-buttons {
  display: flex;
  gap: 5px;
}

.d-inline {
  display: inline;
}

.empty-state {
  text-align: center;
  padding: 40px 0;
  color: #7f8c8d;
}

.empty-state i {
  margin-bottom: 20px;
  color: #bdc3c7;
}

.empty-state h3 {
  margin-bottom: 10px;
  color: #2c3e50;
}

.mass-import-section {
  background-color: #f8f9fa;
  border-radius: 8px;
  padding: 20px;
}

.import-form-container {
  max-width: 800px;
  margin: 0 auto;
}

pre {
  background-color: #f1f1f1;
  padding: 15px;
  border-radius: 5px;
  overflow-x: auto;
  margin-bottom: 20px;
  font-size: 0.9rem;
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
  border-radius: 6px;
  font-size: 1rem;
  transition: border-color 0.3s ease, box-shadow 0.3s ease;
}

.form-control:focus {
  border-color: #3498db;
  box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.2);
  outline: none;
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

/* Modal Styles */
.modal {
  display: none;
  position: fixed;
  z-index: 1000;
  left: 0;
  top: 0;
  width: 100%;
  height: 100%;
  overflow: auto;
  background-color: rgba(0, 0, 0, 0.4);
}

.modal-dialog {
  margin: 10% auto;
  width: 90%;
  max-width: 500px;
}

.modal-content {
  background-color: white;
  border-radius: 8px;
  box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
}

.modal-header {
  padding: 15px 20px;
  border-bottom: 1px solid #eee;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.modal-title {
  font-size: 1.3rem;
  color: #2c3e50;
  margin: 0;
}

.close {
  background: none;
  border: none;
  font-size: 1.5rem;
  cursor: pointer;
  color: #7f8c8d;
}

.modal-body {
  padding: 20px;
}

.modal-footer {
  padding: 15px 20px;
  border-top: 1px solid #eee;
  display: flex;
  justify-content: flex-end;
  gap: 10px;
}

/* Responsive adjustments */
@media (max-width: 992px) {
  .vehicle-db-grid {
    grid-template-columns: 1fr;
    gap: 20px;
  }
}

@media (max-width: 576px) {
  .action-buttons {
    flex-direction: column;
    gap: 5px;
  }
  
  .action-buttons .btn {
    width: 100%;
  }
  
  .admin-table th, .admin-table td {
    padding: 10px;
  }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
  // Modal-Funktionalität
  const modals = document.querySelectorAll('.modal');
  const modalTriggers = document.querySelectorAll('[data-toggle="modal"]');
  const modalCloseButtons = document.querySelectorAll('[data-dismiss="modal"]');
  
  // Modal öffnen
  modalTriggers.forEach(trigger => {
    trigger.addEventListener('click', function() {
      const modalId = this.getAttribute('data-target');
      const modal = document.querySelector(modalId);
      if (modal) {
        modal.style.display = 'block';
      }
    });
  });
  
  // Modal schließen (über X oder Abbrechen-Button)
  modalCloseButtons.forEach(button => {
    button.addEventListener('click', function() {
      const modal = this.closest('.modal');
      if (modal) {
        modal.style.display = 'none';
      }
    });
  });
  
  // Modal schließen, wenn außerhalb geklickt wird
  window.addEventListener('click', function(event) {
    modals.forEach(modal => {
      if (event.target === modal) {
        modal.style.display = 'none';
      }
    });
  });
});
</script>

<?php require_once '../includes/admin-footer.php'; ?>

