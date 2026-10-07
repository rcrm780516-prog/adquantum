<?php

namespace App\Http\Controllers;

use App\Models\City;
use App\Models\Doctor;
use App\Models\Plan;
use App\Models\Specialty;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function index()
    {
        return view('public.home', [
            'specialties' => Specialty::orderBy('name')->get(),
            'cities' => City::orderBy('name')->get(),
            'featured' => Doctor::published()->with(['specialty', 'city'])
                ->orderByDesc('rating_avg')->orderByDesc('rating_count')->limit(6)->get(),
        ]);
    }

    public function search(Request $request)
    {
        $data = $request->validate([
            'especialidad' => ['required', 'exists:specialties,slug'],
            'ciudad' => ['nullable', 'exists:cities,slug'],
        ]);

        return redirect()->route('directory', array_filter([$data['especialidad'], $data['ciudad'] ?? null]));
    }

    public function forDoctors()
    {
        $whatsapp = preg_replace('/\D/', '', (string) config('plataforma.virtuoso.whatsapp'));
        $message = 'Hola, soy médico y me interesa aparecer en '.config('plataforma.nombre').'.';

        return view('public.for-doctors', [
            'plans' => Plan::where('is_public', true)->orderBy('sort')->get(),
            // El alta la hace el equipo de Virtuoso: el médico solo nos contacta.
            'contactUrl' => $whatsapp
                ? 'https://wa.me/'.$whatsapp.'?text='.rawurlencode($message)
                : 'mailto:'.config('plataforma.virtuoso.email').'?subject='.rawurlencode($message),
        ]);
    }

    public function privacy()
    {
        return view('public.privacy');
    }
}
