<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\Doctor;
use App\Models\Plan;
use App\Models\Specialty;
use App\Models\User;
use App\Notifications\AccessInvitation;
use App\Services\GoogleBusinessProfile;
use App\Services\GoogleCalendar;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

/** Alta y configuración de médicos. Todo lo captura el equipo de Virtuoso. */
class DoctorsController extends Controller
{
    public function index(Request $request)
    {
        $doctors = Doctor::with(['specialty', 'city', 'plan', 'user'])
            ->withCount(['changeRequests as pending_changes' => fn ($q) => $q->where('status', 'pending')])
            ->when($request->query('q'), fn ($q, $term) => $q->where('name', 'like', "%{$term}%"))
            ->orderBy('name')
            ->paginate(30)
            ->withQueryString();

        return view('admin.doctors.index', compact('doctors'));
    }

    public function create(GoogleCalendar $calendar)
    {
        return view('admin.doctors.form', $this->formData(new Doctor(['title' => 'Dr.']), $calendar));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $doctor = DB::transaction(function () use ($data, $request) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Str::random(40), // el médico crea la suya con el correo de bienvenida
                'role' => 'doctor',
            ]);

            $doctor = new Doctor(['user_id' => $user->id, 'slug' => $this->uniqueSlug($data['title'].' '.$data['name'])]);
            $this->fill($doctor, $data, $request);

            return $doctor;
        });

        if ($request->boolean('send_access')) {
            $this->sendAccess($doctor);
        }

        return redirect()->route('admin.doctors.edit', $doctor)->with('status', 'Médico creado.'.($request->boolean('send_access') ? ' Se le envió el correo de acceso.' : ''));
    }

    public function edit(Doctor $doctor, GoogleCalendar $calendar, GoogleBusinessProfile $gbp)
    {
        $doctor->load(['schedules', 'user', 'plan', 'changeRequests' => fn ($q) => $q->latest()->limit(10)]);

        return view('admin.doctors.form', $this->formData($doctor, $calendar) + [
            'gbpScore' => $gbp->profileScore($doctor),
            'plans' => Plan::orderBy('sort')->get(),
        ]);
    }

    public function update(Request $request, Doctor $doctor)
    {
        $data = $this->validated($request, $doctor);
        $this->fill($doctor, $data, $request);
        $doctor->user->update(['name' => $data['name'], 'email' => $data['email']]);

        return back()->with('status', 'Perfil guardado.');
    }

    public function activatePlan(Request $request, Doctor $doctor)
    {
        $data = $request->validate([
            'plan_id' => ['required', 'exists:plans,id'],
            'reference' => ['nullable', 'string', 'max:120'],
            'courtesy' => ['nullable', 'boolean'],
        ]);
        $plan = Plan::findOrFail($data['plan_id']);
        $endsAt = $plan->billing_interval === 'month' ? now()->addMonth() : now()->addYear();

        $doctor->subscriptions()->create([
            'plan_id' => $plan->id,
            'provider' => 'manual',
            'provider_reference' => $data['reference'] ?? ($request->boolean('courtesy') ? 'Cortesía / prueba' : null),
            'amount_mxn' => $request->boolean('courtesy') ? 0 : $plan->price_mxn,
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => $endsAt,
        ]);
        $doctor->forceFill(['plan_id' => $plan->id, 'plan_expires_at' => $endsAt, 'is_published' => true])->save();

        return back()->with('status', "Plan {$plan->name} activo hasta {$endsAt->format('d/m/Y')}. El perfil ya está publicado.");
    }

    public function checkCalendar(Doctor $doctor, GoogleCalendar $calendar)
    {
        $error = $calendar->check($doctor);

        return back()->with('status', $error ? "⚠️ Google Calendar: {$error}" : '✅ Google Calendar conectado correctamente.');
    }

    public function resendAccess(Doctor $doctor)
    {
        $this->sendAccess($doctor);

        return back()->with('status', "Se envió el correo de acceso a {$doctor->user->email}.");
    }

    private function sendAccess(Doctor $doctor): void
    {
        try {
            $doctor->user->notify(new AccessInvitation(Password::createToken($doctor->user), welcome: true));
        } catch (Throwable $e) {
            report($e);
            session()->flash('status', 'El médico se guardó, pero no se pudo enviar el correo. Revisa la configuración de correo.');
        }
    }

    private function formData(Doctor $doctor, GoogleCalendar $calendar): array
    {
        return [
            'doctor' => $doctor,
            'specialties' => Specialty::orderBy('name')->get(),
            'cities' => City::orderBy('name')->get(),
            'serviceAccountEmail' => $calendar->serviceAccountEmail(),
        ];
    }

    private function validated(Request $request, ?Doctor $doctor = null): array
    {
        return $request->validate([
            'title' => ['required', 'in:Dr.,Dra.'],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:160', Rule::unique('users', 'email')->ignore($doctor?->user_id)],
            'specialty_id' => ['required', 'exists:specialties,id'],
            'city_id' => ['required', 'exists:cities,id'],
            'cedula_profesional' => ['required', 'string', 'max:20'],
            'cedula_especialidad' => ['nullable', 'string', 'max:20'],
            'bio' => ['nullable', 'string', 'max:2000'],
            'services_text' => ['nullable', 'string', 'max:1000'],
            'insurances_text' => ['nullable', 'string', 'max:500'],
            'consultation_price_mxn' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'phone' => ['nullable', 'string', 'max:20'],
            'whatsapp' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:200'],
            'neighborhood' => ['nullable', 'string', 'max:120'],
            'postal_code' => ['nullable', 'string', 'max:10'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'website' => ['nullable', 'url', 'max:200'],
            'google_place_id' => ['nullable', 'string', 'max:200'],
            'google_calendar_id' => ['nullable', 'string', 'max:200'],
            'photo' => ['nullable', 'image', 'max:3072'],
            'schedules' => ['array'],
            'schedules.*.enabled' => ['nullable'],
            'schedules.*.start_time' => ['nullable', 'date_format:H:i'],
            'schedules.*.end_time' => ['nullable', 'date_format:H:i'],
            'slot_minutes' => ['required', 'integer', 'in:15,20,30,45,60'],
        ]);
    }

    private function fill(Doctor $doctor, array $data, Request $request): void
    {
        $toList = fn (?string $text) => array_values(array_filter(array_map('trim', preg_split('/[\n,]+/', (string) $text))));

        $doctor->fill(collect($data)->except(['email', 'services_text', 'insurances_text', 'photo', 'schedules', 'slot_minutes'])->all());
        $doctor->email = $data['email'];
        $doctor->services = $toList($data['services_text'] ?? '');
        $doctor->insurances = $toList($data['insurances_text'] ?? '');

        if ($request->hasFile('photo')) {
            $doctor->photo_path = $request->file('photo')->store('doctors', 'public');
        }

        if ($doctor->isDirty('google_calendar_id')) {
            $doctor->google_calendar_checked_at = null;
            $doctor->google_calendar_error = null;
        }

        $doctor->save();

        $doctor->schedules()->delete();
        foreach ($data['schedules'] ?? [] as $weekday => $row) {
            if (! empty($row['enabled']) && ! empty($row['start_time']) && ! empty($row['end_time']) && $row['start_time'] < $row['end_time']) {
                $doctor->schedules()->create([
                    'weekday' => (int) $weekday,
                    'start_time' => $row['start_time'],
                    'end_time' => $row['end_time'],
                    'slot_minutes' => $data['slot_minutes'],
                ]);
            }
        }
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $i = 2;
        while (Doctor::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
