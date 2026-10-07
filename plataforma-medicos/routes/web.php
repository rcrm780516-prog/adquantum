<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DirectoryController;
use App\Http\Controllers\DoctorController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Panel;
use App\Http\Controllers\ReviewController;
use Illuminate\Support\Facades\Route;

// ── Público (pacientes) ───────────────────────────────────────────────
Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/buscar', [HomeController::class, 'search'])->name('search');
Route::get('/para-medicos', [HomeController::class, 'forDoctors'])->name('for-doctors');
Route::get('/aviso-de-privacidad', [HomeController::class, 'privacy'])->name('privacy');
Route::get('/robots.txt', fn () => response(
    "User-agent: *\nDisallow: /panel\nDisallow: /calificar\nDisallow: /cita\n\nSitemap: ".route('sitemap')."\n"
)->header('Content-Type', 'text/plain'))->name('robots');
Route::get('/sitemap.xml', [DirectoryController::class, 'sitemap'])->name('sitemap');

Route::get('/medicos/{specialty}/{city?}', [DirectoryController::class, 'index'])->name('directory');
Route::get('/medico/{doctor}', [DoctorController::class, 'show'])->name('doctors.show');
Route::post('/medico/{doctor}/citas', [AppointmentController::class, 'store'])
    ->middleware('throttle:10,1')->name('appointments.store');
Route::get('/cita/{token}', [AppointmentController::class, 'confirmed'])->name('appointments.confirmed');

Route::get('/calificar/{token}', [ReviewController::class, 'create'])->name('reviews.create');
Route::post('/calificar/{token}', [ReviewController::class, 'store'])->middleware('throttle:5,1')->name('reviews.store');
Route::get('/calificar/{token}/gracias', [ReviewController::class, 'thanks'])->name('reviews.thanks');
Route::get('/calificar/{token}/google', [ReviewController::class, 'google'])->name('reviews.google');

// ── Acceso (las cuentas las crea el equipo de Virtuoso) ───────────────
Route::middleware('guest')->group(function () {
    Route::get('/entrar', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/entrar', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::get('/olvide-mi-contrasena', [AuthController::class, 'showForgot'])->name('password.request');
    Route::post('/olvide-mi-contrasena', [AuthController::class, 'sendResetLink'])->middleware('throttle:5,1')->name('password.email');
    Route::get('/contrasena/{token}', [AuthController::class, 'showReset'])->name('password.reset');
    Route::post('/contrasena', [AuthController::class, 'reset'])->middleware('throttle:10,1')->name('password.update');
});
Route::post('/salir', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

// ── Panel del médico ──────────────────────────────────────────────────
Route::middleware('auth')->prefix('panel')->name('panel.')->group(function () {
    Route::get('/', Panel\DashboardController::class)->name('dashboard');

    Route::get('/perfil', [Panel\ProfileController::class, 'show'])->name('profile');
    Route::post('/perfil/solicitar-cambio', [Panel\ProfileController::class, 'requestChange'])->name('profile.request');

    Route::get('/citas', [Panel\AppointmentsController::class, 'index'])->name('appointments');
    Route::patch('/citas/{appointment}', [Panel\AppointmentsController::class, 'update'])->name('appointments.update');

    Route::get('/resenas', [Panel\ReviewsController::class, 'index'])->name('reviews');
    Route::post('/resenas/{review}/sugerir', [Panel\ReviewsController::class, 'suggestReply'])->name('reviews.suggest');
    Route::put('/resenas/{review}/responder', [Panel\ReviewsController::class, 'reply'])->name('reviews.reply');

    Route::get('/estudio', [Panel\StudioController::class, 'index'])->name('studio');
    Route::post('/estudio/copy', [Panel\StudioController::class, 'copy'])->name('studio.copy');
    Route::post('/estudio/creativo', [Panel\StudioController::class, 'saveCreative'])->name('studio.creative');
    Route::post('/estudio/ficha-google', [Panel\StudioController::class, 'gbpDescription'])->name('studio.gbp');

    Route::get('/asesor', [Panel\AdvisorController::class, 'index'])->name('advisor');
    Route::post('/asesor', [Panel\AdvisorController::class, 'ask'])->name('advisor.ask');

    Route::get('/planes', [Panel\PlansController::class, 'index'])->name('plans');
    Route::post('/planes/interes', [Panel\PlansController::class, 'interest'])->name('plans.interest');
});

// ── Equipo de Virtuoso ────────────────────────────────────────────────
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', Admin\DashboardController::class)->name('dashboard');

    Route::get('/medicos', [Admin\DoctorsController::class, 'index'])->name('doctors.index');
    Route::get('/medicos/nuevo', [Admin\DoctorsController::class, 'create'])->name('doctors.create');
    Route::post('/medicos', [Admin\DoctorsController::class, 'store'])->name('doctors.store');
    Route::get('/medicos/{doctor}', [Admin\DoctorsController::class, 'edit'])->name('doctors.edit');
    Route::put('/medicos/{doctor}', [Admin\DoctorsController::class, 'update'])->name('doctors.update');
    Route::post('/medicos/{doctor}/plan', [Admin\DoctorsController::class, 'activatePlan'])->name('doctors.plan');
    Route::post('/medicos/{doctor}/calendario', [Admin\DoctorsController::class, 'checkCalendar'])->name('doctors.calendar');
    Route::post('/medicos/{doctor}/acceso', [Admin\DoctorsController::class, 'resendAccess'])->name('doctors.access');

    Route::get('/leads', [Admin\LeadsController::class, 'index'])->name('leads');
    Route::patch('/leads/{lead}', [Admin\LeadsController::class, 'update'])->name('leads.update');
    Route::post('/solicitudes/{changeRequest}/resolver', [Admin\ChangeRequestsController::class, 'resolve'])->name('changes.resolve');
});
