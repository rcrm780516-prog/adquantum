@extends('layouts.admin')
@section('title', 'Resumen')

@section('content')
<h1 class="text-2xl font-bold">Resumen</h1>
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-6">
    <div class="card"><p class="text-sm text-slate-500">Médicos</p><p class="text-3xl font-bold">{{ $stats['medicos'] }}</p></div>
    <div class="card"><p class="text-sm text-slate-500">Publicados</p><p class="text-3xl font-bold">{{ $stats['publicados'] }}</p></div>
    <div class="card"><p class="text-sm text-slate-500">Citas este mes</p><p class="text-3xl font-bold">{{ $stats['citas_mes'] }}</p></div>
    <div class="card"><p class="text-sm text-slate-500">Leads nuevos</p><p class="text-3xl font-bold text-amber-700">{{ $stats['leads_nuevos'] }}</p></div>
</div>

<div class="grid lg:grid-cols-2 gap-6 mt-6">
    <section class="card">
        <h2 class="font-semibold">Cambios que piden los médicos</h2>
        <ul class="mt-3 divide-y divide-slate-100 text-sm">
            @forelse ($changeRequests as $r)
                <li class="py-3">
                    <p><a class="font-medium text-brand-700 underline" href="{{ route('admin.doctors.edit', $r->doctor) }}">{{ $r->doctor->displayName() }}</a>
                        <span class="text-slate-400">· {{ $r->created_at->locale('es')->diffForHumans() }}</span></p>
                    <p class="mt-1 whitespace-pre-line">{{ $r->message }}</p>
                    <form method="post" action="{{ route('admin.changes.resolve', $r) }}" class="mt-2">@csrf<button class="btn-secondary py-1 text-xs">Marcar como hecho</button></form>
                </li>
            @empty
                <li class="py-3 text-slate-500">Sin pendientes.</li>
            @endforelse
        </ul>
    </section>

    <div class="space-y-6">
        <section class="card">
            <h2 class="font-semibold">Leads de upgrade nuevos</h2>
            <ul class="mt-3 divide-y divide-slate-100 text-sm">
                @forelse ($leads as $lead)
                    <li class="py-2 flex justify-between gap-2">
                        <span><a class="text-brand-700 underline" href="{{ route('admin.doctors.edit', $lead->doctor) }}">{{ $lead->doctor->displayName() }}</a> → {{ $lead->plan_interest }}</span>
                        <span class="text-slate-400">{{ $lead->source }}</span>
                    </li>
                @empty
                    <li class="py-2 text-slate-500">Sin leads nuevos.</li>
                @endforelse
            </ul>
            <a href="{{ route('admin.leads') }}" class="text-sm text-brand-700 underline mt-2 inline-block">Ver todos</a>
        </section>

        <section class="card">
            <h2 class="font-semibold">Requieren atención</h2>
            <ul class="mt-3 divide-y divide-slate-100 text-sm">
                @forelse ($attention as $d)
                    <li class="py-2">
                        <a class="text-brand-700 underline" href="{{ route('admin.doctors.edit', $d) }}">{{ $d->displayName() }}</a>
                        @if ($d->google_calendar_error)<span class="text-red-700"> · Calendario: {{ \Illuminate\Support\Str::limit($d->google_calendar_error, 60) }}</span>@endif
                        @if ($d->plan_expires_at && $d->plan_expires_at->isBefore(now()->addDays(30)))<span class="text-amber-700"> · Plan vence {{ $d->plan_expires_at->format('d/m/Y') }}</span>@endif
                    </li>
                @empty
                    <li class="py-2 text-slate-500">Todo en orden.</li>
                @endforelse
            </ul>
        </section>
    </div>
</div>
@endsection
