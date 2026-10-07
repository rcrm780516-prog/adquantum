<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\UpgradeLead;
use Illuminate\Http\Request;

class LeadsController extends Controller
{
    public function index(Request $request)
    {
        return view('admin.leads', [
            'leads' => UpgradeLead::with('doctor.specialty', 'doctor.city')
                ->when($request->query('estado'), fn ($q, $s) => $q->where('status', $s))
                ->latest()->paginate(30)->withQueryString(),
        ]);
    }

    public function update(Request $request, UpgradeLead $lead)
    {
        $lead->update($request->validate(['status' => ['required', 'in:new,contacted,won,lost']]));

        return back()->with('status', 'Lead actualizado.');
    }
}
