<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-slate-100">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title inertia>{{ config('app.name', 'Restaurante 2 - POS') }}</title>
        <script>
            try {
                if (localStorage.getItem('restaurante2-theme') === 'dark') {
                    document.documentElement.classList.add('dark');
                }
            } catch (error) {
                // The theme can still be toggled for this session without local storage.
            }
        </script>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @inertiaHead
    </head>
    <body class="h-full font-sans antialiased text-slate-800 bg-slate-100 selection:bg-emerald-500 selection:text-white">
        @inertia
    </body>
</html>
