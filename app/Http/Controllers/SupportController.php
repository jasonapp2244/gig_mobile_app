<?php

namespace App\Http\Controllers;

use App\Mail\SupportMail;
use App\Models\SupportEmail;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use App\Mail\SupportConfirmationMail;

class SupportController extends Controller
{
    public function send(Request $request)
    {
        $name = Auth::user()->name;
        $email = Auth::user()->email;
        $data = $request->validate([
            'subject' => 'nullable|string|max:255',
            'message' => 'required|string',
        ]);

        $supportData = SupportEmail::create([
            'name' => $name,
            'email' => $email,
            'subject' => $data['subject'] ?? null,
            'message' => $data['message'],
            'status' => 'open',
        ]);

        $supportAddress = env('SUPPORT_MAIL', 'support@gmai.com');

        try {

            Mail::to($supportAddress)->send(new SupportMail($supportData));

        
            Mail::to($email)->send(new SupportConfirmationMail($supportData));

            $supportData->update([
                'status' => 'sent',
                'sent_at' => now(),
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Support request saved. Emails sent to support and user.',
                'data' => $supportData,
            ], 200);

        } catch (\Exception $e) {
            $supportData->update([
                'status' => 'failed',
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Failed to send support email',
                'error' => $e->getMessage(),
                'data' => $supportData,
            ], 500);
        }
    }
}
