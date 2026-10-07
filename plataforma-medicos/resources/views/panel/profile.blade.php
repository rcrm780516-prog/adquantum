@extends('layouts.panel')
@section('title', 'Mi perfil')

@php($days = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'])
@php($byDay = $doctor->schedules->keyBy('weekday'))

@section('content')
<h1 class="text-2xl font-bold">Mi perfil</h1>
<p class="text-slate-600">Entre más completo, mejor te posiciona Google.</p>

<form method="post" action="{{ route('panel.profile.update') }}" enctype="multipart/form-data" class="mt-6 space-y-6">
    @csrf @method('put')

    <section class="card space-y-4">
        <h2 class="font-semibold">Datos profesionales</h2>
        <div class="grid sm:grid-cols-[110px_1fr] gap-3">
            <div><label class="label">Título</label><select class="input" name="title"><option @selected($doctor->title === 'Dr.')>Dr.</option><option @selected($doctor->title === 'Dra.')>Dra.</option></select></div>
            <div><label class="label">Nombre</label><input class="input" name="name" value="{{ old('name', $doctor->name) }}" required></div>
        </div>
        <div class="grid sm:grid-cols-2 gap-3">
            <div><label class="label">Especialidad</label><select class="input" name="specialty_id">@foreach ($specialties as $s)<option value="{{ $s->id }}" @selected($doctor->specialty_id === $s->id)>{{ $s->name }}</option>@endforeach</select></div>
            <div><label class="label">Ciudad</label><select class="input" name="city_id">@foreach ($cities as $c)<option value="{{ $c->id }}" @selected($doctor->city_id === $c->id)>{{ $c->name }}</option>@endforeach</select></div>
            <div><label class="label">Cédula profesional</label><input class="input" name="cedula_profesional" value="{{ old('cedula_profesional', $doctor->cedula_profesional) }}" required></div>
            <div><label class="label">Cédula de especialidad</label><input class="input" name="cedula_especialidad" value="{{ old('cedula_especialidad', $doctor->cedula_especialidad) }}"></div>
        </div>
        <div><label class="label">Sobre mí</label><textarea class="input" name="bio" rows="5" maxlength="2000">{{ old('bio', $doctor->bio) }}</textarea>
            <p class="text-xs text-slate-500 mt-1">Recomendado: 250+ caracteres. Menciona tu especialidad, tu ciudad y en qué te enfocas.</p></div>
        <div class="grid sm:grid-cols-2 gap-3">
            <div><label class="label">Servicios (uno por línea)</label><textarea class="input" name="services_text" rows="4">{{ old('services_text', implode("\n", $doctor->services ?? [])) }}</textarea></div>
            <div><label class="label">Aseguradoras (separadas por coma)</label><textarea class="input" name="insurances_text" rows="4">{{ old('insurances_text', implode(', ', $doctor->insurances ?? [])) }}</textarea></div>
        </div>
        <div class="grid sm:grid-cols-2 gap-3">
            <div><label class="label">Precio de consulta (MXN)</label><input class="input" type="number" name="consultation_price_mxn" value="{{ old('consultation_price_mxn', $doctor->consultation_price_mxn) }}"></div>
            <div><label class="label">Foto profesional</label><input class="input" type="file" name="photo" accept="image/*"></div>
        </div>
    </section>

    <section class="card space-y-4">
        <h2 class="font-semibold">Contacto y ubicación</h2>
        <div class="grid sm:grid-cols-2 gap-3">
            <div><label class="label">Teléfono del consultorio</label><input class="input" name="phone" value="{{ old('phone', $doctor->phone) }}"></div>
            <div><label class="label">WhatsApp</label><input class="input" name="whatsapp" value="{{ old('whatsapp', $doctor->whatsapp) }}"></div>
            <div class="sm:col-span-2"><label class="label">Dirección</label><input class="input" name="address" value="{{ old('address', $doctor->address) }}"></div>
            <div><label class="label">Colonia</label><input class="input" name="neighborhood" value="{{ old('neighborhood', $doctor->neighborhood) }}"></div>
            <div><label class="label">Código postal</label><input class="input" name="postal_code" value="{{ old('postal_code', $doctor->postal_code) }}"></div>
            <div><label class="label">Sitio web</label><input class="input" type="url" name="website" value="{{ old('website', $doctor->website) }}" placeholder="https://"></div>
            <div><label class="label">Google Place ID</label><input class="input" name="google_place_id" value="{{ old('google_place_id', $doctor->google_place_id) }}" placeholder="ChIJ...">
                <p class="text-xs text-slate-500 mt-1">Lo usamos para el botón “Escribir reseña en Google”. Búscalo en <a class="underline" href="https://developers.google.com/maps/documentation/places/web-service/place-id" target="_blank" rel="noopener">el buscador de Place ID</a>.</p></div>
        </div>
    </section>

    <section class="card space-y-3">
        <h2 class="font-semibold">Horario de consulta</h2>
        <div><label class="label">Duración de cada cita</label>
            <select class="input w-40" name="slot_minutes">@foreach ([15, 20, 30, 45, 60] as $m)<option value="{{ $m }}" @selected(($byDay->first()->slot_minutes ?? 30) === $m)>{{ $m }} min</option>@endforeach</select></div>
        @foreach ($days as $i => $day)
            <div class="grid grid-cols-[120px_1fr_1fr] gap-3 items-center">
                <label class="flex gap-2 items-center"><input type="checkbox" name="schedules[{{ $i }}][enabled]" value="1" @checked($byDay->has($i))> {{ $day }}</label>
                <input class="input" type="time" name="schedules[{{ $i }}][start_time]" value="{{ substr($byDay[$i]->start_time ?? '09:00', 0, 5) }}">
                <input class="input" type="time" name="schedules[{{ $i }}][end_time]" value="{{ substr($byDay[$i]->end_time ?? '14:00', 0, 5) }}">
            </div>
        @endforeach
    </section>

    <button class="btn-primary">Guardar cambios</button>
</form>
@endsection
