<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChangeRequest;
use Illuminate\Http\Request;

class ChangeRequestsController extends Controller
{
    public function resolve(Request $request, ChangeRequest $changeRequest)
    {
        $changeRequest->update(['status' => 'done', 'resolved_by' => $request->user()->id, 'resolved_at' => now()]);

        return back()->with('status', 'Solicitud marcada como resuelta.');
    }
}
