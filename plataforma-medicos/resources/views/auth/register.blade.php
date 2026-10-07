@extends('layouts.public')
@section('title', 'Registro de médicos | '.config('plataforma.nombre'))

@section('content')
<div class="max-w-2xl mx-auto px-4 py-12">
    <form method="post" action="{{ route('register') }}" class="card space-y-4">
        @csrf
        <h1 class="text-2xl font-bold">Crea tu perfil médico</h1>
        <p class="text-slate-600 text-sm">Tu perfil se publica cuando se activa tu plan.</p>

        <div class="grid sm:grid-cols-[110px_1fr] gap-3">
            <div><label class="label" for="title">Título</label>
                <select class="input" id="title" name="title"><option @selected(old('title') === 'Dr.')>Dr.</option><option @selected(old('title') === 'Dra.')>Dra.</option></select></div>
            <div><label class="label" for="name">Nombre completo</label><input class="input" id="name" name="name" value="{{ old('name') }}" required></div>
        </div>
        <div class="grid sm:grid-cols-2 gap-3">
            <div><label class="label" for="specialty_id">Especialidad</label>
                <select class="input" id="specialty_id" name="specialty_id" required>
                    @foreach ($specialties as $s)<option value="{{ $s->id }}" @selected(old('specialty_id') == $s->id)>{{ $s->name }}</option>@endforeach
                </select></div>
            <div><label class="label" for="city_id">Ciudad</label>
                <select class="input" id="city_id" name="city_id" required>
                    @foreach ($cities as $c)<option value="{{ $c->id }}" @selected(old('city_id') == $c->id)>{{ $c->name }}</option>@endforeach
                </select></div>
        </div>
        <div class="grid sm:grid-cols-2 gap-3">
            <div><label class="label" for="cedula_profesional">Cédula profesional</label><input class="input" id="cedula_profesional" name="cedula_profesional" value="{{ old('cedula_profesional') }}" required></div>
            <div><label class="label" for="whatsapp">WhatsApp</label><input class="input" id="whatsapp" name="whatsapp" value="{{ old('whatsapp') }}" required inputmode="tel"></div>
        </div>
        <div><label class="label" for="email">Correo</label><input class="input" id="email" type="email" name="email" value="{{ old('email') }}" required></div>
        <div class="grid sm:grid-cols-2 gap-3">
            <div><label class="label" for="password">Contraseña</label><input class="input" id="password" type="password" name="password" required minlength="8"></div>
            <div><label class="label" for="password_confirmation">Confirmar contraseña</label><input class="input" id="password_confirmation" type="password" name="password_confirmation" required></div>
        </div>
        <label class="flex gap-2 text-sm text-slate-600"><input type="checkbox" name="terms" value="1" required> Acepto los términos del servicio y el <a href="{{ route('privacy') }}" class="underline" target="_blank">aviso de privacidad</a>.</label>
        <button class="btn-primary w-full">Crear mi perfil</button>
    </form>
</div>
@endsection
