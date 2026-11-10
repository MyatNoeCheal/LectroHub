<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <meta name="robots" content="noindex,nofollow">

    <!-- FIX: Theme must be set BEFORE any CSS paints, or the page
         flashes the wrong theme for a frame. Reads the same
         'lh-theme' key used site-wide (customer + admin). -->
    <script>
    (function() {
        try {
            var t = localStorage.getItem('lh-theme');
            if (!t) {
                t = (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) ?
                    'dark' : 'light';
            }
            document.documentElement.setAttribute('data-theme', t);
        } catch (e) {}
    })();
    </script>

    <!-- FIX: corrected typo "Custome" → "Customer" -->
    <title>LectroHub - Customer</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600&family=JetBrains+Mono:wght@400;500&display=swap"
        rel="stylesheet">

    <!--Bootstrap CSS-->
    <!-- FIX: must load BEFORE light-mode.css/dark-mode.css. Bootstrap 5.3
         sets `body { background-color: var(--bs-body-bg); color: var(--bs-body-color); }`
         which has the exact same specificity as our own `body { background: var(--bg); }`
         rule. With equal specificity, whichever stylesheet is linked LAST wins ties —
         Bootstrap was loading after our theme CSS, so its white #fff body background
         silently beat our dark-mode override every time, no matter what --bg resolved
         to. That's why the navbar and individual dark-themed cards (which use their own
         classes Bootstrap doesn't touch) looked correct, while the page canvas behind
         them stayed white. Loading Bootstrap first means our theme CSS (linked after)
         always wins any tie. -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css"
        integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous">
    </script>

    <!-- Stylesheets (loaded AFTER Bootstrap — see note above) -->
<?php $lh_css_v = filemtime(__DIR__ . '/css/light-mode.css') ?: time(); ?>
<link rel="stylesheet" href="css/light-mode.css?v=<?= $lh_css_v ?>">
<link rel="stylesheet" href="css/dark-mode.css?v=<?= @filemtime(__DIR__ . '/css/dark-mode.css') ?: $lh_css_v ?>">
<link rel="stylesheet" href="css/animations.css?v=<?= @filemtime(__DIR__ . '/css/animations.css') ?: $lh_css_v ?>">

    <script src="/LectroHub/customer/js/theme-toggle.js" defer></script>
    <script src="/LectroHub/customer/js/main.js" defer></script>
    <script src="/LectroHub/customer/js/animations.js" defer></script>

    <!-- FIX: three.min.js MUST load before circuit-hero.js.
         Scripts marked `defer` execute in the order they appear in the
         document, not in the order their downloads finish. It was
         previously listed AFTER circuit-hero.js, so `THREE` was undefined
         the moment circuit-hero.js ran and the hero canvas silently
         failed to render. -->
    <script src="https://cdn.jsdelivr.net/npm/three@0.160.0/build/three.min.js" defer></script>
    <script src="/LectroHub/customer/js/circuit-hero.js" defer></script>

</head>