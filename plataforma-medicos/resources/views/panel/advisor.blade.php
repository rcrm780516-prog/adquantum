@extends('layouts.panel')
@section('title', 'Asesor IA')

@section('content')
<h1 class="text-2xl font-bold">Asesor de marketing con IA</h1>
<p class="text-slate-600">Ideas rápidas para atraer pacientes. Te quedan <strong>{{ $aiRemaining }}</strong> consultas este mes.</p>
<p class="text-xs text-slate-500 mt-1">Asesoría de marketing únicamente; no es consejo médico ni legal.</p>

<form method="post" action="{{ route('panel.advisor.ask') }}" class="card mt-6 space-y-3">
    @csrf
    <label class="label" for="question">¿Qué quieres mejorar?</label>
    <textarea class="input" id="question" name="question" rows="3" maxlength="500" required placeholder="Ej.: ¿Cómo consigo más pacientes de primera vez en mi colonia?"></textarea>
    <button class="btn-primary" @disabled($aiRemaining <= 0)>Preguntar</button>
</form>

<div class="space-y-4 mt-6">
    @foreach ($history as $item)
        <div class="card">
            <p class="text-sm text-slate-500">{{ $item->created_at->locale('es')->diffForHumans() }}</p>
            <div class="mt-2 whitespace-pre-line">{{ $item->output }}</div>
        </div>
    @endforeach
</div>

<div class="mt-6">@include('partials.upsell', ['title' => '¿Prefieres una estrategia completa?', 'text' => 'El asesor te da ideas rápidas. Un estratega de Virtuoso arma contigo un plan de 4 meses con campañas, contenido y métricas.', 'source' => 'advisor'])</div>
@endsection
