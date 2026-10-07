<?php

namespace App\Http\Controllers\Panel;

use App\Models\Plan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/** Embudo de upgrade: cada interés se guarda como lead para el equipo de Virtuoso. */
class PlansController extends PanelController
{
    public function index(Request $request)
    {
        return view('panel.plans', [
            'doctor' => $this->doctor()->load('plan'),
            'plans' => Plan::where('is_public', true)->orderBy('sort')->get(),
            'motivo' => $request->query('motivo'),
        ]);
    }

    public function interest(Request $request)
    {
        $data = $request->validate([
            'plan_interest' => ['required', 'exists:plans,slug'],
            'source' => ['nullable', 'string', 'max:40'],
            'message' => ['nullable', 'string', 'max:1000'],
        ]);

        $doctor = $this->doctor()->load(['specialty', 'city']);

        $lead = $doctor->upgradeLeads()->create([
            'plan_interest' => $data['plan_interest'],
            'source' => $data['source'] ?: 'panel',
            'message' => $data['message'] ?? null,
        ]);

        try {
            Mail::raw(
                "Nuevo lead de upgrade #{$lead->id}\n\n{$doctor->displayName()} ({$doctor->specialty->name}, {$doctor->city->name})\n"
                ."Plan de interés: {$lead->plan_interest}\nOrigen: {$lead->source}\nWhatsApp: {$doctor->whatsapp}\nCorreo: {$doctor->email}\n\n"
                .($lead->message ?? ''),
                fn ($m) => $m->to(config('plataforma.virtuoso.email'))->subject("Lead de upgrade: {$doctor->displayName()}")
            );
        } catch (\Throwable $e) {
            Log::warning('No se pudo enviar el aviso de lead', ['lead' => $lead->id, 'error' => $e->getMessage()]);
        }

        return back()->with('status', '¡Listo! Un asesor de Virtuoso te contactará por WhatsApp en menos de 24 horas.');
    }
}
