@extends('layouts.panel')
@section('title', 'Mi perfil')

@php
    $days = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
@endphp

@section('content')
<div class="flex flex-wrap items-end justify-between gap-3">
    <div>
        <h1 class="text-2xl font-bold">Mi perfil</h1>
        <p class="text-slate-600">El equipo de Virtuoso mantiene y optimiza tu perfil por ti.</p>
    </div>
    @if ($doctor->is_published)
        <a href="{{ route('doctors.show', $doctor) }}" target="_blank" class="btn-secondary">Ver mi perfil público ↗</a>
    @endif
</div>

<div class="grid lg:grid-cols-[1fr_340px] gap-6 mt-6">
    <div class="space-y-4">
        <section class="card flex gap-4">
            @if ($doctor->photoUrl())<img src="{{ $doctor->photoUrl() }}" alt="" class="w-20 h-20 rounded-full object-cover">@endif
            <div>
                <p class="text-xl font-semibold">{{ $doctor->displayName() }}</p>
                <p class="text-slate-600">{{ $doctor->specialty->name }} · {{ $doctor->city->name }}</p>
                <p class="text-sm text-slate-500">Cédula {{ $doctor->cedula_profesional }}</p>
            </div>
        </section>
        <section class="card">
            <h2 class="font-semibold">Sobre mí</h2>
            <p class="mt-2 whitespace-pre-line text-slate-700">{{ $doctor->bio ?: 'Pendiente.' }}</p>
        </section>
        <section class="card grid sm:grid-cols-2 gap-4 text-sm">
            <div><p class="text-slate-500">Servicios</p><p>{{ implode(', ', $doctor->services ?? []) ?: '—' }}</p></div>
            <div><p class="text-slate-500">Precio de consulta</p><p>{{ $doctor->consultation_price_mxn ? '$'.number_format($doctor->consultation_price_mxn) : '—' }}</p></div>
            <div><p class="text-slate-500">Dirección</p><p>{{ $doctor->address ?: '—' }}{{ $doctor->neighborhood ? ', '.$doctor->neighborhood : '' }}</p></div>
            <div><p class="text-slate-500">Teléfono / WhatsApp</p><p>{{ $doctor->phone ?: '—' }} / {{ $doctor->whatsapp ?: '—' }}</p></div>
        </section>
        <section class="card">
            <h2 class="font-semibold">Horario de consulta</h2>
            <ul class="mt-2 text-sm space-y-1">
                @forelse ($doctor->schedules->sortBy('weekday') as $s)
                    <li>{{ $days[$s->weekday] }}: {{ substr($s->start_time, 0, 5) }} – {{ substr($s->end_time, 0, 5) }} (citas de {{ $s->slot_minutes }} min)</li>
                @empty
                    <li class="text-slate-500">Pendiente.</li>
                @endforelse
            </ul>
            <p class="mt-3 text-sm {{ $calendarConnected ? 'text-brand-700' : 'text-slate-500' }}">
                {{ $calendarConnected ? '✓ Tus citas se guardan en tu Google Calendar y lo ocupado ahí no se ofrece a pacientes.' : 'Google Calendar aún no está conectado.' }}
            </p>
        </section>
    </div>

    <aside class="space-y-4">
        <form method="post" action="{{ route('panel.profile.request') }}" class="card space-y-3">
            @csrf
            <h2 class="font-semibold">¿Quieres cambiar algo?</h2>
            <p class="text-sm text-slate-600">Escríbelo aquí y nuestro equipo lo actualiza por ti.</p>
            <textarea name="message" rows="4" class="input" maxlength="1500" placeholder="Ej.: Los jueves ahora atiendo de 4 a 8 pm." required></textarea>
            <button class="btn-primary w-full">Enviar solicitud</button>
        </form>
        @if ($requests->isNotEmpty())
            <div class="card text-sm">
                <h3 class="font-semibold mb-2">Mis solicitudes</h3>
                <ul class="space-y-2">
                    @foreach ($requests as $r)
                        <li><span class="{{ $r->status === 'done' ? 'text-brand-700' : 'text-amber-700' }}">{{ $r->status === 'done' ? '✓ Hecho' : '⏳ Pendiente' }}</span> · {{ \Illuminate\Support\Str::limit($r->message, 60) }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
    </aside>
</div>
@endsection
