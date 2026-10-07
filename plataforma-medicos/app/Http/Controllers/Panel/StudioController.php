<?php

namespace App\Http\Controllers\Panel;

use App\Services\MarketingAi;
use Illuminate\Http\Request;
use RuntimeException;

/** Estudio de anuncios: plantillas por especialidad (canvas en el navegador) + copys con IA. */
class StudioController extends PanelController
{
    public function index(MarketingAi $ai)
    {
        $doctor = $this->doctor()->load('specialty', 'city');

        return view('panel.studio', [
            'doctor' => $doctor,
            'aiRemaining' => $ai->remaining($doctor),
            'creativesThisMonth' => $doctor->creatives()->where('created_at', '>=', now()->startOfMonth())->count(),
            'creativesLimit' => $doctor->plan?->creatives_monthly_limit ?? 0,
            'history' => $doctor->aiGenerations()->where('type', 'copy')->latest()->limit(5)->get(),
        ]);
    }

    public function copy(Request $request, MarketingAi $ai)
    {
        $data = $request->validate([
            'objective' => ['required', 'string', 'max:300'],
            'channel' => ['required', 'in:Facebook e Instagram,Google Ads,publicación en la ficha de Google,WhatsApp'],
        ]);

        try {
            $generation = $ai->adCopy($this->doctor(), $data['objective'], $data['channel']);
        } catch (RuntimeException $e) {
            return $this->aiError($e);
        }

        return back()->withInput()->with('copy', $generation->output);
    }

    public function saveCreative(Request $request)
    {
        $doctor = $this->doctor();
        $limit = $doctor->plan?->creatives_monthly_limit ?? 0;
        $used = $doctor->creatives()->where('created_at', '>=', now()->startOfMonth())->count();

        if ($used >= $limit) {
            return response()->json(['error' => 'limit', 'upgrade_url' => route('panel.plans', ['motivo' => 'creativos'])], 402);
        }

        $data = $request->validate([
            'template' => ['required', 'string', 'max:40'],
            'format' => ['required', 'in:post,story,banner'],
            'data' => ['required', 'array'],
        ]);

        $creative = $doctor->creatives()->create($data);

        return response()->json(['ok' => true, 'id' => $creative->id, 'remaining' => $limit - $used - 1]);
    }

    public function gbpDescription(MarketingAi $ai)
    {
        try {
            $generation = $ai->gbpDescription($this->doctor());
        } catch (RuntimeException $e) {
            return $this->aiError($e);
        }

        return back()->with('gbp_description', $generation->output);
    }
}
