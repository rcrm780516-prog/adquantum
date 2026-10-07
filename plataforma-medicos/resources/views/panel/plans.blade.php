@extends('layouts.panel')
@section('title', 'Planes')

@section('content')
<h1 class="text-2xl font-bold">Planes</h1>
@if ($motivo === 'ia')<p class="mt-2 text-amber-700">Usaste todas tus consultas de IA de este mes. Con el plan Pro tienes 200 al mes.</p>@endif
@if ($motivo === 'creativos')<p class="mt-2 text-amber-700">Llegaste al límite de anuncios de tu plan este mes.</p>@endif

<div class="grid md:grid-cols-3 gap-4 mt-6">
    @foreach ($plans as $plan)
        @php($current = $doctor->plan_id === $plan->id && $doctor->hasActivePlan())
        <div class="card flex flex-col @if($current) border-brand-500 ring-2 ring-brand-100 @endif">
            <h2 class="text-xl font-semibold">{{ $plan->name }} @if($current)<span class="text-xs bg-brand-50 text-brand-700 rounded px-2 py-0.5 align-middle">Tu plan</span>@endif</h2>
            <p class="text-2xl font-bold mt-2">
                @if ($plan->billing_interval === 'custom') A tu medida
                @else ${{ number_format($plan->price_mxn) }} <span class="text-base font-normal text-slate-500">/ {{ $plan->billing_interval === 'year' ? 'año' : 'mes' }}</span>@endif
            </p>
            <ul class="mt-4 space-y-2 text-sm flex-1">@foreach ($plan->features as $f)<li class="flex gap-2"><span class="text-brand-600">✓</span>{{ $f }}</li>@endforeach</ul>
            @unless ($current)
                <form method="post" action="{{ route('panel.plans.interest') }}" class="mt-6">
                    @csrf
                    <input type="hidden" name="plan_interest" value="{{ $plan->slug }}">
                    <input type="hidden" name="source" value="{{ $motivo ? 'limite_'.$motivo : 'panel' }}">
                    <button class="btn-primary w-full">{{ $plan->slug === 'basico' ? 'Activar plan Básico' : ($plan->billing_interval === 'custom' ? 'Hablar con Virtuoso' : 'Quiero el plan '.$plan->name) }}</button>
                </form>
            @endunless
        </div>
    @endforeach
</div>
<p class="text-sm text-slate-500 mt-4">Por ahora la activación es con un asesor (transferencia, tarjeta u OXXO). Te contactamos por WhatsApp.</p>
@endsection
