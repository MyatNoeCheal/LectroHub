<?php
/*
 * signin.php — Shared sign-in page for admin & customer
 * Place in:  /LectroHub/auth/signin.php
 */
if (session_status() === PHP_SESSION_NONE) session_start();

/* If already logged in, redirect immediately */
if (!empty($_SESSION['user']['role'])) {
    if ($_SESSION['user']['role'] === 'admin') {
        header("Location: /LectroHub/admin/index.php"); exit;
    } else {
        header("Location: /LectroHub/customer/index.php"); exit;
    }
}

$error_map = [
    'invalid'         => 'Incorrect email or password.',
    'missing'         => 'Please enter your email and password.',
    'server'          => 'A server error occurred. Please try again.',
    'oauth_state'     => 'Your sign-in session expired. Please try again.',
    'oauth_cancelled' => 'Google sign-in was cancelled.',
    'oauth_failed'    => 'Could not sign in with Google. Please try again.',
];
$error_msg = $error_map[$_GET['error'] ?? ''] ?? '';

/* Where to go after login (e.g. ?redirect=cart) */
$redirect = $_GET['redirect'] ?? '';

$prefill = $_COOKIE['lh_email'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Sign In — LectroHub</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
        href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=Inter:wght@300;400;500;600&display=swap"
        rel="stylesheet" />
    <link href="auth_style.css" rel="stylesheet">
</head>

<body>
    <div class="page">

        <!-- ══ Brand Panel ══ -->
        <aside class="brand-panel">
            <div class="grid-lines"></div>

            <a href="/" class="brand-logo">
                <div class="logo-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="#000" stroke-width="2.2" stroke-linecap="round"
                        stroke-linejoin="round">
                        <path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z" />
                    </svg>
                </div>
                LectroHub
            </a>

            <div class="brand-content">
                <div class="brand-tag"><span></span> Welcome back</div>
                <h2 class="brand-headline">Sign back in<br />to your <em>tech hub.</em></h2>
                <p class="brand-desc">
                    Your orders, wishlist, exclusive deals and 2 million fellow
                    tech enthusiasts are waiting for you.
                </p>
                <div class="features">
                    <div class="feature">
                        <div class="feature-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="var(--accent)" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round">
                                <path d="M20 7H4a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2z" />
                                <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16" />
                            </svg>
                        </div>
                        <div class="feature-text">
                            <strong>Member Deals</strong>
                            <span>Up to 40% off — only visible when logged in</span>
                        </div>
                    </div>
                    <div class="feature">
                        <div class="feature-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="var(--accent2)" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round">
                                <path d="M5 12h14M12 5l7 7-7 7" />
                            </svg>
                        </div>
                        <div class="feature-text">
                            <strong>Live Order Tracking</strong>
                            <span>Real-time updates from warehouse to door</span>
                        </div>
                    </div>
                    <div class="feature">
                        <div class="feature-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="var(--accent)" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round">
                                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
                            </svg>
                        </div>
                        <div class="feature-text">
                            <strong>Secure &amp; Private</strong>
                            <span>PCI-DSS certified, 2FA available on your account</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="brand-footer">© 2026 LectroHub. All rights reserved.</div>
        </aside>

        <!-- ══ Form Panel ══ -->
        <main class="form-panel">

            <div class="form-header">
                <h1>Sign In</h1>
                <p>Don't have an account?
                    <a href="signup.php<?= $redirect ? '?redirect=' . urlencode($redirect) : '' ?>">Create one free
                        →</a>
                </p>
            </div>

            <?php if ($redirect === 'cart'): ?>
            <div class="alert-info"
                style="display:flex;align-items:center;gap:10px;background:var(--surface-1);border:1px solid var(--border-accent);border-radius:8px;padding:12px 16px;margin-bottom:20px;font-size:13px;color:var(--text-secondary)">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                    stroke-linejoin="round" width="16" height="16">
                    <path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z" />
                    <line x1="3" y1="6" x2="21" y2="6" />
                    <path d="M16 10a4 4 0 0 1-8 0" />
                </svg>
                Sign in to complete your purchase. Your cart will be waiting.
            </div>
            <?php endif; ?>

            <?php if ($error_msg): ?>
            <div class="alert-error">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                    stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10" />
                    <line x1="12" y1="8" x2="12" y2="12" />
                    <line x1="12" y1="16" x2="12.01" y2="16" />
                </svg>
                <?= htmlspecialchars($error_msg) ?>
            </div>
            <?php endif; ?>
            <div class="oauth-row">
                <a href="google_login.php<?= $redirect ? '?redirect=' . urlencode($redirect) : '' ?>" class="oauth-btn">
                    <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path
                            d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"
                            fill="#4285F4" />
                        <path
                            d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"
                            fill="#34A853" />
                        <path
                            d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l3.66-2.84z"
                            fill="#FBBC05" />
                        <path
                            d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"
                            fill="#EA4335" />
                    </svg>
                    Google
                </a>
                <a href="#" class="oauth-btn">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                        stroke-linejoin="round">
                        <path d="M4 6h16v12H4z" />
                        <path d="M4 7l8 6 8-6" />
                    </svg>
                    Email
                </a>
            </div>

            <div class="divider">or sign in with email</div>

            <!--
        Pass ?redirect= to auth.php via query string so it can route correctly after login.
      -->
            <form method="POST" action="auth.php<?= $redirect ? '?redirect=' . urlencode($redirect) : '' ?>" novalidate
                onsubmit="handleSubmit(this)">

                <!-- Email -->
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <div class="input-wrap">
                        <span class="input-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round">
                                <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z" />
                                <polyline points="22,6 12,13 2,6" />
                            </svg>
                        </span>
                        <input type="email" id="email" name="email" placeholder="john@example.com"
                            value="<?= htmlspecialchars($prefill) ?>" autocomplete="email"
                            class="<?= $error_msg ? 'error' : '' ?>" />
                    </div>
                </div>

                <!-- Password -->
                <div class="form-group">
                    <div class="label-row">
                        <label for="password">Password</label>
                        <a href="forgot-password.php">Forgot password?</a>
                    </div>
                    <div class="input-wrap">
                        <span class="input-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="11" width="18" height="11" rx="2" ry="2" />
                                <path d="M7 11V7a5 5 0 0 1 10 0v4" />
                            </svg>
                        </span>
                        <input type="password" id="password" name="password" placeholder="Your password"
                            autocomplete="current-password" class="<?= $error_msg ? 'error' : '' ?>" />
                        <button type="button" class="toggle-pw" onclick="togglePw('password', this)"
                            aria-label="Toggle password">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                                <circle cx="12" cy="12" r="3" />
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Remember me -->
                <div class="checkbox-row">
                    <input type="checkbox" id="remember" name="remember" <?= $prefill ? 'checked' : '' ?> />
                    <label for="remember">Keep me signed in for 30 days</label>
                </div>

                <!-- Submit -->
                <button type="submit" class="btn-submit">
                    <span class="btn-label" style="display:flex;align-items:center;gap:8px;">
                        Sign In
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"
                            stroke-linecap="round" stroke-linejoin="round">
                            <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4" />
                            <polyline points="10 17 15 12 10 7" />
                            <line x1="15" y1="12" x2="3" y2="12" />
                        </svg>
                    </span>
                    <div class="spinner"></div>
                </button>

            </form>

            <div class="signup-link">
                New to LectroHub?
                <a href="signup.php<?= $redirect ? '?redirect=' . urlencode($redirect) : '' ?>">Create a free
                    account</a>
            </div>

        </main>
    </div>

    <script>
    function togglePw(id, btn) {
        const inp = document.getElementById(id);
        const isText = inp.type === 'text';
        inp.type = isText ? 'password' : 'text';
        btn.querySelector('svg').style.opacity = isText ? '1' : '.45';
    }

    function handleSubmit(form) {
        const btn = form.querySelector('.btn-submit');
        btn.classList.add('loading');
        btn.disabled = true;
        setTimeout(() => {
            btn.classList.remove('loading');
            btn.disabled = false;
        }, 8000);
    }
    </script>
</body>

</html>