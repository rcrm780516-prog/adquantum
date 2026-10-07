@extends('layouts.panel')
@section('title', 'Inicio')

@section('content')
<h1 class="text-2xl font-bold">Hola, {{ $doctor->displayName() }}</h1>

@unless ($doctor->hasActivePlan())
    <div class="mt-4 rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm">
        <p class="font-medium">Tu perfil todavía no está publicado.</p>
        <p class="mt-1">Activa tu plan Básico ($500 al año) para aparecer en el directorio y recibir citas.</p>
        <a href="{{ route('panel.plans') }}" class="btn-primary mt-3">Activar mi plan</a>
    </div>
@else
    <p class="text-slate-600 mt-1">Plan {{ $doctor->plan->name }} · vigente hasta {{ $doctor->plan_expires_at?->format('d/m/Y') ?? 'sin vencimiento' }} ·
        <a href="{{ route('doctors.show', $doctor) }}" class="text-brand-700 underline" target="_blank">ver mi perfil público</a></p>
@endunless

<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-6">
    <div class="card"><p class="text-sm text-slate-500">Citas este mes</p><p class="text-3xl font-bold">{{ $stats['citas_mes'] }}</p></div>
    <div class="card"><p class="text-sm text-slate-500">Reseñas</p><p class="text-3xl font-bold">{{ $stats['resenas'] }}</p></div>
    <div class="card"><p class="text-sm text-slate-500">Calificación</p><p class="text-3xl font-bold">{{ number_format($stats['calificacion'], 1) }}</p></div>
    <div class="card"><p class="text-sm text-slate-500">Pacientes enviados a Google</p><p class="text-3xl font-bold">{{ $stats['clics_google'] }}</p></div>
</div>

<div class="grid md:grid-cols-2 gap-4 mt-6">
    <div class="card">
        <h2 class="font-semibold">Salud de tu ficha de Google</h2>
        <div class="mt-3 h-3 rounded-full bg-slate-100 overflow-hidden"><div class="h-full {{ $gbpScore >= 70 ? 'bg-brand-500' : 'bg-amber-500' }}" style="width: {{ $gbpScore }}%"></div></div>
        <p class="text-sm mt-2"><strong>{{ $gbpScore }}/100</strong>. Completa tu perfil (foto, horario, servicios, sitio web y el Place ID de Google) para subir.</p>
        <a href="{{ route('panel.profile') }}" class="btn-secondary mt-3">Completar perfil</a>
    </div>
    <div class="card">
        <h2 class="font-semibold">Próximas citas</h2>
        <ul class="mt-2 divide-y divide-slate-100 text-sm">
            @forelse ($upcoming as $a)
                <li class="py-2 flex justify-between"><span>{{ $a->patient_name }}</span><span class="text-slate-500">{{ $a->starts_at->locale('es')->isoFormat('ddd D MMM, HH:mm') }}</span></li>
            @empty
                <li class="py-2 text-slate-500">Sin citas próximas.</li>
            @endforelse
        </ul>
    </div>
</div>

<div class="mt-6 grid md:grid-cols-2 gap-4">
    <div class="card"><p class="text-sm text-slate-500">Consultas de IA disponibles este mes</p><p class="text-3xl font-bold">{{ $aiRemaining }}</p>
        <a href="{{ route('panel.studio') }}" class="text-brand-700 text-sm underline">Crear un anuncio</a></div>
    @include('partials.upsell', ['source' => 'dashboard'])
</div>
@endsection
