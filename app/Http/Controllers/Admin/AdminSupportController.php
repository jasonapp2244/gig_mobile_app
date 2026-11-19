<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SupportEmail;
use App\Mail\SupportReplyMail;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

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

    /**
     * Email the admin's reply to the customer (Reply-To: the support mailbox) and keep it on the ticket.
     * The status is left as it is so the ticket stays in the support list.
     */
    public function respond(Request $request, $id)
    {
        $request->validate([
            'response' => 'required|string|max:5000',
        ]);

        $support = SupportEmail::findOrFail($id);

        try {
            Mail::to($support->email)->send(new SupportReplyMail($support, $request->response));
        } catch (\Throwable $e) {
            Log::error('Support reply email failed: ' . $e->getMessage(), ['support_id' => $support->id]);

            return redirect()->route('support.show', $support->id)
                ->withInput()
                ->with('error', 'The reply could not be sent. Please try again or reply from the support mailbox.');
        }

        $support->update([
            'response'     => $request->response,
            'responded_at' => Carbon::now(),
        ]);

        return redirect()->route('support.show', $support->id)
            ->with('success', 'Reply sent to ' . $support->email . '.');
    }
}
