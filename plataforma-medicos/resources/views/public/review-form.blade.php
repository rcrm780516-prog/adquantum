@extends('layouts.public')
@section('title', 'Califica tu consulta')
@push('head')<meta name="robots" content="noindex">@endpush

@section('content')
<div class="max-w-xl mx-auto px-4 py-12">
    <div class="card">
        <h1 class="text-2xl font-bold">¿Cómo fue tu consulta con {{ $appointment->doctor->displayName() }}?</h1>
        <p class="text-slate-600 mt-1">Tu opinión ayuda a otros pacientes. No compartas información de tu salud.</p>

        <form method="post" action="{{ route('reviews.store', $appointment->review_token) }}" class="mt-6 space-y-4">
            @csrf
            <fieldset>
                <legend class="label">Calificación</legend>
                <div class="flex flex-row-reverse justify-end gap-1 text-4xl">
                    @for ($i = 5; $i >= 1; $i--)
                        <input type="radio" id="r{{ $i }}" name="rating" value="{{ $i }}" class="peer sr-only" required>
                        <label for="r{{ $i }}" class="cursor-pointer text-slate-300 peer-checked:text-amber-500 hover:text-amber-400 [&:hover~label]:text-amber-400 peer-checked:[&~label]:text-amber-500" title="{{ $i }} estrellas">★</label>
                    @endfor
                </div>
            </fieldset>
            <div><label class="label" for="comment">Comentario (opcional)</label>
                <textarea class="input" id="comment" name="comment" rows="4" maxlength="1000" placeholder="¿Qué te pareció la atención, la puntualidad, el trato?"></textarea></div>
            <div><label class="label" for="patient_name">¿Cómo quieres aparecer? (opcional)</label>
                <input class="input" id="patient_name" name="patient_name" maxlength="60" placeholder="Por defecto: tu nombre e iniciales"></div>
            <button class="btn-primary w-full">Enviar opinión</button>
        </form>
    </div>
</div>
@endsection
