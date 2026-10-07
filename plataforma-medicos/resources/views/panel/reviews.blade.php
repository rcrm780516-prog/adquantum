@extends('layouts.panel')
@section('title', 'Reseñas')

@section('content')
<h1 class="text-2xl font-bold">Reseñas</h1>
<p class="text-slate-600">Después de cada cita invitamos a <strong>todos</strong> tus pacientes a calificarte y a dejar su opinión en Google. No filtramos por calificación: Google lo prohíbe y puede borrar las reseñas de tu ficha.</p>

@unless ($doctor->google_place_id)
    <div class="mt-4 rounded-lg bg-amber-50 border border-amber-200 p-3 text-sm">Agrega tu <a href="{{ route('panel.profile') }}" class="underline">Google Place ID</a> para que tus pacientes puedan reseñarte en Google con un clic.</div>
@endunless

<div class="space-y-4 mt-6">
    @forelse ($reviews as $review)
        <div class="card">
            <p>@include('partials.stars', ['rating' => $review->rating]) <strong class="ml-1">{{ $review->patient_name }}</strong>
                <span class="text-xs text-slate-400 ml-1">{{ $review->created_at->format('d/m/Y') }}</span>
                @if ($review->google_invite_clicked_at)<span class="text-xs bg-brand-50 text-brand-700 rounded px-1.5 py-0.5 ml-1">Fue a Google</span>@endif</p>
            @if ($review->comment)<p class="mt-1">{{ $review->comment }}</p>@endif

            @php($suggestion = session('suggestion.review_id') === $review->id ? session('suggestion.text') : null)
            <form method="post" action="{{ route('panel.reviews.reply', $review) }}" class="mt-3 space-y-2">
                @csrf @method('put')
                <textarea name="doctor_reply" rows="2" class="input" placeholder="Escribe una respuesta pública…">{{ $suggestion ?? $review->doctor_reply }}</textarea>
                <button class="btn-primary">Publicar respuesta</button>
            </form>
            <form method="post" action="{{ route('panel.reviews.suggest', $review) }}" class="mt-2">
                @csrf
                <button class="btn-secondary">✨ Sugerir respuesta con IA</button>
            </form>
        </div>
    @empty
        <p class="text-slate-500">Todavía no tienes reseñas.</p>
    @endforelse
</div>
<div class="mt-4">{{ $reviews->links() }}</div>
@endsection
