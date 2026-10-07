<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Review;
use Illuminate\Http\Request;

/**
 * Flujo de reseñas que cumple con la política de Google (sin "review gating"):
 * a TODOS los pacientes se les invita igual a reseñar en Google, sin importar su calificación.
 */
class ReviewController extends Controller
{
    public function create(string $token)
    {
        $appointment = $this->appointment($token);

        if ($appointment->review) {
            return redirect()->route('reviews.thanks', $token);
        }

        return view('public.review-form', compact('appointment'));
    }

    public function store(Request $request, string $token)
    {
        $appointment = $this->appointment($token);
        abort_if($appointment->review()->exists(), 409);

        $data = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:1000'],
            'patient_name' => ['nullable', 'string', 'max:60'],
        ]);

        $review = $appointment->doctor->reviews()->create([
            'appointment_id' => $appointment->id,
            'patient_name' => ($data['patient_name'] ?? null) ?: $this->initials($appointment->patient_name),
            'rating' => $data['rating'],
            'comment' => $data['comment'] ?? null,
            'is_verified' => true,
            'status' => 'published',
        ]);

        $appointment->doctor->recalculateRating();

        return redirect()->route('reviews.thanks', $token);
    }

    public function thanks(string $token)
    {
        $appointment = $this->appointment($token);
        $review = $appointment->review;
        abort_unless($review, 404);

        // Misma pantalla y mismo botón de Google para cualquier calificación.
        return view('public.review-thanks', [
            'doctor' => $appointment->doctor,
            'review' => $review,
        ]);
    }

    public function google(string $token)
    {
        $appointment = $this->appointment($token);
        $url = $appointment->doctor->googleReviewUrl();
        abort_unless($url, 404);

        $appointment->review?->forceFill(['google_invite_clicked_at' => now()])->save();

        return redirect()->away($url);
    }

    private function appointment(string $token): Appointment
    {
        return Appointment::with(['doctor', 'review'])
            ->where('review_token', $token)
            ->whereIn('status', ['confirmed', 'completed'])
            ->where('starts_at', '<=', now())
            ->firstOrFail();
    }

    /** "María Fernanda López" -> "María F. L." para proteger la privacidad. */
    private function initials(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name));
        $first = array_shift($parts);

        return trim($first.' '.implode(' ', array_map(fn ($p) => mb_substr($p, 0, 1).'.', $parts)));
    }
}
