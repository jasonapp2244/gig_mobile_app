<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class AdminAuthController extends Controller
{
    public function index()
    {
        return view('admin.signin');
    }
    public function signin(Request $request)
    {
        try {
            $request->validate([
                'email'    => 'required|email',
                'password' => 'required|min:8',
            ]);
            $credentials = [
                'email'    => trim($request->email),
                'password' => trim($request->password),
            ];
            if (Auth::guard('admin')->attempt($credentials)) {
                $user = Auth::guard('admin')->user();
                Log::info('Admin login successful for user: ' . $user->email . ', role: ' . $user->role);
                if ($user->role !== 'admin') {

                    Auth::guard('admin')->logout();
                    Log::warning('Access denied for user: ' . $user->email . ', role: ' . $user->role);

                    return back()->withErrors([
                        'email' => 'Access denied. Admin privileges required.',
                    ])->onlyInput('email');
                }
                $request->session()->regenerate();
                return redirect()->route('admin.dashboard')
                    ->with('success', 'Welcome back, Admin!');
            }
            return back()->withErrors([
                'email' => 'Invalid Email or Password.',
            ])->onlyInput('email');
        } catch (\Throwable $e) {
            Log::error('Admin login error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            return back()->withErrors([
                'email' => 'Something went wrong. Please try again later.',
            ])->onlyInput('email');
        }
    }
    public function logout(Request $request)
    {
        Auth::guard('admin')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('admin.login');
    }
}
