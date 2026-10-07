@extends('layouts.public')

@section('title', $doctor->displayName().' · '.$doctor->specialty->name.' en '.$doctor->city->name)
@section('description', \Illuminate\Support\Str::limit($doctor->bio ?: $doctor->displayName().', '.$doctor->specialty->name.' en '.$doctor->city->name.'. Agenda tu cita en línea.', 155))

@push('head')
<script type="application/ld+json">{!! json_encode($jsonLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
@endpush

@section('content')
<div class="max-w-6xl mx-auto px-4 py-10 grid lg:grid-cols-[1fr_380px] gap-8">
    <div class="space-y-6">
        <div class="card flex flex-col sm:flex-row gap-6">
            @if ($doctor->photoUrl())
                <img src="{{ $doctor->photoUrl() }}" alt="{{ $doctor->displayName() }}" class="w-32 h-32 rounded-full object-cover">
            @endif
            <div>
                <h1 class="text-3xl font-bold">{{ $doctor->displayName() }}</h1>
                <p class="text-lg text-slate-600">{{ $doctor->specialty->name }} en {{ $doctor->city->name }}</p>
                @if ($doctor->rating_count)
                    <p class="mt-2">@include('partials.stars', ['rating' => $doctor->rating_avg])
                        <strong>{{ number_format($doctor->rating_avg, 1) }}</strong>
                        <span class="text-slate-500">· {{ $doctor->rating_count }} reseñas verificadas</span></p>
                @endif
                <p class="text-sm text-slate-500 mt-2">Cédula profesional {{ $doctor->cedula_profesional }}
                    @if ($doctor->cedula_especialidad) · Cédula de especialidad {{ $doctor->cedula_especialidad }} @endif</p>
            </div>
        </div>

        @if ($doctor->bio)
        <section class="card">
            <h2 class="text-xl font-semibold mb-2">Sobre {{ $doctor->title === 'Dra.' ? 'la doctora' : 'el doctor' }}</h2>
            <p class="whitespace-pre-line text-slate-700">{{ $doctor->bio }}</p>
        </section>
        @endif

        @if ($doctor->services)
        <section class="card">
            <h2 class="text-xl font-semibold mb-2">Servicios</h2>
            <ul class="grid sm:grid-cols-2 gap-2">
                @foreach ($doctor->services as $service)<li class="flex gap-2"><span class="text-brand-600">✓</span>{{ $service }}</li>@endforeach
            </ul>
        </section>
        @endif

        <section class="card">
            <h2 class="text-xl font-semibold mb-2">Ubicación</h2>
            <p>{{ $doctor->address }}{{ $doctor->neighborhood ? ', '.$doctor->neighborhood : '' }}, {{ $doctor->city->name }}, {{ $doctor->city->state }}</p>
            @if ($doctor->insurances)<p class="text-sm text-slate-600 mt-2">Aseguradoras: {{ implode(', ', $doctor->insurances) }}</p>@endif
        </section>

        <section class="card">
            <h2 class="text-xl font-semibold mb-4">Opiniones de pacientes</h2>
            @forelse ($reviews as $review)
                <article class="border-b border-slate-100 last:border-0 py-3">
                    <p>@include('partials.stars', ['rating' => $review->rating]) <strong class="ml-1">{{ $review->patient_name }}</strong>
                        @if ($review->is_verified)<span class="text-xs text-brand-700 bg-brand-50 rounded px-1.5 py-0.5 ml-1">Cita verificada</span>@endif
                        <span class="text-xs text-slate-400 ml-1">{{ $review->created_at->locale('es')->diffForHumans() }}</span></p>
                    @if ($review->comment)<p class="mt-1 text-slate-700">{{ $review->comment }}</p>@endif
                    @if ($review->doctor_reply)<p class="mt-2 ml-4 pl-3 border-l-2 border-brand-100 text-sm text-slate-600"><strong>Respuesta:</strong> {{ $review->doctor_reply }}</p>@endif
                </article>
            @empty
                <p class="text-slate-500">Aún no hay opiniones.</p>
            @endforelse
        </section>
    </div>

    <aside id="agendar" class="lg:sticky lg:top-4 h-fit card">
        <h2 class="text-xl font-semibold">Agenda tu cita</h2>
        @if ($doctor->consultation_price_mxn)<p class="text-sm text-slate-600">Consulta: ${{ number_format($doctor->consultation_price_mxn) }} MXN</p>@endif

        @if ($days->isEmpty())
            <p class="mt-4 text-slate-600">No hay horarios disponibles esta semana.</p>
            @if ($doctor->whatsapp)
                <a class="btn-secondary mt-3 w-full" href="https://wa.me/{{ app(\App\Services\WhatsAppCloud::class)->normalize($doctor->whatsapp) }}" target="_blank" rel="noopener">Escribir por WhatsApp</a>
            @endif
        @else
        <form method="post" action="{{ route('appointments.store', $doctor) }}" class="mt-4 space-y-4">
            @csrf
            <fieldset>
                <legend class="label">Elige día y hora</legend>
                <div class="space-y-3 max-h-72 overflow-y-auto pr-1">
                    @foreach ($days as $date => $slots)
                        <div>
                            <p class="text-sm font-medium">{{ \Illuminate\Support\Str::ucfirst(\Carbon\Carbon::parse($date)->locale('es')->isoFormat('dddd D [de] MMMM')) }}</p>
                            <div class="flex flex-wrap gap-2 mt-1">
                                @foreach ($slots as $slot)
                                    <label class="cursor-pointer">
                                        <input type="radio" name="starts_at" value="{{ $slot->format('Y-m-d H:i:s') }}" class="peer sr-only" required @checked(old('starts_at') === $slot->format('Y-m-d H:i:s'))>
                                        <span class="block rounded-md border border-slate-300 px-2.5 py-1 text-sm peer-checked:bg-brand-600 peer-checked:text-white peer-checked:border-brand-600">{{ $slot->format('H:i') }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </fieldset>
            <div><label class="label" for="patient_name">Nombre completo</label><input class="input" id="patient_name" name="patient_name" value="{{ old('patient_name') }}" required maxlength="120"></div>
            <div><label class="label" for="patient_phone">WhatsApp</label><input class="input" id="patient_phone" name="patient_phone" value="{{ old('patient_phone') }}" required inputmode="tel" placeholder="10 dígitos"></div>
            <div><label class="label" for="patient_email">Correo (opcional)</label><input class="input" id="patient_email" type="email" name="patient_email" value="{{ old('patient_email') }}"></div>
            <label class="flex gap-2 text-sm text-slate-600">
                <input type="checkbox" name="privacy" value="1" required>
                <span>Acepto el <a href="{{ route('privacy') }}" target="_blank" class="underline">aviso de privacidad</a>. Solo se comparten mis datos de contacto con el médico.</span>
            </label>
            <button class="btn-primary w-full">Confirmar cita</button>
        </form>
        @endif
    </aside>
</div>

{{-- En celular el formulario queda al final: botón fijo para llegar a él. --}}
<a href="#agendar" class="lg:hidden fixed bottom-4 inset-x-4 btn-primary shadow-lg py-3">Agendar cita</a>
@endsection
