<?php

namespace App\Http\Controllers;

use App\Models\City;
use App\Models\Doctor;
use App\Models\Specialty;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function showRegister()
    {
        return view('auth.register', [
            'specialties' => Specialty::orderBy('name')->get(),
            'cities' => City::orderBy('name')->get(),
        ]);
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'in:Dr.,Dra.'],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:160', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'cedula_profesional' => ['required', 'string', 'max:20'],
            'specialty_id' => ['required', 'exists:specialties,id'],
            'city_id' => ['required', 'exists:cities,id'],
            'whatsapp' => ['required', 'regex:/^[\d\s\-\+\(\)]{10,20}$/'],
            'terms' => ['accepted'],
        ]);

        $user = DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'role' => 'doctor',
            ]);

            Doctor::create([
                'user_id' => $user->id,
                'title' => $data['title'],
                'name' => $data['name'],
                'slug' => $this->uniqueSlug($data['title'].' '.$data['name']),
                'cedula_profesional' => $data['cedula_profesional'],
                'specialty_id' => $data['specialty_id'],
                'city_id' => $data['city_id'],
                'whatsapp' => $data['whatsapp'],
                'phone' => $data['whatsapp'],
                'email' => $data['email'],
                'is_published' => false, // se publica al activar el plan
            ]);

            return $user;
        });

        Auth::login($user);

        return redirect()->route('panel.profile')->with('status', '¡Bienvenido! Completa tu perfil para publicarlo.');
    }

    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'Correo o contraseña incorrectos.'])->onlyInput('email');
        }

        $request->session()->regenerate();

        return redirect()->intended(route('panel.dashboard'));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
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
