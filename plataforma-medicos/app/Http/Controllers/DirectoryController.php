<?php

namespace App\Http\Controllers;

use App\Models\City;
use App\Models\Doctor;
use App\Models\Specialty;

/** Páginas "especialidad + ciudad": son las que posicionan en Google ("dermatólogo en Culiacán"). */
class DirectoryController extends Controller
{
    public function index(Specialty $specialty, ?City $city = null)
    {
        $doctors = Doctor::published()
            ->with(['specialty', 'city'])
            ->where('specialty_id', $specialty->id)
            ->when($city, fn ($q) => $q->where('city_id', $city->id))
            ->orderByDesc('rating_avg')
            ->orderByDesc('rating_count')
            ->paginate(20);

        $title = $specialty->name.($city ? ' en '.$city->name : ' en México');

        return view('public.directory', compact('specialty', 'city', 'doctors', 'title'));
    }

    public function sitemap()
    {
        $doctors = Doctor::published()->select(['slug', 'updated_at'])->get();
        $combos = Doctor::published()
            ->join('specialties', 'specialties.id', '=', 'doctors.specialty_id')
            ->join('cities', 'cities.id', '=', 'doctors.city_id')
            ->select('specialties.slug as specialty', 'cities.slug as city')
            ->distinct()->get();

        return response()
            ->view('public.sitemap', compact('doctors', 'combos'))
            ->header('Content-Type', 'application/xml');
    }
}
