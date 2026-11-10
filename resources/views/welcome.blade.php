<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Pollos Misme</title>
        <link rel="icon" type="image/png" href="/images/favicon-pollos-misme.png">
        <link rel="apple-touch-icon" href="/images/favicon-pollos-misme.png">
        @vite(['resources/css/app.css', 'resources/js/aplicacion.js'])
    </head>
    <body class="antialiased bg-gray-50">
        <div id="app"></div>
    </body>
</html>
