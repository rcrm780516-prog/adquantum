<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Doctor;
use App\Services\MarketingAi;
use RuntimeException;
use Illuminate\Support\Facades\Auth;

abstract class PanelController extends Controller
{
    protected function doctor(): Doctor
    {
        $doctor = Auth::user()?->doctor;
        abort_unless($doctor, 403, 'Tu cuenta no tiene un perfil de médico.');

        return $doctor;
    }

    /** Si se acabó el cupo de IA, se manda a la página de planes (embudo de upgrade). */
    protected function aiError(RuntimeException $e)
    {
        if ($e->getMessage() === MarketingAi::LIMIT_REACHED) {
            return redirect()->route('panel.plans', ['motivo' => 'ia'])
                ->with('status', 'Llegaste al límite de IA de tu plan este mes.');
        }

        return back()->withErrors(['ia' => $e->getMessage()]);
    }
}
