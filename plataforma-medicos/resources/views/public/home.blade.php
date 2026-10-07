@extends('layouts.public')

@section('content')
<section class="bg-gradient-to-b from-brand-50 to-slate-50">
    <div class="max-w-6xl mx-auto px-4 py-16">
        <h1 class="text-3xl md:text-5xl font-bold text-brand-900 max-w-3xl">Encuentra a tu médico, lee reseñas reales y agenda en minutos</h1>
        <p class="mt-4 text-lg text-slate-600 max-w-2xl">Médicos con cédula profesional verificada en todo México.</p>

        <form action="{{ route('search') }}" method="get" class="mt-8 bg-white rounded-xl shadow-sm border border-slate-200 p-4 grid md:grid-cols-[1fr_1fr_auto] gap-3">
            <select name="especialidad" class="input" required aria-label="Especialidad">
                <option value="">¿Qué especialidad buscas?</option>
                @foreach ($specialties as $s)<option value="{{ $s->slug }}">{{ $s->name }}</option>@endforeach
            </select>
            <select name="ciudad" class="input" aria-label="Ciudad">
                <option value="">Todas las ciudades</option>
                @foreach ($cities as $c)<option value="{{ $c->slug }}">{{ $c->name }}</option>@endforeach
            </select>
            <button class="btn-primary">Buscar</button>
        </form>
    </div>
</section>

@if ($featured->isNotEmpty())
<section class="max-w-6xl mx-auto px-4 mt-12">
    <h2 class="text-2xl font-semibold mb-4">Médicos mejor calificados</h2>
    <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-4">
        @foreach ($featured as $doctor) @include('partials.doctor-card') @endforeach
    </div>
</section>
@endif

<section class="max-w-6xl mx-auto px-4 mt-12">
    <h2 class="text-2xl font-semibold mb-4">Especialidades</h2>
    <div class="flex flex-wrap gap-2">
        @foreach ($specialties as $s)
            <a href="{{ route('directory', $s) }}" class="px-3 py-1.5 rounded-full bg-white border border-slate-200 text-sm hover:border-brand-500">{{ $s->name }}</a>
        @endforeach
    </div>
</section>
@endsection
