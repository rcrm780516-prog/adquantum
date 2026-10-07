@extends('layouts.admin')
@section('title', $doctor->exists ? $doctor->displayName() : 'Nuevo médico')

@php
    $days = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
    $byDay = $doctor->exists ? $doctor->schedules->keyBy('weekday') : collect();
    $field = fn ($name, $default = null) => old($name, $doctor->{$name} ?? $default);
@endphp

@section('content')
<div class="flex flex-wrap items-end justify-between gap-3">
    <div>
        <a href="{{ route('admin.doctors.index') }}" class="text-sm text-slate-500 hover:underline">← Médicos</a>
        <h1 class="text-2xl font-bold">{{ $doctor->exists ? $doctor->displayName() : 'Nuevo médico' }}</h1>
    </div>
    @if ($doctor->exists && $doctor->is_published)
        <a href="{{ route('doctors.show', $doctor) }}" target="_blank" class="btn-secondary">Ver perfil público ↗</a>
    @endif
</div>

<div class="grid xl:grid-cols-[1fr_340px] gap-6 mt-6">
<form method="post" enctype="multipart/form-data" action="{{ $doctor->exists ? route('admin.doctors.update', $doctor) : route('admin.doctors.store') }}" class="space-y-6">
    @csrf
    @if ($doctor->exists) @method('put') @endif

    <section class="card space-y-4">
        <h2 class="font-semibold">1. Datos del médico</h2>
        <div class="grid sm:grid-cols-[110px_1fr] gap-3">
            <div><label class="label">Título</label><select class="input" name="title"><option @selected($field('title') === 'Dr.')>Dr.</option><option @selected($field('title') === 'Dra.')>Dra.</option></select></div>
            <div><label class="label">Nombre completo</label><input class="input" name="name" value="{{ $field('name') }}" required></div>
        </div>
        <div class="grid sm:grid-cols-2 gap-3">
            <div><label class="label">Correo para entrar a la plataforma</label><input class="input" type="email" name="email" value="{{ old('email', $doctor->user->email ?? '') }}" required></div>
            <div><label class="label">WhatsApp</label><input class="input" name="whatsapp" value="{{ $field('whatsapp') }}"></div>
            <div><label class="label">Especialidad</label><select class="input" name="specialty_id">@foreach ($specialties as $s)<option value="{{ $s->id }}" @selected($field('specialty_id') == $s->id)>{{ $s->name }}</option>@endforeach</select></div>
            <div><label class="label">Ciudad</label><select class="input" name="city_id">@foreach ($cities as $c)<option value="{{ $c->id }}" @selected($field('city_id') == $c->id)>{{ $c->name }}</option>@endforeach</select></div>
            <div><label class="label">Cédula profesional</label><input class="input" name="cedula_profesional" value="{{ $field('cedula_profesional') }}" required></div>
            <div><label class="label">Cédula de especialidad</label><input class="input" name="cedula_especialidad" value="{{ $field('cedula_especialidad') }}"></div>
        </div>
        <p class="text-xs text-slate-500">Verifica las cédulas en <a class="underline" target="_blank" rel="noopener" href="https://www.cedulaprofesional.sep.gob.mx/">cedulaprofesional.sep.gob.mx</a> antes de publicar.</p>
    </section>

    <section class="card space-y-4">
        <h2 class="font-semibold">2. Perfil público</h2>
        <div><label class="label">Sobre el médico</label><textarea class="input" name="bio" rows="5" maxlength="2000">{{ $field('bio') }}</textarea>
            <p class="text-xs text-slate-500 mt-1">250+ caracteres. Incluye especialidad, ciudad, colonia y enfoque. Sin promesas de resultados (COFEPRIS).</p></div>
        <div class="grid sm:grid-cols-2 gap-3">
            <div><label class="label">Servicios (uno por línea)</label><textarea class="input" name="services_text" rows="4">{{ old('services_text', implode("\n", $doctor->services ?? [])) }}</textarea></div>
            <div><label class="label">Aseguradoras (separadas por coma)</label><textarea class="input" name="insurances_text" rows="4">{{ old('insurances_text', implode(', ', $doctor->insurances ?? [])) }}</textarea></div>
            <div><label class="label">Precio de consulta (MXN)</label><input class="input" type="number" name="consultation_price_mxn" value="{{ $field('consultation_price_mxn') }}"></div>
            <div><label class="label">Foto profesional</label><input class="input" type="file" name="photo" accept="image/*">
                @if ($doctor->photoUrl())<img src="{{ $doctor->photoUrl() }}" alt="" class="w-12 h-12 rounded-full object-cover mt-2">@endif</div>
        </div>
    </section>

    <section class="card space-y-4">
        <h2 class="font-semibold">3. Consultorio</h2>
        <div class="grid sm:grid-cols-2 gap-3">
            <div class="sm:col-span-2"><label class="label">Dirección</label><input class="input" name="address" value="{{ $field('address') }}"></div>
            <div><label class="label">Colonia</label><input class="input" name="neighborhood" value="{{ $field('neighborhood') }}"></div>
            <div><label class="label">Código postal</label><input class="input" name="postal_code" value="{{ $field('postal_code') }}"></div>
            <div><label class="label">Teléfono del consultorio</label><input class="input" name="phone" value="{{ $field('phone') }}"></div>
            <div><label class="label">Sitio web</label><input class="input" type="url" name="website" value="{{ $field('website') }}" placeholder="https://"></div>
            <div><label class="label">Latitud</label><input class="input" name="lat" value="{{ $field('lat') }}" placeholder="24.8091"></div>
            <div><label class="label">Longitud</label><input class="input" name="lng" value="{{ $field('lng') }}" placeholder="-107.3940"></div>
        </div>
        <p class="text-xs text-slate-500">Latitud y longitud: en Google Maps, clic derecho sobre el consultorio → copia los números que aparecen arriba.</p>
    </section>

    <section class="card space-y-3">
        <h2 class="font-semibold">4. Horario de consulta</h2>
        <div><label class="label">Duración de cada cita</label>
            <select class="input w-40" name="slot_minutes">@foreach ([15, 20, 30, 45, 60] as $m)<option value="{{ $m }}" @selected((int) old('slot_minutes', $byDay->first()->slot_minutes ?? 30) === $m)>{{ $m }} min</option>@endforeach</select></div>
        @foreach ($days as $i => $day)
            <div class="grid grid-cols-[120px_1fr_1fr] gap-3 items-center">
                <label class="flex gap-2 items-center"><input type="checkbox" name="schedules[{{ $i }}][enabled]" value="1" @checked($byDay->has($i))> {{ $day }}</label>
                <input class="input" type="time" name="schedules[{{ $i }}][start_time]" value="{{ substr($byDay[$i]->start_time ?? '09:00', 0, 5) }}">
                <input class="input" type="time" name="schedules[{{ $i }}][end_time]" value="{{ substr($byDay[$i]->end_time ?? '14:00', 0, 5) }}">
            </div>
        @endforeach
    </section>

    <section class="card space-y-4">
        <h2 class="font-semibold">5. Google</h2>
        <div>
            <label class="label">Google Calendar del médico</label>
            <input class="input" name="google_calendar_id" value="{{ $field('google_calendar_id') }}" placeholder="correo.del.medico@gmail.com">
            <details class="mt-2 text-sm text-slate-600">
                <summary class="cursor-pointer text-brand-700">¿Cómo se conecta? (2 minutos, con el médico)</summary>
                <ol class="list-decimal pl-5 mt-2 space-y-1">
                    <li>En la computadora del médico (o con su sesión), abre <strong>calendar.google.com</strong>.</li>
                    <li>A la izquierda, en "Mis calendarios", pasa el mouse sobre su calendario → <strong>⋮ → Configuración y uso compartido</strong>.</li>
                    <li>En "Compartir con personas y grupos específicos" → <strong>Agregar personas</strong>.</li>
                    <li>Pega este correo: <code class="bg-slate-100 px-1 rounded select-all">{{ $serviceAccountEmail ?? '(falta configurar la cuenta de servicio)' }}</code></li>
                    <li>Permiso: <strong>"Hacer cambios en los eventos"</strong> → Enviar.</li>
                    <li>Aquí arriba escribe el correo de Gmail del médico, guarda y presiona <strong>"Probar conexión"</strong>.</li>
                </ol>
            </details>
        </div>
        <div>
            <label class="label">Google Place ID de su ficha</label>
            <input class="input" name="google_place_id" value="{{ $field('google_place_id') }}" placeholder="ChIJ...">
            <p class="text-xs text-slate-500 mt-1">Se obtiene en <a class="underline" target="_blank" rel="noopener" href="https://developers.google.com/maps/documentation/places/web-service/place-id">el buscador de Place ID</a>. Activa el botón "Reseñar en Google" para los pacientes.</p>
        </div>
    </section>

    @unless ($doctor->exists)
        <label class="flex gap-2 items-center"><input type="checkbox" name="send_access" value="1" checked> Enviar al médico el correo para crear su contraseña</label>
    @endunless
    <button class="btn-primary">{{ $doctor->exists ? 'Guardar cambios' : 'Crear médico' }}</button>
</form>

@if ($doctor->exists)
<aside class="space-y-4">
    <div class="card text-sm space-y-2">
        <h2 class="font-semibold">Estado</h2>
        <p>Perfil: {!! $doctor->is_published ? '<span class="text-brand-700">Publicado</span>' : '<span class="text-slate-500">Borrador</span>' !!}</p>
        <p>Plan: {{ $doctor->plan?->name ?? 'Sin plan' }} @if($doctor->plan_expires_at) · vence {{ $doctor->plan_expires_at->format('d/m/Y') }} @endif</p>
        <p>Ficha de Google: <strong>{{ $gbpScore }}/100</strong></p>
        <p>Calendario:
            @if (! $doctor->google_calendar_id) <span class="text-slate-500">sin conectar</span>
            @elseif ($doctor->google_calendar_error) <span class="text-red-700">{{ $doctor->google_calendar_error }}</span>
            @elseif ($doctor->google_calendar_checked_at) <span class="text-brand-700">✓ conectado ({{ $doctor->google_calendar_checked_at->format('d/m H:i') }})</span>
            @else <span class="text-amber-700">sin probar</span> @endif
        </p>
        @if ($doctor->google_calendar_id)
            <form method="post" action="{{ route('admin.doctors.calendar', $doctor) }}">@csrf<button class="btn-secondary w-full">Probar conexión con Google Calendar</button></form>
        @endif
    </div>

    <form method="post" action="{{ route('admin.doctors.plan', $doctor) }}" class="card space-y-3 text-sm">
        @csrf
        <h2 class="font-semibold">Activar plan</h2>
        <select name="plan_id" class="input">@foreach ($plans as $p)<option value="{{ $p->id }}" @selected($doctor->plan_id === $p->id)>{{ $p->name }}</option>@endforeach</select>
        <input name="reference" class="input" placeholder="Referencia de pago (opcional)">
        <label class="flex gap-2"><input type="checkbox" name="courtesy" value="1"> Cortesía / prueba sin costo</label>
        <button class="btn-primary w-full">Activar y publicar</button>
    </form>

    <form method="post" action="{{ route('admin.doctors.access', $doctor) }}" class="card space-y-2 text-sm">
        @csrf
        <h2 class="font-semibold">Acceso del médico</h2>
        <p class="text-slate-600">{{ $doctor->user->email }}</p>
        <button class="btn-secondary w-full">Enviar correo de acceso</button>
    </form>

    @if ($doctor->changeRequests->isNotEmpty())
        <div class="card text-sm">
            <h2 class="font-semibold mb-2">Solicitudes del médico</h2>
            <ul class="space-y-3">
                @foreach ($doctor->changeRequests as $r)
                    <li>
                        <p class="whitespace-pre-line">{{ $r->message }}</p>
                        <p class="text-xs text-slate-500">{{ $r->created_at->format('d/m/Y') }} ·
                            @if ($r->status === 'done') <span class="text-brand-700">hecho</span>
                            @else <form method="post" action="{{ route('admin.changes.resolve', $r) }}" class="inline">@csrf<button class="text-amber-700 underline">marcar como hecho</button></form> @endif
                        </p>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</aside>
@endif
</div>
@endsection
