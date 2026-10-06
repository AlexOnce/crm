<!DOCTYPE html>
<html lang="es" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>TAMep CRM - Panel de Atención al Cliente</title>
    
    <!-- PWA & Mobile Meta Tags -->
    <link rel="manifest" href="/manifest.webmanifest">
    <meta name="theme-color" content="#020617">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="TAMep CRM">
    <link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="192x192" href="/icons/icon-192x192.png">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Anti-FOUC: Aplicar clase dark/light antes del primer renderizado -->
    <script>
        (function() {
            try {
                var savedTheme = localStorage.getItem('crm_theme') || 'dark';
                var isDark = savedTheme === 'dark';
                if (savedTheme === 'system') {
                    isDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
                }
                var root = document.documentElement;
                if (isDark) {
                    root.classList.add('dark');
                    root.classList.remove('light');
                    root.setAttribute('data-theme', 'dark');
                } else {
                    root.classList.remove('dark');
                    root.classList.add('light');
                    root.setAttribute('data-theme', 'light');
                }
            } catch (e) {}
        })();
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-50 text-slate-800 dark:bg-slate-950 dark:text-slate-100 antialiased selection:bg-sky-500 selection:text-white">
    <div id="app"></div>
</body>
</html>
