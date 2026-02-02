<?php

namespace App\Http\Controllers\Admin;

use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AdminUserController extends Controller
{

    // For Blade view
    public function users()
    {
        $users = User::where('role', 'user')
            ->orderByDesc('created_at')
            ->get();

        return view('admin.users', compact('users'));
    }

    // For AJAX refresh
    public function fetchUsers()
    {
        $users = User::where('role', 'user')
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'users'   => $users
        ]);
    }

    public function edit($id)
    {
        $user = User::findOrFail($id);
        return view('admin.user_edit', compact('user'));
    }


    public function update(Request $request, User $user)
    {
        $request->validate([
            'status' => 'required|in:active,inactive',
        ]);

        $user->update([
            'status' => $request->status,
        ]);

        return redirect()->route('admin.users')->with('success', 'User status updated successfully.');
    }
}
