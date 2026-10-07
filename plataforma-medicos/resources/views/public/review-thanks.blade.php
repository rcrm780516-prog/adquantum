@extends('layouts.public')
@section('title', '¡Gracias por tu opinión!')
@push('head')<meta name="robots" content="noindex">@endpush

{{-- IMPORTANTE (política de Google): esta pantalla es idéntica para cualquier calificación.
     No se oculta ni se condiciona el botón de Google según las estrellas ("review gating"). --}}
@section('content')
<div class="max-w-xl mx-auto px-4 py-16 text-center">
    <div class="text-5xl">🙏</div>
    <h1 class="text-2xl font-bold mt-4">¡Gracias por tu opinión!</h1>
    <p class="mt-2 text-slate-600">Ya está publicada en el perfil de {{ $doctor->displayName() }}.</p>

    @if ($doctor->googleReviewUrl())
        <div class="card mt-8">
            <p class="font-medium">¿Nos ayudas a compartirla también en Google?</p>
            <p class="text-sm text-slate-600 mt-1">Así más pacientes pueden conocer tu experiencia. Toma menos de un minuto.</p>
            <a href="{{ route('reviews.google', $review->appointment->review_token) }}" class="btn-primary mt-4" data-testid="google-review-button">Escribir reseña en Google</a>
        </div>
    @endif
</div>
@endsection
