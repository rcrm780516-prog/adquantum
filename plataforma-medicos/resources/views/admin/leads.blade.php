@extends('layouts.admin')
@section('title', 'Leads de upgrade')

@php
    $labels = ['new' => 'Nuevo', 'contacted' => 'Contactado', 'won' => 'Ganado', 'lost' => 'Perdido'];
@endphp

@section('content')
<h1 class="text-2xl font-bold">Leads de upgrade</h1>
<p class="text-slate-600">Médicos que pidieron más: un plan mayor o hablar con Virtuoso.</p>
<div class="flex gap-2 mt-4 text-sm">
    <a href="{{ route('admin.leads') }}" class="btn-secondary py-1">Todos</a>
    @foreach ($labels as $k => $v)<a href="{{ route('admin.leads', ['estado' => $k]) }}" class="btn-secondary py-1">{{ $v }}</a>@endforeach
</div>
<div class="card mt-4 p-0 overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 text-left text-slate-500"><tr><th class="p-3">Fecha</th><th class="p-3">Médico</th><th class="p-3">Interés</th><th class="p-3">Origen</th><th class="p-3">WhatsApp</th><th class="p-3">Estado</th></tr></thead>
        <tbody class="divide-y divide-slate-100">
        @forelse ($leads as $lead)
            <tr>
                <td class="p-3 whitespace-nowrap">{{ $lead->created_at->format('d/m/Y H:i') }}</td>
                <td class="p-3"><a class="text-brand-700 underline" href="{{ route('admin.doctors.edit', $lead->doctor) }}">{{ $lead->doctor->displayName() }}</a><br><span class="text-xs text-slate-500">{{ $lead->doctor->specialty->name }} · {{ $lead->doctor->city->name }}</span></td>
                <td class="p-3">{{ $lead->plan_interest }}</td>
                <td class="p-3">{{ $lead->source }}</td>
                <td class="p-3">@if ($lead->doctor->whatsapp)<a class="underline" target="_blank" href="https://wa.me/{{ app(\App\Services\WhatsAppCloud::class)->normalize($lead->doctor->whatsapp) }}">{{ $lead->doctor->whatsapp }}</a>@endif</td>
                <td class="p-3">
                    <form method="post" action="{{ route('admin.leads.update', $lead) }}">@csrf @method('patch')
                        <select name="status" class="input py-1" onchange="this.form.submit()">@foreach ($labels as $k => $v)<option value="{{ $k }}" @selected($lead->status === $k)>{{ $v }}</option>@endforeach</select>
                    </form>
                </td>
            </tr>
        @empty
            <tr><td colspan="6" class="p-6 text-center text-slate-500">Sin leads.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $leads->links() }}</div>
@endsection
