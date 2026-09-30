<?php

namespace App\Http\Controllers\Admin;

use App\Models\User;
use App\Services\StatsExclusionService;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AdminUserController extends Controller
{
    public function __construct(protected StatsExclusionService $exclusion)
    {
    }

    // For Blade view
    public function users()
    {
        $users = $this->listedUsers();

        return view('admin.users', compact('users'));
    }

    // For AJAX refresh
    public function fetchUsers()
    {
        $users = $this->listedUsers();

        return response()->json([
            'success' => true,
            'users'   => $users
        ]);
    }

    /** App users for the Users Records list, without internal team / test accounts. */
    protected function listedUsers()
    {
        return User::where('role', 'user')
            ->whereNotIn('id', $this->exclusion->userIds())
            ->orderByDesc('created_at')
            ->get();
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
