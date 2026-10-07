@extends('layouts.public')
@section('title', 'Para médicos · Aparece en Google y recibe citas | '.config('plataforma.nombre'))
@section('description', 'Posiciona tu consultorio en Google, recibe citas en línea y reseñas verificadas desde $500 al año.')

@section('content')
<section class="bg-brand-900 text-white">
    <div class="max-w-6xl mx-auto px-4 py-16">
        <h1 class="text-3xl md:text-5xl font-bold max-w-3xl">Que tus pacientes te encuentren en Google y agenden contigo</h1>
        <p class="mt-4 text-lg text-brand-100 max-w-2xl">Nuestro equipo configura todo por ti: tu perfil, tu ficha de Google, tu agenda en línea y tus reseñas. Tú solo atiendes pacientes. Desde <strong>$500 al año</strong>.</p>
        <a href="{{ $contactUrl }}" class="btn-primary mt-8 bg-white text-brand-900 hover:bg-brand-50" target="_blank" rel="noopener">Quiero mi perfil</a>
    </div>
</section>

<section class="max-w-6xl mx-auto px-4 mt-12">
    <h2 class="text-2xl font-semibold mb-4">Cómo funciona</h2>
    <ol class="grid md:grid-cols-3 gap-4">
        <li class="card"><p class="text-brand-700 font-bold">1. Nos escribes</p><p class="text-sm text-slate-600 mt-1">Un asesor te pide tus datos y tus horarios por WhatsApp.</p></li>
        <li class="card"><p class="text-brand-700 font-bold">2. Configuramos todo</p><p class="text-sm text-slate-600 mt-1">Creamos tu perfil, optimizamos tu ficha de Google y conectamos tu agenda con tu Google Calendar.</p></li>
        <li class="card"><p class="text-brand-700 font-bold">3. Recibes pacientes</p><p class="text-sm text-slate-600 mt-1">Las citas llegan a tu calendario y tus pacientes te califican. Tú solo revisas tu panel cuando quieras.</p></li>
    </ol>
</section>

<section class="max-w-6xl mx-auto px-4 mt-12">
    <h2 class="text-2xl font-semibold mb-6">Planes</h2>
    <div class="grid md:grid-cols-3 gap-4">
        @foreach ($plans as $plan)
            <div class="card flex flex-col @if($plan->slug === 'pro') border-brand-500 ring-2 ring-brand-100 @endif">
                <h3 class="text-xl font-semibold">{{ $plan->name }}</h3>
                <p class="text-3xl font-bold mt-2">
                    @if ($plan->billing_interval === 'custom') A tu medida
                    @else ${{ number_format($plan->price_mxn) }} <span class="text-base font-normal text-slate-500">MXN / {{ $plan->billing_interval === 'year' ? 'año' : 'mes' }}</span>
                    @endif
                </p>
                <ul class="mt-4 space-y-2 text-sm flex-1">
                    @foreach ($plan->features as $f)<li class="flex gap-2"><span class="text-brand-600">✓</span>{{ $f }}</li>@endforeach
                </ul>
                <a href="{{ $contactUrl }}" class="btn-primary mt-6" target="_blank" rel="noopener">Me interesa</a>
            </div>
        @endforeach
    </div>
</section>
@endsection
