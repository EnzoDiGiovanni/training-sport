<!DOCTYPE html>
<html lang="fr">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="theme-color" content="#ffffff">
        {{-- Thème clair par défaut ; le sombre reste disponible via le bouton (clé de @vueuse/core). --}}
        <script>
            try {
                const m = localStorage.getItem('vueuse-color-scheme') ?? 'light';
                localStorage.setItem('vueuse-color-scheme', m);
                if (m === 'dark' || (m === 'auto' && matchMedia('(prefers-color-scheme: dark)').matches)) document.documentElement.classList.add('dark');
            } catch {}
        </script>
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        @vite(['resources/css/app.css', 'resources/js/app.ts', "resources/js/pages/{$page['component']}.vue"])
        <x-inertia::head>
            <title>Recompo</title>
        </x-inertia::head>
    </head>
    <body>
        <x-inertia::app />
    </body>
</html>
