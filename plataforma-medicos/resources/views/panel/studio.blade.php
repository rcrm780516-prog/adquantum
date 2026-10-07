@extends('layouts.panel')
@section('title', 'Estudio de anuncios')

@section('content')
<h1 class="text-2xl font-bold">Estudio de anuncios</h1>
<p class="text-slate-600">Crea imágenes y textos para tus anuncios en minutos. Anuncios este mes: <strong>{{ $creativesThisMonth }}/{{ $creativesLimit }}</strong> · IA: <strong>{{ $aiRemaining }}</strong> consultas.</p>

<div class="grid lg:grid-cols-[340px_1fr] gap-6 mt-6">
    {{-- Editor --}}
    <div class="card space-y-3">
        <h2 class="font-semibold">1. Diseña tu imagen</h2>
        <div><label class="label">Plantilla</label>
            <select id="tpl" class="input">
                <option value="clasica">Clásica (foto + texto)</option>
                <option value="aviso">Aviso de consulta</option>
                <option value="consejo">Consejo de salud</option>
            </select></div>
        <div><label class="label">Formato</label>
            <select id="fmt" class="input">
                <option value="post">Post 1080×1080 (Facebook/Instagram)</option>
                <option value="story">Historia 1080×1920</option>
                <option value="banner">Banner 1200×628 (Google/ficha)</option>
            </select></div>
        <div><label class="label">Título</label><input id="headline" class="input" maxlength="60" value="Consulta de {{ $doctor->specialty->name }}"></div>
        <div><label class="label">Texto</label><textarea id="body" class="input" rows="3" maxlength="140">Agenda tu cita en {{ $doctor->city->name }}. Atención profesional y cercana.</textarea></div>
        <div><label class="label">Llamado a la acción</label><input id="cta" class="input" maxlength="30" value="Agenda por WhatsApp"></div>
        <div class="grid grid-cols-2 gap-3">
            <div><label class="label">Color</label><input id="color" type="color" class="input h-10 p-1" value="#18756c"></div>
            <div><label class="label">Foto</label><input id="photo" type="file" accept="image/*" class="text-xs"></div>
        </div>
        <p class="text-xs text-slate-500">Se agrega automáticamente tu cédula profesional (requisito de COFEPRIS). Evita prometer resultados o usar fotos de antes/después.</p>
        <button id="download" class="btn-primary w-full">Descargar imagen</button>
        <p id="studio-msg" class="text-sm"></p>
    </div>

    <div class="card flex items-center justify-center bg-slate-100">
        <canvas id="canvas" class="max-w-full max-h-[70vh] shadow-lg" aria-label="Vista previa del anuncio"></canvas>
    </div>
</div>

{{-- Copys con IA --}}
<div class="grid lg:grid-cols-2 gap-6 mt-6">
    <form method="post" action="{{ route('panel.studio.copy') }}" class="card space-y-3">
        @csrf
        <h2 class="font-semibold">2. Escribe el texto del anuncio con IA</h2>
        <div><label class="label">¿Dónde se publicará?</label>
            <select name="channel" class="input">
                @foreach (['Facebook e Instagram', 'Google Ads', 'publicación en la ficha de Google', 'WhatsApp'] as $ch)<option @selected(old('channel') === $ch)>{{ $ch }}</option>@endforeach
            </select></div>
        <div><label class="label">¿Qué quieres lograr?</label>
            <textarea name="objective" class="input" rows="2" maxlength="300" required placeholder="Ej.: dar a conocer mi nueva consulta de revisión de lunares">{{ old('objective') }}</textarea></div>
        <button class="btn-primary" @disabled($aiRemaining <= 0)>✨ Generar 3 opciones</button>
        @if (session('copy'))
            <div class="rounded-lg bg-slate-50 border border-slate-200 p-3 whitespace-pre-line text-sm">{{ session('copy') }}</div>
        @endif
    </form>

    <div class="card space-y-3">
        <h2 class="font-semibold">3. Descripción para tu ficha de Google</h2>
        <p class="text-sm text-slate-600">Genera una descripción optimizada (máx. 750 caracteres) para pegar en tu Perfil de Empresa de Google.</p>
        <form method="post" action="{{ route('panel.studio.gbp') }}">@csrf<button class="btn-secondary" @disabled($aiRemaining <= 0)>✨ Generar descripción</button></form>
        @if (session('gbp_description'))
            <div class="rounded-lg bg-slate-50 border border-slate-200 p-3 whitespace-pre-line text-sm">{{ session('gbp_description') }}</div>
        @endif
        @include('partials.upsell', ['title' => '¿Quieres que tus anuncios se publiquen solos?', 'text' => 'Virtuoso crea, publica y optimiza tus campañas en Meta y Google con presupuesto controlado.', 'source' => 'studio', 'cta' => 'Quiero campañas gestionadas'])
    </div>
</div>
@endsection

@php
    $canvasData = [
        'name' => $doctor->displayName(),
        'specialty' => $doctor->specialty->name,
        'cedula' => $doctor->cedula_profesional,
        'phone' => $doctor->whatsapp ?: $doctor->phone,
    ];
@endphp

@push('scripts')
<script>
(() => {
    const doctor = {{ Js::from($canvasData) }};
    const sizes = { post: [1080, 1080], story: [1080, 1920], banner: [1200, 628] };
    const $ = (id) => document.getElementById(id);
    const canvas = $('canvas'), ctx = canvas.getContext('2d');
    let photo = null;

    function wrap(text, x, y, maxWidth, lineHeight) {
        const words = text.split(' '); let line = '';
        for (const w of words) {
            const test = line ? line + ' ' + w : w;
            if (ctx.measureText(test).width > maxWidth && line) { ctx.fillText(line, x, y); line = w; y += lineHeight; }
            else line = test;
        }
        ctx.fillText(line, x, y);
        return y + lineHeight;
    }

    function draw() {
        const [W, H] = sizes[$('fmt').value];
        canvas.width = W; canvas.height = H;
        const color = $('color').value, tpl = $('tpl').value;
        const s = Math.min(W, H) / 1080; // escala tipográfica
        const pad = 70 * s;

        ctx.fillStyle = tpl === 'consejo' ? '#ffffff' : color;
        ctx.fillRect(0, 0, W, H);

        if (photo && tpl !== 'consejo') {
            const area = W > H ? [W * 0.45, 0, W * 0.55, H] : [0, H * 0.45, W, H * 0.55];
            const [ax, ay, aw, ah] = area;
            const r = Math.max(aw / photo.width, ah / photo.height);
            const pw = photo.width * r, ph = photo.height * r;
            ctx.save(); ctx.beginPath(); ctx.rect(ax, ay, aw, ah); ctx.clip();
            ctx.drawImage(photo, ax + (aw - pw) / 2, ay + (ah - ph) / 2, pw, ph);
            ctx.restore();
        }
        if (tpl === 'consejo') { ctx.fillStyle = color; ctx.fillRect(0, 0, W, 24 * s); ctx.fillRect(0, H - 24 * s, W, 24 * s); }

        const textColor = tpl === 'consejo' ? '#0f172a' : '#ffffff';
        const maxW = (W > H && photo && tpl !== 'consejo') ? W * 0.45 - pad * 2 : W - pad * 2;
        let y = pad + 40 * s;

        ctx.fillStyle = tpl === 'consejo' ? color : 'rgba(255,255,255,.85)';
        ctx.font = `600 ${30 * s}px Inter, sans-serif`;
        ctx.fillText(tpl === 'aviso' ? 'AVISO' : tpl === 'consejo' ? 'CONSEJO DE SALUD' : doctor.specialty.toUpperCase(), pad, y);
        y += 70 * s;

        ctx.fillStyle = textColor;
        ctx.font = `700 ${72 * s}px Inter, sans-serif`;
        y = wrap($('headline').value, pad, y, maxW, 84 * s) + 10 * s;
        ctx.font = `400 ${38 * s}px Inter, sans-serif`;
        y = wrap($('body').value, pad, y, maxW, 50 * s) + 30 * s;

        const cta = $('cta').value;
        if (cta) {
            ctx.font = `600 ${34 * s}px Inter, sans-serif`;
            const bw = ctx.measureText(cta).width + 60 * s;
            ctx.fillStyle = tpl === 'consejo' ? color : '#ffffff';
            ctx.beginPath(); ctx.roundRect(pad, y, bw, 76 * s, 38 * s); ctx.fill();
            ctx.fillStyle = tpl === 'consejo' ? '#ffffff' : color;
            ctx.fillText(cta, pad + 30 * s, y + 50 * s);
        }

        // Pie con nombre, teléfono y cédula (obligatorio en publicidad de servicios de salud).
        const footerH = 110 * s;
        ctx.fillStyle = 'rgba(0,0,0,.55)';
        ctx.fillRect(0, H - footerH, W, footerH);
        ctx.fillStyle = '#ffffff';
        ctx.font = `600 ${32 * s}px Inter, sans-serif`;
        ctx.fillText(doctor.name + (doctor.phone ? ' · ' + doctor.phone : ''), pad, H - footerH + 46 * s);
        ctx.font = `400 ${24 * s}px Inter, sans-serif`;
        ctx.fillText('Cédula profesional ' + doctor.cedula, pad, H - footerH + 86 * s);
    }

    ['tpl', 'fmt', 'headline', 'body', 'cta', 'color'].forEach((id) => $(id).addEventListener('input', draw));
    $('photo').addEventListener('change', (e) => {
        const file = e.target.files[0]; if (!file) return;
        const img = new Image(); img.onload = () => { photo = img; draw(); }; img.src = URL.createObjectURL(file);
    });

    $('download').addEventListener('click', async () => {
        const msg = $('studio-msg');
        const res = await fetch(@json(route('panel.studio.creative')), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
            body: JSON.stringify({ template: $('tpl').value, format: $('fmt').value, data: { headline: $('headline').value, body: $('body').value, cta: $('cta').value, color: $('color').value } }),
        });
        const json = await res.json().catch(() => ({}));
        if (res.status === 402) { msg.innerHTML = 'Llegaste al límite de anuncios de tu plan. <a class="underline" href="' + json.upgrade_url + '">Ver planes</a>'; return; }
        if (!res.ok) { msg.textContent = 'No se pudo guardar. Intenta de nuevo.'; return; }
        const a = document.createElement('a');
        a.download = 'anuncio-' + $('fmt').value + '.png';
        a.href = canvas.toDataURL('image/png');
        a.click();
        msg.textContent = 'Listo. Te quedan ' + json.remaining + ' anuncios este mes.';
    });

    document.fonts?.ready.then(draw); draw();
})();
</script>
@endpush
