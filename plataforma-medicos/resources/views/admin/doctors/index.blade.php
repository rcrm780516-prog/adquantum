@extends('layouts.admin')
@section('title', 'Médicos')

@section('content')
<div class="flex flex-wrap justify-between items-center gap-3">
    <h1 class="text-2xl font-bold">Médicos</h1>
    <div class="flex gap-2">
        <form><input name="q" value="{{ request('q') }}" class="input" placeholder="Buscar por nombre"></form>
        <a href="{{ route('admin.doctors.create') }}" class="btn-primary">+ Nuevo médico</a>
    </div>
</div>

<div class="card mt-6 p-0 overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 text-left text-slate-500"><tr>
            <th class="p-3">Médico</th><th class="p-3">Especialidad</th><th class="p-3">Plan</th><th class="p-3">Calendario</th><th class="p-3">Perfil</th><th class="p-3"></th>
        </tr></thead>
        <tbody class="divide-y divide-slate-100">
        @forelse ($doctors as $d)
            <tr>
                <td class="p-3"><a href="{{ route('admin.doctors.edit', $d) }}" class="font-medium text-brand-700 underline">{{ $d->displayName() }}</a><br><span class="text-xs text-slate-500">{{ $d->user->email }}</span></td>
                <td class="p-3">{{ $d->specialty->name }}<br><span class="text-xs text-slate-500">{{ $d->city->name }}</span></td>
                <td class="p-3">{{ $d->plan?->name ?? '—' }}@if($d->plan_expires_at)<br><span class="text-xs {{ $d->plan_expires_at->isPast() ? 'text-red-700' : 'text-slate-500' }}">hasta {{ $d->plan_expires_at->format('d/m/Y') }}</span>@endif</td>
                <td class="p-3">
                    @if (! $d->google_calendar_id) <span class="text-slate-400">Sin conectar</span>
                    @elseif ($d->google_calendar_error) <span class="text-red-700">Error</span>
                    @elseif ($d->google_calendar_checked_at) <span class="text-brand-700">✓ Conectado</span>
                    @else <span class="text-amber-700">Sin probar</span> @endif
                </td>
                <td class="p-3">{!! $d->is_published ? '<span class="text-brand-700">Publicado</span>' : '<span class="text-slate-400">Borrador</span>' !!}</td>
                <td class="p-3">@if ($d->pending_changes)<span class="rounded bg-amber-100 text-amber-800 px-2 py-0.5 text-xs">{{ $d->pending_changes }} cambio(s)</span>@endif</td>
            </tr>
        @empty
            <tr><td colspan="6" class="p-6 text-center text-slate-500">Aún no hay médicos. <a class="underline" href="{{ route('admin.doctors.create') }}">Da de alta el primero</a>.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $doctors->links() }}</div>
@endsection
