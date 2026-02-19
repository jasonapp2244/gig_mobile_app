<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SupportEmail;
use Carbon\Carbon;

use function Laravel\Prompts\alert;

class AdminSupportController extends Controller
{
  // index() - normal page render
public function index()
{
    $supports = SupportEmail::where('status', 'sent')
        ->latest()
        ->get();

    return view('admin.support_index', compact('supports'));
}

// fetchSupports() - AJAX endpoint (returns JSON)
  public function fetchSupports()
{
    $supports = SupportEmail::where('status', 'sent')
        ->orderBy('created_at', 'desc')
        ->get()
        ->map(function ($support) {
            $support->created_at_formatted = $support->created_at
                ->timezone(config('app.timezone'))
                ->format('d F Y');
            return $support;
        });

    return response()->json([
        'success' => true,
        'supports' => $supports
    ]);
}


    public function show($id)
    {
        $support = SupportEmail::findOrFail($id);

        if (!$support->is_read) {
            $support->update(['is_read' => 1]);
        }

        return view('admin.support_show', compact('support'));
    }

    public function respond(Request $request, $id)
    {
        $request->validate([
            'response' => 'required|string',
        ]);

        $support = SupportEmail::findOrFail($id);

        $support->update([
            'response'      => $request->response,
            'status'        => 'responded',
            'responded_at'  => Carbon::now(),
        ]);

        return redirect()->route('support.show', $support->id)
            ->with('success', 'Response saved successfully.');
    }
}
