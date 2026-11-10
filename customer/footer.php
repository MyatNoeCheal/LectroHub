<?php

require_once "../lectrohub_db/connection.php";
require_once __DIR__ . "/lang/translate.php";
$categories        = [];


$sql = "SELECT * FROM categories ORDER BY category_name ASC";
$stmt = $conn->query($sql);

if ($stmt) {
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

?>

<footer>
    <div class="footer-grid">
        <div class="footer-brand">
            <div class="logo">Lectro<span>Hub</span></div>
            <p><?= htmlspecialchars(t('footer_tagline')) ?></p>
            <div class="social-links">
                <a href="#" class="social-link">𝕏</a>
                <a href="#" class="social-link">in</a>
                <a href="#" class="social-link">ig</a>
                <a href="#" class="social-link">yt</a>
            </div>
        </div>

        <!-- Dynamic category links from DB (category names are not translated) -->
        <div class="footer-col">
            <h4><?= htmlspecialchars(t('footer_shop')) ?></h4>
            <ul>
                <?php foreach (array_slice($categories, 0, 5) as $cat): ?>
                    <li>
                        <a href="products.php?cat=<?= urlencode($cat['category_name']) ?>">
                            <?= htmlspecialchars($cat['category_name']) ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <div class="footer-col">
            <h4><?= htmlspecialchars(t('footer_support')) ?></h4>
            <ul>
                <li><a href="faq.php"><?= htmlspecialchars(t('footer_faq')) ?></a></li>
                <li><a href="#"><?= htmlspecialchars(t('footer_track_order')) ?></a></li>
                <li><a href="#"><?= htmlspecialchars(t('footer_returns')) ?></a></li>
                <li><a href="#"><?= htmlspecialchars(t('footer_warranty')) ?></a></li>
                <li><a href="#"><?= htmlspecialchars(t('footer_contact')) ?></a></li>
            </ul>
        </div>

        <div class="footer-col">
            <h4><?= htmlspecialchars(t('footer_company')) ?></h4>
            <ul>
                <li><a href="#"><?= htmlspecialchars(t('footer_about')) ?></a></li>
                <li><a href="#"><?= htmlspecialchars(t('footer_careers')) ?></a></li>
                <li><a href="#"><?= htmlspecialchars(t('footer_press')) ?></a></li>
                <li><a href="#"><?= htmlspecialchars(t('footer_privacy')) ?></a></li>
                <li><a href="#"><?= htmlspecialchars(t('footer_terms')) ?></a></li>
            </ul>
        </div>
    </div>

    <div class="footer-bottom">
        <p>© <?= date('Y') ?> LectroHub. <?= htmlspecialchars(t('footer_rights')) ?></p>
        <div class="payment-icons">
            <span class="pay-icon">VISA</span>
            <span class="pay-icon">MC</span>
            <span class="pay-icon">AMEX</span>
            <span class="pay-icon">PAYPAL</span>
            <span class="pay-icon">APPLE</span>
        </div>
    </div>

   <?php
    /* Tell the chat widget who's logged in (both password AND Google
       logins land here the same way, since both write $_SESSION['user']['id']
       the same way — auth.php and google_callback.php are symmetric).
       Without this, window.LH_USER_ID is never defined and the widget's
       /predict call always sends user_id: null, no matter how you signed in. */
    if (session_status() === PHP_SESSION_NONE) session_start();
    ?>
    <script>window.LH_USER_ID = <?= json_encode($_SESSION['user']['id'] ?? null) ?>;</script>

    <?php require_once __DIR__ . "/../chatbot-deployment/static/index.php"; ?>
</footer>