<?php

namespace App\Http\Controllers\Panel;

use App\Services\GoogleBusinessProfile;
use App\Services\MarketingAi;

class DashboardController extends PanelController
{
    public function __invoke(GoogleBusinessProfile $gbp, MarketingAi $ai)
    {
        $doctor = $this->doctor()->load(['plan', 'specialty', 'city']);

        return view('panel.dashboard', [
            'doctor' => $doctor,
            'gbpScore' => $gbp->profileScore($doctor),
            'aiRemaining' => $ai->remaining($doctor),
            'upcoming' => $doctor->appointments()->where('starts_at', '>=', now())
                ->whereIn('status', ['pending', 'confirmed'])->orderBy('starts_at')->limit(5)->get(),
            'stats' => [
                'citas_mes' => $doctor->appointments()->where('starts_at', '>=', now()->startOfMonth())->count(),
                'resenas' => $doctor->rating_count,
                'calificacion' => $doctor->rating_avg,
                'clics_google' => $doctor->reviews()->whereNotNull('google_invite_clicked_at')->count(),
            ],
        ]);
    }
}
