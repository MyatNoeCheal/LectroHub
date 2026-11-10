<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once "../lectrohub_db/connection.php";
require_once __DIR__ . "/lang/translate.php";

$cart_count = isset($_SESSION['cart']) ? array_sum($_SESSION['cart']) : 0;
$user_name  = $_SESSION['user']['full_name'] ?? null;
?>

<?php require_once "head.php" ?>
<?php require_once "navbar.php" ?>

<body>

  <!-- ── HERO ── -->
  <div class="faq-hero">
    <h1><?= htmlspecialchars(t('faq_title_1')) ?><br><span><?= htmlspecialchars(t('faq_title_2')) ?></span></h1>
    <p><?= htmlspecialchars(t('faq_hero_sub')) ?></p>

  </div>

  <!-- ── MAIN LAYOUT ── -->
  <div class="faq-layout">

    <!-- Sidebar -->
    <aside class="faq-sidebar">
      <div class="faq-sidebar-label"><?= htmlspecialchars(t('jump_to')) ?></div>
      <a href="#orders"   class="faq-sidebar-link active"><span class="cat-emoji">📦</span> <?= htmlspecialchars(t('cat_orders')) ?></a>
      <a href="#shipping" class="faq-sidebar-link"><span class="cat-emoji">🚀</span> <?= htmlspecialchars(t('cat_shipping')) ?></a>
      <a href="#returns"  class="faq-sidebar-link"><span class="cat-emoji">🔄</span> <?= htmlspecialchars(t('cat_returns')) ?></a>
      <a href="#payments" class="faq-sidebar-link"><span class="cat-emoji">💳</span> <?= htmlspecialchars(t('cat_payments')) ?></a>
      <a href="#warranty" class="faq-sidebar-link"><span class="cat-emoji">🛡️</span> <?= htmlspecialchars(t('cat_warranty')) ?></a>
      <a href="#account"  class="faq-sidebar-link"><span class="cat-emoji">👤</span> <?= htmlspecialchars(t('cat_account')) ?></a>
      <a href="#products" class="faq-sidebar-link"><span class="cat-emoji">📱</span> <?= htmlspecialchars(t('cat_products')) ?></a>
    </aside>

    <!-- FAQ groups -->
    <div class="faq-content" id="faqContent">

      <!-- No results -->
      <div class="faq-no-results" id="noResults">
        <div class="nr-icon">🔍</div>
        <h3><?= htmlspecialchars(t('no_results_found')) ?></h3>
        <p><?= htmlspecialchars(t('no_results_sub')) ?></p>
      </div>

      <!-- ORDERS -->
      <div class="faq-group" id="orders" data-label="<?= htmlspecialchars(t('cat_orders')) ?>">
        <div class="faq-group-title"><span>📦</span> <?= htmlspecialchars(t('cat_orders')) ?></div>

        <div class="faq-item">
          <div class="faq-question" onclick="toggleFaq(this)">
            <span class="faq-q-text"><?= htmlspecialchars(t('faq_o1_q')) ?></span>
            <span class="faq-chevron"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="m6 9 6 6 6-6"/></svg></span>
          </div>
          <div class="faq-answer">
            <div class="faq-answer-inner">
              <?= t('faq_o1_a') ?>
            </div>
          </div>
        </div>

        <div class="faq-item">
          <div class="faq-question" onclick="toggleFaq(this)">
            <span class="faq-q-text"><?= htmlspecialchars(t('faq_o2_q')) ?></span>
            <span class="faq-chevron"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="m6 9 6 6 6-6"/></svg></span>
          </div>
          <div class="faq-answer">
            <div class="faq-answer-inner">
              <?= t('faq_o2_a') ?>
            </div>
          </div>
        </div>

        <div class="faq-item">
          <div class="faq-question" onclick="toggleFaq(this)">
            <span class="faq-q-text"><?= htmlspecialchars(t('faq_o3_q')) ?></span>
            <span class="faq-chevron"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="m6 9 6 6 6-6"/></svg></span>
          </div>
          <div class="faq-answer">
            <div class="faq-answer-inner">
              <?= t('faq_o3_a') ?>
            </div>
          </div>
        </div>

        <div class="faq-item">
          <div class="faq-question" onclick="toggleFaq(this)">
            <span class="faq-q-text"><?= htmlspecialchars(t('faq_o4_q')) ?></span>
            <span class="faq-chevron"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="m6 9 6 6 6-6"/></svg></span>
          </div>
          <div class="faq-answer">
            <div class="faq-answer-inner">
              <?= t('faq_o4_a') ?>
            </div>
          </div>
        </div>
      </div>

      <!-- SHIPPING -->
      <div class="faq-group" id="shipping" data-label="<?= htmlspecialchars(t('cat_shipping')) ?>">
        <div class="faq-group-title"><span>🚀</span> <?= htmlspecialchars(t('cat_shipping')) ?></div>

        <div class="faq-item">
          <div class="faq-question" onclick="toggleFaq(this)">
            <span class="faq-q-text"><?= htmlspecialchars(t('faq_s1_q')) ?></span>
            <span class="faq-chevron"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="m6 9 6 6 6-6"/></svg></span>
          </div>
          <div class="faq-answer">
            <div class="faq-answer-inner">
              <?= t('faq_s1_a') ?>
            </div>
          </div>
        </div>

        <div class="faq-item">
          <div class="faq-question" onclick="toggleFaq(this)">
            <span class="faq-q-text"><?= htmlspecialchars(t('faq_s2_q')) ?></span>
            <span class="faq-chevron"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="m6 9 6 6 6-6"/></svg></span>
          </div>
          <div class="faq-answer">
            <div class="faq-answer-inner">
              <?= t('faq_s2_a') ?>
            </div>
          </div>
        </div>

        <div class="faq-item">
          <div class="faq-question" onclick="toggleFaq(this)">
            <span class="faq-q-text"><?= htmlspecialchars(t('faq_s3_q')) ?></span>
            <span class="faq-chevron"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="m6 9 6 6 6-6"/></svg></span>
          </div>
          <div class="faq-answer">
            <div class="faq-answer-inner">
              <?= t('faq_s3_a') ?>
            </div>
          </div>
        </div>

        <div class="faq-item">
          <div class="faq-question" onclick="toggleFaq(this)">
            <span class="faq-q-text"><?= htmlspecialchars(t('faq_s4_q')) ?></span>
            <span class="faq-chevron"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="m6 9 6 6 6-6"/></svg></span>
          </div>
          <div class="faq-answer">
            <div class="faq-answer-inner">
              <?= t('faq_s4_a') ?>
            </div>
          </div>
        </div>
      </div>

      <!-- RETURNS -->
      <div class="faq-group" id="returns" data-label="<?= htmlspecialchars(t('cat_returns')) ?>">
        <div class="faq-group-title"><span>🔄</span> <?= htmlspecialchars(t('cat_returns')) ?></div>

        <div class="faq-item">
          <div class="faq-question" onclick="toggleFaq(this)">
            <span class="faq-q-text"><?= htmlspecialchars(t('faq_r1_q')) ?></span>
            <span class="faq-chevron"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="m6 9 6 6 6-6"/></svg></span>
          </div>
          <div class="faq-answer">
            <div class="faq-answer-inner">
              <?= t('faq_r1_a') ?>
            </div>
          </div>
        </div>

        <div class="faq-item">
          <div class="faq-question" onclick="toggleFaq(this)">
            <span class="faq-q-text"><?= htmlspecialchars(t('faq_r2_q')) ?></span>
            <span class="faq-chevron"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="m6 9 6 6 6-6"/></svg></span>
          </div>
          <div class="faq-answer">
            <div class="faq-answer-inner">
              <?= t('faq_r2_a') ?>
            </div>
          </div>
        </div>

        <div class="faq-item">
          <div class="faq-question" onclick="toggleFaq(this)">
            <span class="faq-q-text"><?= htmlspecialchars(t('faq_r3_q')) ?></span>
            <span class="faq-chevron"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="m6 9 6 6 6-6"/></svg></span>
          </div>
          <div class="faq-answer">
            <div class="faq-answer-inner">
              <?= t('faq_r3_a') ?>
            </div>
          </div>
        </div>
      </div>

      <!-- PAYMENTS -->
      <div class="faq-group" id="payments" data-label="<?= htmlspecialchars(t('cat_payments')) ?>">
        <div class="faq-group-title"><span>💳</span> <?= htmlspecialchars(t('cat_payments')) ?></div>

        <div class="faq-item">
          <div class="faq-question" onclick="toggleFaq(this)">
            <span class="faq-q-text"><?= htmlspecialchars(t('faq_p1_q')) ?></span>
            <span class="faq-chevron"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="m6 9 6 6 6-6"/></svg></span>
          </div>
          <div class="faq-answer">
            <div class="faq-answer-inner">
              <?= t('faq_p1_a') ?>
            </div>
          </div>
        </div>

        <div class="faq-item">
          <div class="faq-question" onclick="toggleFaq(this)">
            <span class="faq-q-text"><?= htmlspecialchars(t('faq_p2_q')) ?></span>
            <span class="faq-chevron"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="m6 9 6 6 6-6"/></svg></span>
          </div>
          <div class="faq-answer">
            <div class="faq-answer-inner">
              <?= t('faq_p2_a') ?>
            </div>
          </div>
        </div>

        <div class="faq-item">
          <div class="faq-question" onclick="toggleFaq(this)">
            <span class="faq-q-text"><?= htmlspecialchars(t('faq_p3_q')) ?></span>
            <span class="faq-chevron"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="m6 9 6 6 6-6"/></svg></span>
          </div>
          <div class="faq-answer">
            <div class="faq-answer-inner">
              <?= t('faq_p3_a') ?>
            </div>
          </div>
        </div>
      </div>

      <!-- WARRANTY -->
      <div class="faq-group" id="warranty" data-label="<?= htmlspecialchars(t('cat_warranty')) ?>">
        <div class="faq-group-title"><span>🛡️</span> <?= htmlspecialchars(t('cat_warranty')) ?></div>

        <div class="faq-item">
          <div class="faq-question" onclick="toggleFaq(this)">
            <span class="faq-q-text"><?= htmlspecialchars(t('faq_w1_q')) ?></span>
            <span class="faq-chevron"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="m6 9 6 6 6-6"/></svg></span>
          </div>
          <div class="faq-answer">
            <div class="faq-answer-inner">
              <?= t('faq_w1_a') ?>
            </div>
          </div>
        </div>

        <div class="faq-item">
          <div class="faq-question" onclick="toggleFaq(this)">
            <span class="faq-q-text"><?= htmlspecialchars(t('faq_w2_q')) ?></span>
            <span class="faq-chevron"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="m6 9 6 6 6-6"/></svg></span>
          </div>
          <div class="faq-answer">
            <div class="faq-answer-inner">
              <?= t('faq_w2_a') ?>
            </div>
          </div>
        </div>
      </div>

      <!-- ACCOUNT -->
      <div class="faq-group" id="account" data-label="<?= htmlspecialchars(t('cat_account')) ?>">
        <div class="faq-group-title"><span>👤</span> <?= htmlspecialchars(t('cat_account')) ?></div>

        <div class="faq-item">
          <div class="faq-question" onclick="toggleFaq(this)">
            <span class="faq-q-text"><?= htmlspecialchars(t('faq_a1_q')) ?></span>
            <span class="faq-chevron"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="m6 9 6 6 6-6"/></svg></span>
          </div>
          <div class="faq-answer">
            <div class="faq-answer-inner">
              <?= t('faq_a1_a') ?>
            </div>
          </div>
        </div>

        <div class="faq-item">
          <div class="faq-question" onclick="toggleFaq(this)">
            <span class="faq-q-text"><?= htmlspecialchars(t('faq_a2_q')) ?></span>
            <span class="faq-chevron"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="m6 9 6 6 6-6"/></svg></span>
          </div>
          <div class="faq-answer">
            <div class="faq-answer-inner">
              <?= t('faq_a2_a') ?>
            </div>
          </div>
        </div>

        <div class="faq-item">
          <div class="faq-question" onclick="toggleFaq(this)">
            <span class="faq-q-text"><?= htmlspecialchars(t('faq_a3_q')) ?></span>
            <span class="faq-chevron"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="m6 9 6 6 6-6"/></svg></span>
          </div>
          <div class="faq-answer">
            <div class="faq-answer-inner">
              <?= t('faq_a3_a') ?>
            </div>
          </div>
        </div>
      </div>

      <!-- PRODUCTS -->
      <div class="faq-group" id="products" data-label="<?= htmlspecialchars(t('cat_products')) ?>">
        <div class="faq-group-title"><span>📱</span> <?= htmlspecialchars(t('cat_products')) ?></div>

        <div class="faq-item">
          <div class="faq-question" onclick="toggleFaq(this)">
            <span class="faq-q-text"><?= htmlspecialchars(t('faq_pr1_q')) ?></span>
            <span class="faq-chevron"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="m6 9 6 6 6-6"/></svg></span>
          </div>
          <div class="faq-answer">
            <div class="faq-answer-inner">
              <?= t('faq_pr1_a') ?>
            </div>
          </div>
        </div>

        <div class="faq-item">
          <div class="faq-question" onclick="toggleFaq(this)">
            <span class="faq-q-text"><?= htmlspecialchars(t('faq_pr2_q')) ?></span>
            <span class="faq-chevron"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="m6 9 6 6 6-6"/></svg></span>
          </div>
          <div class="faq-answer">
            <div class="faq-answer-inner">
              <?= t('faq_pr2_a') ?>
            </div>
          </div>
        </div>

        <div class="faq-item">
          <div class="faq-question" onclick="toggleFaq(this)">
            <span class="faq-q-text"><?= htmlspecialchars(t('faq_pr3_q')) ?></span>
            <span class="faq-chevron"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="m6 9 6 6 6-6"/></svg></span>
          </div>
          <div class="faq-answer">
            <div class="faq-answer-inner">
              <?= t('faq_pr3_a') ?>
            </div>
          </div>
        </div>
      </div>

    </div><!-- /faq-content -->
  </div><!-- /faq-layout -->

  <!-- ── CONTACT CTA ── -->
  <div class="faq-contact">
    <h2><?= htmlspecialchars(t('faq_still_questions')) ?></h2>
    <p><?= htmlspecialchars(t('faq_contact_sub')) ?></p>
    <div class="faq-contact-btns">
      <a href="mailto:support@lectrohub.com" class="btn btn-primary">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-10 7L2 7"/></svg>
        <?= htmlspecialchars(t('email_support')) ?>
      </a>
      <a href="#" class="btn btn-secondary">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
        <?= htmlspecialchars(t('live_chat')) ?>
      </a>
    </div>
  </div>

  <?php require_once "footer.php" ?>

  <!-- ── TOAST ── -->
  <div class="toast" id="toast">
    <span class="toast-icon" id="toastIcon">✓</span>
    <span id="toastMsg"><?= htmlspecialchars(t('toast_done')) ?></span>
  </div>

</body>
</html>