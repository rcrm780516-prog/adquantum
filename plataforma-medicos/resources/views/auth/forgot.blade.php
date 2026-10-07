@extends('layouts.public')
@section('title', 'Recuperar contraseña | '.config('plataforma.nombre'))

@section('content')
<div class="max-w-md mx-auto px-4 py-16">
    <form method="post" action="{{ route('password.email') }}" class="card space-y-4">
        @csrf
        <h1 class="text-2xl font-bold">¿Olvidaste tu contraseña?</h1>
        <p class="text-sm text-slate-600">Escribe tu correo y te enviaremos un enlace para crear una nueva.</p>
        <div><label class="label" for="email">Correo</label><input class="input" id="email" type="email" name="email" value="{{ old('email') }}" required autofocus></div>
        <button class="btn-primary w-full">Enviar enlace</button>
        <p class="text-sm text-center"><a href="{{ route('login') }}" class="text-brand-700 underline">Volver a entrar</a></p>
    </form>
</div>
@endsection
