<?php

if (session_status() === PHP_SESSION_NONE) session_start();

require_once '../lectrohub_db/connection.php';

$errors    = [];
$success   = false;
$full_name = '';

/* Where to redirect after signup (e.g. ?redirect=cart) */
$redirect = $_GET['redirect'] ?? $_POST['redirect'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $email     = trim($_POST['email']     ?? '');
    $password  = $_POST['password']       ?? '';
    $confirm   = $_POST['confirm_password'] ?? '';
    $phone     = trim($_POST['phone']     ?? '');
    $agree     = isset($_POST['agree']);

    if (empty($full_name))                          $errors['full_name'] = 'Full name is required.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email']     = 'Enter a valid email address.';
    if (strlen($password) < 8)                      $errors['password']  = 'Password must be at least 8 characters.';
    if ($password !== $confirm)                     $errors['confirm']   = 'Passwords do not match.';
    if (!$agree)                                    $errors['agree']     = 'You must accept the terms.';

    if (empty($errors)) {

        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);

        if ($stmt->rowCount() > 0) {
            $errors['email'] = 'Email already exists.';
        } else {

            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $conn->prepare(
                "INSERT INTO users (full_name, email, password, phone, role)
                 VALUES (?, ?, ?, ?, 'user')"
            );
            $stmt->execute([$full_name, $email, $hashedPassword, $phone]);
            $new_user_id = $conn->lastInsertId();

            /* ── Save guest cart & wishlist before touching session ── */
            $guest_cart     = $_SESSION['cart']     ?? [];
            $guest_wishlist = $_SESSION['wishlist'] ?? [];

            session_regenerate_id(true);

            /* Log the new user in immediately */
            $_SESSION['user'] = [
                'id'        => $new_user_id,
                'full_name' => $full_name,
                'email'     => $email,
                'role'      => 'user',
            ];

            /* ── Carry guest cart into the new session ── */
            /* ── Carry guest wishlist into session ── */

            $_SESSION['cart'] = $guest_cart;
            $_SESSION['wishlist'] = array_values(array_unique($guest_wishlist));

            /* ── Sync guest wishlist to DB for the new user ── */
            if (!empty($guest_wishlist)) {
                $wl_stmt = $conn->prepare(
                    "INSERT IGNORE INTO wishlist (user_id, product_id) VALUES (?, ?)"
                );
                foreach ($guest_wishlist as $product_id) {
                    $wl_stmt->execute([$new_user_id, $product_id]);
                }
            }

            $success = true;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Create Account — LectroHub</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet" />
  <link href="auth_style.css" rel="stylesheet">
</head>

<body>
  <div class="page">

    <!-- ══ Brand Panel ══ -->
    <aside class="brand-panel">
      <div class="grid-lines"></div>

      <div class="brand-logo">
        <div class="logo-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="#000" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z" />
          </svg>
        </div>
        LectroHub
      </div>

      <div class="brand-content">
        <div class="brand-tag"><span></span> New member perks</div>
        <h2 class="brand-headline">
          The Future of<br /><em>Electronics</em><br />Shopping.
        </h2>
        <p class="brand-desc">
          Join over 2 million tech enthusiasts who discover, compare, and buy the latest gadgets at unbeatable prices.
        </p>
        <div class="features">
          <div class="feature">
            <div class="feature-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="var(--accent)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
              </svg>
            </div>
            <div class="feature-text">
              <strong>Secure Payments</strong>
              <span>PCI-DSS Level 1 certified gateway</span>
            </div>
          </div>
          <div class="feature">
            <div class="feature-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="var(--accent2)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M5 12h14M12 5l7 7-7 7" />
              </svg>
            </div>
            <div class="feature-text">
              <strong>Free Express Delivery</strong>
              <span>Next-day on orders over €49</span>
            </div>
          </div>
          <div class="feature">
            <div class="feature-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="var(--accent)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" />
              </svg>
            </div>
            <div class="feature-text">
              <strong>24/7 Expert Support</strong>
              <span>Real humans, average 2 min response</span>
            </div>
          </div>
        </div>
      </div>

      <div class="brand-footer">© 2026 LectroHub. All rights reserved.</div>
    </aside>

    <!-- ══ Form Panel ══ -->
    <main class="form-panel">

      <?php if ($success): ?>
        <div class="success-box">
          <div class="success-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
              <polyline points="20 6 9 17 4 12" />
            </svg>
          </div>
          <h2>Account Created!</h2>
          <p>
            Welcome to LectroHub, <?= htmlspecialchars($full_name) ?>!
            <?= $redirect === 'cart' ? 'Taking you back to your cart...' : 'Redirecting to Home Page...' ?>
          </p>
        </div>

        <script>
          setTimeout(function () {
            window.location.href = "<?= $redirect === 'cart'
                ? '/LectroHub/customer/cart.php'
                : '/LectroHub/customer/index.php' ?>";
          }, 1500);
        </script>

      <?php else: ?>

        <div class="form-header">
          <h1>Create Account</h1>
          <p>Already have one? <a href="signin.php<?= $redirect ? '?redirect=' . urlencode($redirect) : '' ?>">Sign in →</a></p>
        </div>

        <!-- OAuth -->
        <div class="oauth-row">
  <a href="google_login.php<?= $redirect ? '?redirect=' . urlencode($redirect) : '' ?>" class="oauth-btn">
    <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
      <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
      <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
      <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l3.66-2.84z" fill="#FBBC05"/>
      <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
    </svg>
    Google
  </a>
  <a href="#" class="oauth-btn">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
         stroke-linecap="round" stroke-linejoin="round">
      <path d="M4 6h16v12H4z"/><path d="M4 7l8 6 8-6"/>
    </svg>
    Email
  </a>
</div>

        <div class="divider">or register with email</div>

        <form method="POST" action="" novalidate>
          <!-- Pass redirect through the form -->
          <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect) ?>">

          <div class="form-grid">

            <!-- Full Name -->
            <div class="form-group">
              <label for="full_name">Full Name</label>
              <div class="input-wrap">
                <span class="input-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></span>
                <input type="text" id="full_name" name="full_name" placeholder="John Doe"
                  value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>"
                  class="<?= isset($errors['full_name']) ? 'error' : '' ?>" />
              </div>
              <?php if (!empty($errors['full_name'])): ?>
                <span class="field-error"><?= $errors['full_name'] ?></span>
              <?php endif; ?>
            </div>

            <!-- Phone -->
            <div class="form-group">
              <label for="phone">Phone <span style="color:var(--muted);font-weight:300">(optional)</span></label>
              <div class="input-wrap">
                <span class="input-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.15 12 19.79 19.79 0 0 1 1.08 3.32 2 2 0 0 1 3.05 1h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.09 8.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 21 16z"/></svg></span>
                <input type="tel" id="phone" name="phone" placeholder="+49 123 456 789"
                  value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>" />
              </div>
            </div>

            <!-- Email -->
            <div class="form-group full">
              <label for="email">Email Address</label>
              <div class="input-wrap">
                <span class="input-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg></span>
                <input type="email" id="email" name="email" placeholder="john@example.com"
                  value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                  class="<?= isset($errors['email']) ? 'error' : '' ?>" />
              </div>
              <?php if (!empty($errors['email'])): ?>
                <span class="field-error"><?= $errors['email'] ?></span>
              <?php endif; ?>
            </div>

            <!-- Password -->
            <div class="form-group">
              <label for="password">Password</label>
              <div class="input-wrap">
                <span class="input-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg></span>
                <input type="password" id="password" name="password" placeholder="Min. 8 characters"
                  class="<?= isset($errors['password']) ? 'error' : '' ?>"
                  oninput="checkStrength(this.value)" />
                <button type="button" class="toggle-pw" onclick="togglePw('password',this)" aria-label="Toggle password">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                </button>
              </div>
              <?php if (!empty($errors['password'])): ?>
                <span class="field-error"><?= $errors['password'] ?></span>
              <?php endif; ?>
              <div class="pw-strength">
                <div class="pw-bars">
                  <div class="pw-bar" id="bar1"></div>
                  <div class="pw-bar" id="bar2"></div>
                  <div class="pw-bar" id="bar3"></div>
                  <div class="pw-bar" id="bar4"></div>
                </div>
                <span class="pw-label" id="pw-label">—</span>
              </div>
            </div>

            <!-- Confirm Password -->
            <div class="form-group">
              <label for="confirm_password">Confirm Password</label>
              <div class="input-wrap">
                <span class="input-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></span>
                <input type="password" id="confirm_password" name="confirm_password" placeholder="Repeat password"
                  class="<?= isset($errors['confirm']) ? 'error' : '' ?>" />
                <button type="button" class="toggle-pw" onclick="togglePw('confirm_password',this)" aria-label="Toggle confirm password">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                </button>
              </div>
              <?php if (!empty($errors['confirm'])): ?>
                <span class="field-error"><?= $errors['confirm'] ?></span>
              <?php endif; ?>
            </div>

          </div><!-- /.form-grid -->

          <!-- Terms -->
          <div class="checkbox-row">
            <input type="checkbox" id="agree" name="agree" <?= !empty($_POST['agree']) ? 'checked' : '' ?> />
            <label for="agree">
              I agree to the <a href="#">Terms of Service</a> and <a href="#">Privacy Policy</a>. I may receive order & promotional emails.
            </label>
          </div>
          <?php if (!empty($errors['agree'])): ?>
            <p style="color:var(--error);font-size:.8rem;margin:-16px 0 16px"><?= $errors['agree'] ?></p>
          <?php endif; ?>

          <button type="submit" class="btn-submit">Create My Account</button>
        </form>

      <?php endif; ?>
    </main>
  </div>

  <script>
    function togglePw(id, btn) {
      const inp = document.getElementById(id);
      const isText = inp.type === 'text';
      inp.type = isText ? 'password' : 'text';
      btn.querySelector('svg').style.opacity = isText ? '1' : '.45';
    }

    function checkStrength(val) {
      let score = 0;
      if (val.length >= 8)          score++;
      if (/[A-Z]/.test(val))        score++;
      if (/[0-9]/.test(val))        score++;
      if (/[^A-Za-z0-9]/.test(val)) score++;

      const colors = ['', '#ff4d6d', '#ff9a3c', '#ffd166', '#00e5a0'];
      const labels = ['—', 'Weak', 'Fair', 'Good', 'Strong'];

      [1, 2, 3, 4].forEach(i => {
        document.getElementById('bar' + i).style.background =
          i <= score ? colors[score] : 'var(--border)';
      });

      const lbl = document.getElementById('pw-label');
      lbl.textContent = labels[score];
      lbl.style.color = colors[score] || 'var(--muted)';
    }
  </script>
</body>
</html>