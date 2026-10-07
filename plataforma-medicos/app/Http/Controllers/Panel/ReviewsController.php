<?php

namespace App\Http\Controllers\Panel;

use App\Models\Review;
use App\Services\MarketingAi;
use Illuminate\Http\Request;
use RuntimeException;

class ReviewsController extends PanelController
{
    public function index()
    {
        $doctor = $this->doctor();

        return view('panel.reviews', [
            'doctor' => $doctor,
            'reviews' => $doctor->reviews()->latest()->paginate(20),
        ]);
    }

    public function suggestReply(Review $review, MarketingAi $ai)
    {
        $doctor = $this->doctor();
        abort_unless($review->doctor_id === $doctor->id, 403);

        try {
            $generation = $ai->reviewReply($doctor, $review->rating, $review->comment);
        } catch (RuntimeException $e) {
            return $this->aiError($e);
        }

        return back()->with('suggestion', ['review_id' => $review->id, 'text' => $generation->output]);
    }

    public function reply(Request $request, Review $review)
    {
        abort_unless($review->doctor_id === $this->doctor()->id, 403);

        $review->update($request->validate(['doctor_reply' => ['required', 'string', 'max:1000']]));

        return back()->with('status', 'Respuesta publicada.');
    }
}
