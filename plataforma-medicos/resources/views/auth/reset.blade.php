@extends('layouts.public')
@section('title', 'Crear contraseña | '.config('plataforma.nombre'))
@push('head')<meta name="robots" content="noindex">@endpush

@section('content')
<div class="max-w-md mx-auto px-4 py-16">
    <form method="post" action="{{ route('password.update') }}" class="card space-y-4">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <h1 class="text-2xl font-bold">Crea tu contraseña</h1>
        <div><label class="label" for="email">Correo</label><input class="input" id="email" type="email" name="email" value="{{ old('email', $email) }}" required></div>
        <div><label class="label" for="password">Nueva contraseña</label><input class="input" id="password" type="password" name="password" required minlength="8" autofocus>
            <p class="text-xs text-slate-500 mt-1">Mínimo 8 caracteres.</p></div>
        <div><label class="label" for="password_confirmation">Repite la contraseña</label><input class="input" id="password_confirmation" type="password" name="password_confirmation" required></div>
        <button class="btn-primary w-full">Guardar y entrar</button>
    </form>
</div>
@endsection
