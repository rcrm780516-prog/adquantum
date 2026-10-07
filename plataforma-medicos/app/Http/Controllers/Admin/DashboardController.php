<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\ChangeRequest;
use App\Models\Doctor;
use App\Models\UpgradeLead;

class DashboardController extends Controller
{
    public function __invoke()
    {
        return view('admin.dashboard', [
            'stats' => [
                'medicos' => Doctor::count(),
                'publicados' => Doctor::where('is_published', true)->count(),
                'citas_mes' => Appointment::where('created_at', '>=', now()->startOfMonth())->count(),
                'leads_nuevos' => UpgradeLead::where('status', 'new')->count(),
            ],
            'changeRequests' => ChangeRequest::with('doctor')->where('status', 'pending')->oldest()->limit(20)->get(),
            'leads' => UpgradeLead::with('doctor')->where('status', 'new')->latest()->limit(10)->get(),
            'attention' => Doctor::where(function ($q) {
                $q->where(fn ($q) => $q->whereNotNull('google_calendar_id')->whereNotNull('google_calendar_error'))
                    ->orWhere(fn ($q) => $q->whereNotNull('plan_expires_at')->whereBetween('plan_expires_at', [now(), now()->addDays(30)]));
            })->limit(20)->get(),
        ]);
    }
}
