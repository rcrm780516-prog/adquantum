<!doctype html>
<html lang="es-MX">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', config('plataforma.nombre').' · '.config('plataforma.eslogan'))</title>
    <meta name="description" content="@yield('description', 'Encuentra médicos verificados, lee reseñas reales y agenda tu cita en línea.')">
    <link rel="canonical" href="@yield('canonical', url()->current())">
    <meta property="og:title" content="@yield('title', config('plataforma.nombre'))">
    <meta property="og:description" content="@yield('description', config('plataforma.eslogan'))">
    <meta property="og:locale" content="es_MX">
    @stack('head')
    @include('partials.styles')
</head>
<body class="bg-slate-50 text-slate-800 antialiased">
<header class="bg-white border-b border-slate-200">
    <div class="max-w-6xl mx-auto px-4 h-16 flex items-center justify-between">
        <a href="{{ route('home') }}" class="font-bold text-lg text-brand-700">{{ config('plataforma.nombre') }}</a>
        <nav class="flex items-center gap-4 text-sm">
            <a href="{{ route('for-doctors') }}" class="hover:text-brand-700">¿Eres médico?</a>
            @auth
                <a href="{{ route('panel.dashboard') }}" class="btn-primary">Mi panel</a>
            @else
                <a href="{{ route('login') }}" class="hover:text-brand-700">Entrar</a>
            @endauth
        </nav>
    </div>
</header>

<main>
    @include('partials.flash')
    @yield('content')
</main>

<footer class="mt-16 border-t border-slate-200 bg-white">
    <div class="max-w-6xl mx-auto px-4 py-8 text-sm text-slate-500 flex flex-col md:flex-row gap-4 justify-between">
        <p>© {{ date('Y') }} {{ config('plataforma.nombre') }}. Una plataforma de Virtuoso Growth Marketing.</p>
        <p class="flex gap-4">
            <a href="{{ route('privacy') }}" class="hover:underline">Aviso de privacidad</a>
            <a href="{{ route('for-doctors') }}" class="hover:underline">Para médicos</a>
        </p>
    </div>
    <p class="max-w-6xl mx-auto px-4 pb-6 text-xs text-slate-400">
        La información de este sitio es proporcionada por cada profesional y no sustituye una consulta médica.
    </p>
</footer>
</body>
</html>
