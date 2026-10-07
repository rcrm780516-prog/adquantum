@extends('layouts.public')

@section('title', $title.' · Agenda tu cita | '.config('plataforma.nombre'))
@section('description', 'Encuentra '.mb_strtolower($specialty->name).($city ? ' en '.$city->name : ' en México').'. Compara reseñas verificadas, precios y horarios, y agenda tu cita en línea.')

@section('content')
<div class="max-w-6xl mx-auto px-4 py-10">
    <nav class="text-sm text-slate-500 mb-4"><a href="{{ route('home') }}" class="hover:underline">Inicio</a> › {{ $specialty->name }} @if($city) › {{ $city->name }} @endif</nav>
    <h1 class="text-3xl font-bold">{{ $title }}</h1>
    <p class="text-slate-600 mt-2">{{ $doctors->total() }} {{ $doctors->total() === 1 ? 'médico disponible' : 'médicos disponibles' }}</p>

    <div class="grid md:grid-cols-2 gap-4 mt-6">
        @forelse ($doctors as $doctor)
            @include('partials.doctor-card')
        @empty
            <p class="text-slate-600">Aún no hay médicos publicados aquí. <a href="{{ route('for-doctors') }}" class="text-brand-700 underline">¿Eres {{ mb_strtolower($specialty->name) }}? Regístrate.</a></p>
        @endforelse
    </div>
    <div class="mt-6">{{ $doctors->links() }}</div>
</div>
@endsection
