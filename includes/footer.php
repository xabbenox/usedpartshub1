    </main>
    <footer>
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h3>Über UsedPartsHub</h3>
                    <p>Die Plattform für gebrauchte Autoersatzteile in Österreich. Kaufen und verkaufen Sie Autoteile einfach und sicher.</p>
                </div>
                <div class="footer-section">
                    <h3>Schnelllinks</h3>
                    <ul>
                        <li><a href="<?php echo SITE_URL; ?>">Startseite</a></li>
                        <li><a href="<?php echo SITE_URL; ?>/advanced-search.php">Erweiterte Suche</a></li>
                        <li><a href="<?php echo SITE_URL; ?>/create-listing.php">Inserat erstellen</a></li>
                        <li><a href="<?php echo SITE_URL; ?>/about.php">Über uns</a></li>
                    </ul>
                </div>
                <div class="footer-section">
                    <h3>Kategorien</h3>
                    <ul>
                        <?php
                        // Fetch main categories for footer
                        $stmt = $pdo->query("SELECT category_id, name FROM categories LIMIT 6");
                        while ($category = $stmt->fetch()) {
                            echo '<li><a href="' . SITE_URL . '/category.php?id=' . $category['category_id'] . '">' 
                                . htmlspecialchars($category['name']) . '</a></li>';
                        }
                        ?>
                    </ul>
                </div>
                <div class="footer-section">
                    <h3>Kontakt</h3>
                    <p><i class="fas fa-envelope"></i> info@usedpartshub.at</p>
                    <p><i class="fas fa-phone"></i> +43 123 456789</p>
                    <div class="social-links">
                        <a href="#"><i class="fab fa-facebook"></i></a>
                        <a href="#"><i class="fab fa-twitter"></i></a>
                        <a href="#"><i class="fab fa-instagram"></i></a>
                    </div>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> UsedPartsHub. Alle Rechte vorbehalten.</p>
                <div class="footer-links">
                    <a href="<?php echo SITE_URL; ?>/terms.php">AGB</a>
                    <a href="<?php echo SITE_URL; ?>/privacy.php">Datenschutz</a>
                    <a href="<?php echo SITE_URL; ?>/imprint.php">Impressum</a>
                </div>
            </div>
        </div>
    </footer>
    <script src="<?php echo SITE_URL; ?>/assets/js/main.js"></script>
</body>
</html>