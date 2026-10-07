<!doctype html>
<html lang="es-MX">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Mi panel') · {{ config('plataforma.nombre') }}</title>
    @include('partials.styles')
</head>
<body class="bg-slate-50 text-slate-800 antialiased">
@php
    $nav = [
        'panel.dashboard' => 'Inicio', 'panel.profile' => 'Mi perfil', 'panel.appointments' => 'Citas',
        'panel.reviews' => 'Reseñas', 'panel.studio' => 'Estudio de anuncios', 'panel.advisor' => 'Asesor IA',
        'panel.plans' => 'Mejorar plan',
    ];
@endphp
<div class="md:flex min-h-screen">
    <aside class="md:w-60 bg-white border-b md:border-b-0 md:border-r border-slate-200">
        <div class="px-5 h-16 flex items-center font-bold text-brand-700"><a href="{{ route('home') }}">{{ config('plataforma.nombre') }}</a></div>
        <nav class="px-3 pb-4 flex md:flex-col gap-1 overflow-x-auto text-sm">
            @foreach ($nav as $route => $label)
                <a href="{{ route($route) }}" class="whitespace-nowrap rounded-lg px-3 py-2 {{ request()->routeIs($route) ? 'bg-brand-50 text-brand-700 font-medium' : 'hover:bg-slate-100' }} {{ $route === 'panel.plans' ? 'text-amber-700' : '' }}">{{ $label }}</a>
            @endforeach
            <form method="post" action="{{ route('logout') }}" class="md:mt-4">@csrf<button class="w-full text-left rounded-lg px-3 py-2 text-slate-500 hover:bg-slate-100">Salir</button></form>
        </nav>
    </aside>
    <main class="flex-1 min-w-0">
        @include('partials.flash')
        <div class="max-w-5xl mx-auto px-4 py-8">@yield('content')</div>
    </main>
</div>
@stack('scripts')
</body>
</html>
