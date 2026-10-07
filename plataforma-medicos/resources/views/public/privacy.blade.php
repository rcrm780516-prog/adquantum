@extends('layouts.public')
@section('title', 'Aviso de privacidad | '.config('plataforma.nombre'))

@section('content')
<article class="max-w-3xl mx-auto px-4 py-12 prose">
    <h1 class="text-3xl font-bold mb-4">Aviso de privacidad</h1>
    <p class="text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded p-3 mb-6">
        Borrador base conforme a la LFPDPPP. Debe revisarlo un abogado antes de publicar y completar los datos del responsable.
    </p>
    <div class="space-y-4 text-slate-700">
        <p><strong>Responsable:</strong> [Razón social de Virtuoso Growth Marketing], con domicilio en [domicilio], es responsable del tratamiento de sus datos personales.</p>
        <p><strong>Datos que recabamos:</strong> nombre, teléfono, correo electrónico (opcional), fecha y hora de la cita y, en su caso, la opinión que usted publique. <strong>No recabamos datos de salud</strong> ni el motivo de consulta.</p>
        <p><strong>Finalidades:</strong> (1) agendar su cita y compartir sus datos de contacto con el médico elegido; (2) enviarle recordatorios de la cita; (3) invitarle a calificar la atención recibida.</p>
        <p><strong>Transferencias:</strong> sus datos se comparten únicamente con el médico con quien agenda. No vendemos sus datos.</p>
        <p><strong>Derechos ARCO:</strong> puede acceder, rectificar, cancelar u oponerse al tratamiento de sus datos escribiendo a [correo de privacidad].</p>
        <p><strong>Opiniones:</strong> se publican con su nombre e iniciales o el nombre que usted elija. Puede solicitar su eliminación en cualquier momento.</p>
        <p class="text-sm text-slate-500">Última actualización: {{ now()->toDateString() }}</p>
    </div>
</article>
@endsection
