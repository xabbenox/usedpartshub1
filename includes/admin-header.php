<?php 
require_once '../includes/config.php';

// Prüfen, ob der Benutzer ein Admin ist
function isAdmin() {
    if (!isLoggedIn()) {
        return false;
    }
    
    global $pdo;
    $stmt = $pdo->prepare("SELECT is_admin FROM users WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    
    return $user && $user['is_admin'] == 1;
}

// Wenn nicht eingeloggt oder kein Admin, umleiten
if (!isAdmin()) {
    $_SESSION['error'] = "Sie haben keine Berechtigung, auf diesen Bereich zuzugreifen.";
    redirect(SITE_URL);
    exit;
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' - Admin' : 'Admin'; ?> | <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/admin.css">
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/style.css">
</head>
<body>
    <header class="admin-header">
        <div class="container">
            <div class="admin-header-content">
                <div class="admin-logo">
                    <a href="<?php echo SITE_URL; ?>/admin/">
                        <h1><?php echo SITE_NAME; ?> Admin</h1>
                    </a>
                </div>
                <div class="admin-nav">
                    <ul>
                        <li><a href="<?php echo SITE_URL; ?>/admin/"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                        <li><a href="<?php echo SITE_URL; ?>/admin/users.php"><i class="fas fa-users"></i> Benutzer</a></li>
                        <li><a href="<?php echo SITE_URL; ?>/admin/listings.php"><i class="fas fa-list"></i> Inserate</a></li>
                        <li><a href="<?php echo SITE_URL; ?>/admin/categories.php"><i class="fas fa-tags"></i> Kategorien</a></li>
                        <li><a href="<?php echo SITE_URL; ?>/admin/vehicle-database.php"><i class="fas fa-car"></i> Fahrzeuge</a></li>
                        <li><a href="<?php echo SITE_URL; ?>/admin/settings.php"><i class="fas fa-cog"></i> Einstellungen</a></li>
                        <li><a href="<?php echo SITE_URL; ?>"><i class="fas fa-home"></i> Zur Website</a></li>
                        <li><a href="<?php echo SITE_URL; ?>/logout.php"><i class="fas fa-sign-out-alt"></i> Abmelden</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </header>
    <main class="admin-main">

