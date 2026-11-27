<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Contract\Auth as FirebaseAuth;
use Throwable;

/**
 * GET /api/firebase-token
 *
 * Gives the logged-in user a Firebase custom token whose uid is their user id, so the app can
 * signInWithCustomToken() and the chat rules can allow only a chat's participants (request.auth.uid).
 */
class FirebaseTokenController extends Controller
{
    public function show(): JsonResponse
    {
        if ($blocked = $this->blockGuest()) return $blocked;

        $uid = (string) auth()->id();

        try {
            $token = app(FirebaseAuth::class)->createCustomToken($uid);
        } catch (Throwable $e) {
            Log::error('Firebase custom token failed: ' . $e->getMessage(), ['user_id' => $uid]);

            return response()->json([
                'status'  => false,
                'message' => 'Chat sign-in is unavailable right now. Please try again later.',
            ], 503);
        }

        return response()->json([
            'status'         => true,
            'uid'            => $uid,
            'firebase_token' => $token->toString(),
            'expires_in'     => 3600,
        ]);
    }
}
