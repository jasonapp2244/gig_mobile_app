<?php

namespace App\Http\Controllers\Admin;

use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class AdminSettingController extends Controller
{
    /**
     * Show the admin profile view
     */
    public function settingAdminProfile()
    {
        $admin = Auth::user();
        return view('admin.view_profile', compact('admin'));
    }

    /**
     * Show edit profile form
     */
    public function editProfile($id)
    {
        // dd($id);
        $admin = User::findOrFail($id);
        return view('admin.edit_profile', compact('admin'));
    }

    /**
     * Update profile
     */
    public function updateProfile(Request $request)
    {
        $admin = Auth::user();

        $request->validate([
            'name'          => 'required|string|max:255',
            'email'         => 'required|email|max:255|unique:users,email,' . $admin->id,
            'phone'         => 'nullable|string|max:20',
            'address'       => 'nullable|string|max:255',
            'profile_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        $admin->name         = $request->name;
        $admin->email        = $request->email;
        $admin->phone_number = $request->phone;
        $admin->address_one  = $request->address;

        if ($request->hasFile('profile_image')) {
            if ($admin->profile_image && Storage::disk('public')->exists($admin->profile_image)) {
                Storage::disk('public')->delete($admin->profile_image);
            }

            $path = $request->file('profile_image')->store('profile_images', 'public');
            $admin->profile_image = $path;
        }

        $admin->save();

        return redirect()->route('setting.view.profile')
            ->with('success', trans('messages.profile_updated'));
    }

    /**
     * Show change password form
     */
    public function changePasswordForm()
    {
        return view('admin.change_password');
    }

    /**
     * Handle change password
     */
    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password'          => 'required',
            'new_password'              => 'required|min:8|confirmed',
            'new_password_confirmation' => 'required',
        ]);

        $admin = Auth::user();

        if (!Hash::check($request->current_password, $admin->password)) {
            return back()->withErrors(['current_password' => 'Current password is incorrect.']);
        }

        $admin->password = Hash::make($request->new_password);
        $admin->save();

        Auth::logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect()->route('admin.login')
            ->with('success', 'Password changed successfully. Please login with your new password.');
    }

}
