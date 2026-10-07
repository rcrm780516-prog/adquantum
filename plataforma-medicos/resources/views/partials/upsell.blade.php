{{-- Bloque de upgrade reutilizable: lleva al médico de la herramienta "lite" al servicio de Virtuoso. --}}
<div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm">
    <p class="font-medium text-amber-900">{{ $title ?? '¿Quieres resultados más rápido?' }}</p>
    <p class="text-amber-800 mt-1">{{ $text ?? 'El equipo de Virtuoso Growth Marketing puede manejar tus campañas, contenido y ficha de Google por ti.' }}</p>
    <form method="post" action="{{ route('panel.plans.interest') }}" class="mt-3">
        @csrf
        <input type="hidden" name="plan_interest" value="{{ $plan ?? 'virtuoso' }}">
        <input type="hidden" name="source" value="{{ $source ?? 'panel' }}">
        <button class="btn-primary bg-amber-600 hover:bg-amber-700">{{ $cta ?? 'Quiero que me contacten' }}</button>
    </form>
</div>
