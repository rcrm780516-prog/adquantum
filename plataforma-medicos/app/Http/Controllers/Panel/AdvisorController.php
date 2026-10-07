<?php

namespace App\Http\Controllers\Panel;

use App\Services\MarketingAi;
use Illuminate\Http\Request;
use RuntimeException;

class AdvisorController extends PanelController
{
    public function index(MarketingAi $ai)
    {
        $doctor = $this->doctor();

        return view('panel.advisor', [
            'aiRemaining' => $ai->remaining($doctor),
            'history' => $doctor->aiGenerations()->where('type', 'advice')->latest()->limit(10)->get(),
        ]);
    }

    public function ask(Request $request, MarketingAi $ai)
    {
        $data = $request->validate(['question' => ['required', 'string', 'max:500']]);

        try {
            $ai->advice($this->doctor(), $data['question']);
        } catch (RuntimeException $e) {
            return $this->aiError($e);
        }

        return back();
    }
}
