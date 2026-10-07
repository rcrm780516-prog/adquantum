@extends('layouts.public')
@section('title', 'Cita confirmada')
@push('head')<meta name="robots" content="noindex">@endpush

@section('content')
<div class="max-w-xl mx-auto px-4 py-16 text-center">
    <div class="text-5xl">✅</div>
    <h1 class="text-2xl font-bold mt-4">¡Tu cita está confirmada!</h1>
    <p class="mt-4 text-lg">{{ $appointment->doctor->displayName() }}</p>
    <p class="text-slate-600">{{ \Illuminate\Support\Str::ucfirst($appointment->starts_at->locale('es')->isoFormat('dddd D [de] MMMM, h:mm a')) }}</p>
    <p class="text-slate-600 mt-1">{{ $appointment->doctor->address }}</p>
    <p class="mt-6 text-sm text-slate-500">Te enviaremos un recordatorio por WhatsApp. Si necesitas cambiarla, comunícate con el consultorio.</p>
    <a href="{{ route('doctors.show', $appointment->doctor) }}" class="btn-secondary mt-6">Volver al perfil</a>
</div>
@endsection
