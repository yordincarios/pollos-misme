<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark h-full bg-slate-100">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#0b1220">
        <title inertia>{{ config('app.name', 'Pollos Misme') }}</title>
        <link rel="icon" type="image/png" href="/images/favicon-pollos-misme.png">
        <link rel="apple-touch-icon" href="/images/favicon-pollos-misme.png">
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,700;0,9..144,900;1,9..144,900&display=swap" rel="stylesheet">
        @vite(['resources/css/app.css', 'resources/js/aplicacion.js'])
        @inertiaHead
    </head>
    <body class="h-full font-sans antialiased text-slate-800 bg-slate-100 selection:bg-primary-600 selection:text-white">
        @inertia
    </body>
</html>
