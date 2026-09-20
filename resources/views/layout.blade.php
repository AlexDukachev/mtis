<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#16866a">
    <title>MTIS | Командная работа</title>
    <script>
        try {
            const t = localStorage.getItem('mtis.theme') || 'system';
            document.documentElement.dataset.theme = t === 'dark' || (t === 'system' && matchMedia(
                '(prefers-color-scheme: dark)').matches) ? 'dark' : 'light';
        } catch {}
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body>
    @yield('content')
</body>

</html>
