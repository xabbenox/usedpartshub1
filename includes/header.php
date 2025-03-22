<?php require_once 'config.php'; ?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' - ' . SITE_NAME : SITE_NAME; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/style.css">
    <style>
.header-avatar {
    width: 24px;
    height: 24px;
    border-radius: 50%;
    object-fit: cover;
    margin-right: 5px;
}

.user-account-btn {
    display: flex;
    align-items: center;
}
</style>
</head>
<body>
    <header>
        <div class="container">
            <div class="header-top">
                <div class="logo">
                    <a href="<?php echo SITE_URL; ?>">
                        <h1>UsedPartsHub</h1>
                    </a>
                </div>
                <div class="search-bar">
                    <form action="<?php echo SITE_URL; ?>/search.php" method="GET">
                        <input type="text" name="q" placeholder="Suche nach Autoteilen..." 
                            value="<?php echo isset($_GET['q']) ? htmlspecialchars($_GET['q']) : ''; ?>">
                        <button type="submit"><i class="fas fa-search"></i></button>
                    </form>
                </div>
                <div class="user-actions">
                    <?php if (isLoggedIn()): ?>
                        <?php
                        // Hole Benutzerinformationen für das Profilbild
                        $userStmt = $pdo->prepare("SELECT profile_image FROM users WHERE user_id = ?");
                        $userStmt->execute([$_SESSION['user_id']]);
                        $currentUser = $userStmt->fetch();
                        ?>
                        <a href="<?php echo SITE_URL; ?>/account/dashboard.php" class="btn btn-sm user-account-btn">
                            <?php if (!empty($currentUser['profile_image'])): ?>
                                <img src="<?php echo SITE_URL . '/' . $currentUser['profile_image']; ?>" alt="Profilbild" class="header-avatar">
                            <?php else: ?>
                                <i class="fas fa-user"></i>
                            <?php endif; ?>
                            Mein Konto
                        </a>
                        <a href="<?php echo SITE_URL; ?>/account/messages.php" class="btn btn-sm">
                            Nachrichten
                            <?php
                            // Count unread messages
                            $stmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0");
                            $stmt->execute([$_SESSION['user_id']]);
                            $unreadCount = $stmt->fetchColumn();
                            if ($unreadCount > 0) {
                                echo '<span class="badge">' . $unreadCount . '</span>';
                            }
                            ?>
                        </a>
                        <a href="<?php echo SITE_URL; ?>/account/favorites.php" class="btn btn-sm">
                            <i class="fas fa-heart"></i>
                        </a>
                        <a href="<?php echo SITE_URL; ?>/logout.php" class="btn btn-sm btn-outline">Abmelden</a>
                    <?php else: ?>
                        <a href="<?php echo SITE_URL; ?>/login.php" class="btn btn-sm">Anmelden</a>
                        <a href="<?php echo SITE_URL; ?>/register.php" class="btn btn-sm btn-primary">Registrieren</a>
                    <?php endif; ?>
                    <a href="<?php echo SITE_URL; ?>/create-listing.php" class="btn btn-lg btn-primary">
                        <i class="fas fa-plus"></i> Inserat erstellen
                    </a>
                </div>
            </div>
            <nav class="main-nav">
                <ul>
                    <?php
                    // Fetch main categories
                    $stmt = $pdo->query("SELECT category_id, name FROM categories ORDER BY name");
                    while ($category = $stmt->fetch()) {
                        echo '<li><a href="' . SITE_URL . '/category.php?id=' . $category['category_id'] . '">' 
                            . htmlspecialchars($category['name']) . '</a></li>';
                    }
                    ?>
                    <li><a href="<?php echo SITE_URL; ?>/advanced-search.php">Erweiterte Suche</a></li>
                </ul>
            </nav>
        </div>
    </header>
    <main class="container">
        <?php
        // Display flash messages
        if (isset($_SESSION['error'])) {
            echo displayError($_SESSION['error']);
            unset($_SESSION['error']);
        }
        if (isset($_SESSION['success'])) {
            echo displaySuccess($_SESSION['success']);
            unset($_SESSION['success']);
        }
        ?>

