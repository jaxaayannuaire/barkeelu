<!DOCTYPE html>
<html lang="fr">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="@yield('description', 'Barkeelu, plateforme solidaire pour le Sénégal et sa diaspora.')">
        <title>@yield('title', 'Barkeelu')</title>
        <link rel="icon" type="image/png" href="{{ asset('images/brand/barkeelu-square.png') }}">
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif
    </head>
    <body>
        <a class="sr-only fixed left-4 top-4 z-50 rounded-[10px] bg-brand-primary px-4 py-3 text-sm font-semibold text-white focus:not-sr-only focus:outline-none focus:ring-4 focus:ring-brand-secondary" href="#main-content">Aller au contenu</a>
        @include('partials.public-header')
        <main id="main-content" tabindex="-1">@yield('content')</main>
        @include('partials.public-footer')
    </body>
</html>
