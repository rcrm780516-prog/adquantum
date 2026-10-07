@extends('layouts.public')
@section('title', 'Entrar | '.config('plataforma.nombre'))

@section('content')
<div class="max-w-md mx-auto px-4 py-16">
    <form method="post" action="{{ route('login') }}" class="card space-y-4">
        @csrf
        <h1 class="text-2xl font-bold">Entrar a mi panel</h1>
        <div><label class="label" for="email">Correo</label><input class="input" id="email" type="email" name="email" value="{{ old('email') }}" required autofocus></div>
        <div><label class="label" for="password">Contraseña</label><input class="input" id="password" type="password" name="password" required></div>
        <label class="flex gap-2 text-sm"><input type="checkbox" name="remember" value="1"> Recordarme</label>
        <button class="btn-primary w-full">Entrar</button>
        <p class="text-sm text-center text-slate-600">¿Aún no tienes cuenta? <a href="{{ route('register') }}" class="text-brand-700 underline">Regístrate</a></p>
    </form>
</div>
@endsection
