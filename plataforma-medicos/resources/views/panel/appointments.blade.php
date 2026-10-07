@extends('layouts.panel')
@section('title', 'Citas')

@php($labels = ['pending' => 'Pendiente', 'confirmed' => 'Confirmada', 'completed' => 'Atendida', 'cancelled' => 'Cancelada', 'no_show' => 'No asistió'])

@section('content')
<h1 class="text-2xl font-bold">Citas</h1>
<div class="card mt-6 overflow-x-auto p-0">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 text-left text-slate-500"><tr><th class="p-3">Fecha</th><th class="p-3">Paciente</th><th class="p-3">WhatsApp</th><th class="p-3">Estado</th></tr></thead>
        <tbody class="divide-y divide-slate-100">
        @forelse ($appointments as $a)
            <tr>
                <td class="p-3 whitespace-nowrap">{{ $a->starts_at->locale('es')->isoFormat('ddd D MMM YYYY, HH:mm') }}</td>
                <td class="p-3">{{ $a->patient_name }}</td>
                <td class="p-3"><a class="text-brand-700 underline" href="https://wa.me/{{ app(\App\Services\WhatsAppCloud::class)->normalize($a->patient_phone) }}" target="_blank" rel="noopener">{{ $a->patient_phone }}</a></td>
                <td class="p-3">
                    <form method="post" action="{{ route('panel.appointments.update', $a) }}">@csrf @method('patch')
                        <select name="status" class="input py-1" onchange="this.form.submit()">
                            @foreach ($labels as $value => $label)<option value="{{ $value }}" @selected($a->status === $value) @disabled($value === 'pending')>{{ $label }}</option>@endforeach
                        </select>
                    </form>
                </td>
            </tr>
        @empty
            <tr><td colspan="4" class="p-6 text-center text-slate-500">Todavía no tienes citas.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $appointments->links() }}</div>
@endsection
