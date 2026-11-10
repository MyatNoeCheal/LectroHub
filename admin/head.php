<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,nofollow">

    <!-- FIX: Theme must be set BEFORE any CSS paints. Reads the same
         'lh-theme' key used on the customer side, so switching on
         either side keeps them in sync. -->
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

    <title><?= isset($page_title) ? htmlspecialchars($page_title) : 'Dashboard — LectroHub Admin' ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">

    <!-- FIX: Dark/light theme overrides — load after style.css -->
    <link rel="stylesheet" href="css/dark-mode.css">

    <link rel="stylesheet" href="css/light-mode.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
    <script src="js/chart.min.js"></script>
    <!-- FIX: Theme toggle logic (shared 'lh-theme' key across admin + customer) -->
    <script src="/LectroHub/admin/js/theme-toggle.js" defer></script>
</head>

<body></body>