<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

abstract class Controller
{
    /**
     * Block guest users from accessing this endpoint.
     * Returns null if user is NOT a guest (allowed to proceed).
     * Returns JsonResponse if user IS a guest (blocked).
     */
    protected function blockGuest(): ?JsonResponse
    {
        if (auth()->check() && auth()->user()->is_guest) {
            return response()->json([
                'status'  => false,
                'message' => 'Please login first to access this feature.',
                'action'  => 'require_login'
            ], 403);
        }
        return null;
    }
}
