<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-slate-100">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title inertia>{{ config('app.name', 'Pollos Misme') }}</title>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,700;0,9..144,900;1,9..144,900&display=swap" rel="stylesheet">
        <script>
            try {
                if (localStorage.getItem('pollos-misme-theme') === 'dark') {
                    document.documentElement.classList.add('dark');
                }
            } catch (error) {
                // The theme can still be toggled for this session without local storage.
            }
        </script>
        @vite(['resources/css/app.css', 'resources/js/aplicacion.js'])
        @inertiaHead
    </head>
    <body class="h-full font-sans antialiased text-slate-800 bg-slate-100 selection:bg-primary-600 selection:text-white">
        @inertia
    </body>
</html>
