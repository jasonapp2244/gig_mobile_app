<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Http\Controllers\Controller;
use App\Http\Requests\AuthRequest;
use App\Mail\{
    WelcomeMail,
    forgotPasswordMail,
    NewUserNotificationMail
};
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class AuthController extends Controller
{

    /**
     * Register new user with OTP verification
     */
    public function getUser()
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => trans('messages.user_not_authenticated')
            ], 401);
        }

        return response()->json([
            'status' => true,
            'message' => trans('messages.user_retrieved_successfully'),
            'data' => $user
        ]);
    }
    public function signup(AuthRequest $request): JsonResponse
    {
        try {
            $existingUserByEmail = User::where('email', $request->email)->first();
            if ($existingUserByEmail) {
                if ($existingUserByEmail->otp_status === 'verified') {
                    return response()->json([
                        'status' => false,
                        'message' => 'Registration failed',
                    ], 400);
                }
                return response()->json([
                    'status' => false,
                    'message' => 'Please verify your email before registering'
                ], 400);
            }

            $existingUserByPhone = User::where('phone_number', $request->phone_number)->first();

            if ($existingUserByPhone) {

                if ($existingUserByPhone->otp_status === 'verified') {
                    return response()->json([
                        'status' => false,
                        'message' => 'This phone number is already registered.',
                    ], 400);
                }

                return response()->json([
                    'status' => false,
                    'message' => 'Please verify your phone before registering'
                ], 400);
            }

            $user = User::create([
                'name'            => $request->name,
                'email'           => $request->email,
                'password'        => Hash::make($request->password),
                'phone_number'    => $request->phone_number,
                'otp'             => rand(100000, 999999),
                'otp_status'      => 'unverified',
                'otp_expires_at'  => now()->addMinutes(10),
                'status'          => 'active',
            ]);

            // Send welcome email to user (wrapped in try-catch to prevent blocking signup)
            try {
                Mail::to($user->email)->send(new WelcomeMail($user, $user->otp));
            } catch (\Exception $mailException) {
                \Log::error('Failed to send welcome email: ' . $mailException->getMessage());
            }

            // Send notification to admin (non-blocking)
            try {
                $adminEmail = env('Admin_Email');
                if ($adminEmail && $adminEmail !== 'default_value') {
                    Mail::to($adminEmail)->queue(
                        new NewUserNotificationMail(
                            $user->name,
                            $user->email,
                            $user->phone_number,
                            $user->created_at->format('Y-m-d H:i:s')
                        )
                    );
                }
            } catch (\Exception $adminMailException) {
                \Log::error('Failed to send admin notification: ' . $adminMailException->getMessage());
            }

            return response()->json([
                'status' => true,
                'message' => 'otp_sent_to_email',
                'otp' => $user->otp,
                'data' => ['email' => $user->email]
            ], 200);
        } catch (\Exception $e) {
            return $this->handleError($e, 'Registration failed');
        }
    }
    /**
     * Resend OTP for email verification
     */

    public function resendOtp(AuthRequest $request): JsonResponse
    {
        try {
            $user = User::where('email', $request->email)->first();
            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'User not found with this email address'
                ], 400);
            }


            // Prevent OTP spamming - limit to 1 request per minute
            // if ($user->otp_expires_at && $user->otp_expires_at->diffInMinutes(now()) < 1) {
            //     return response()->json([
            //         'status' => false,
            //         'message' => 'Please wait before requesting a new OTP'
            //     ], 429);
            // }
            $newOtp = rand(100000, 999999);
            $user->update([
                'otp' => $newOtp,
                'otp_expires_at' => now()->addMinutes(10),
                'otp_status' => 'unverified'
            ]);
            // Send the new OTP via email
            Mail::to($user->email)->send(new WelcomeMail($user, $newOtp));

            return response()->json([
                'status' => true,
                'message' => 'New OTP has been sent to your email',
                'new_otp' => $newOtp,
                'data' => [
                    'email' => $user->email,
                    'otp_expires_at' => $user->otp_expires_at
                ]
            ], 200);
        } catch (\Exception $e) {
            return $this->handleError($e, 'Failed to resend OTP');
        }
    }

    /**
     * Verify OTP for email verification
     */
    public function verifyOtp(AuthRequest $request): JsonResponse
    {
        try {
            $user = User::where('email', $request->email)->firstOrFail();

            if ($user->otp !== $request->otp) {
                return response()->json([
                    'status' => false,
                    'message' => 'Invalid OTP or Email not found'
                ], 400);
            }
            if ($user->status == 'inactive') {
                return response()->json([
                    'status' => false,
                    'message' => 'Your account is inactive. Please contact support.'
                ], 400);
            }

            if (Carbon::parse($user->otp_expires_at)->isPast()) {
                return response()->json([
                    'status' => false,
                    'message' => 'OTP has expired'
                ], 400);
            }

            $user->update([
                'email_verified_at' => now(),
                'otp' => null,
                'otp_status' => 'verified',
                'otp_expires_at' => null,
                "fcm_token" => $request->fcm_token ?? null,
                'timezone'         => $request->timezone,
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Email verified successfully',
                'token' => $user->createToken('auth_token')->plainTextToken,
                'user' => $user->only(['id', 'name', 'email', 'phone_number', 'email_verified_at'])
            ]);
        } catch (\Exception $e) {
            return $this->handleError($e, 'OTP verification failed');
        }
    }

    //
    // public function login(Request $request)
    // {
    //     try {
    //         $request->validate([
    //             'email'    => 'required|email',
    //             'password' => 'required|string',
    //         ]);

    //         $user = User::where('email', $request->email)->first();

    //         if (!$user || !Hash::check($request->password, $user->password)) {
    //             return response()->json([
    //                 'status'  => false,
    //                 'message' => 'Invalid credentials'
    //             ], 400);
    //         }

    //         if (!$user->hasVerifiedEmail()) {
    //             return response()->json([
    //                 'status'  => false,
    //                 'message' => 'Please verify your email first'
    //             ], 400);
    //         }

    //         if ($user->status === 'inactive') {
    //             return response()->json([
    //                 'status'  => false,
    //                 'message' => 'Your account is inactive. Please contact support.'
    //             ], 400);
    //         }

    //         // update user status and login activity
    //         $user->update([
    //             'status'           => 'active',
    //             'last_login_at'    => now(),
    //             'last_activity_at' => now(),
    //             'fcm_token'        => $request->fcm_token ?? null,
    //             'device_type'      => $request->device_type ?? null,
    //             'timezone'         => $request->timezone ?? null,
    //         ]);

    //         $token = $user->createToken('auth_token')->plainTextToken;

    //         return response()->json([
    //             'status'  => true,
    //             'message' => 'Login successful',
    //             'token'   => $token,
    //             'user'    => $user->only([
    //                 'id',
    //                 'name',
    //                 'email',
    //                 'phone_number',
    //                 'status',
    //                 'last_login_at',
    //                 'last_activity_at',
    //                 'last_logout_time',
    //                 'fcm_token'
    //             ]),
    //         ]);
    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'status'  => false,
    //             'message' => 'Login failed',
    //             'error'   => $e->getMessage()
    //         ], 500);
    //     }
    // }





    /**
     * Social Login (Google, Facebook, Apple)
     */
     public function socialLogin(Request $request)
    {
        try {
            $request->validate([
                'service_provider'    => 'required|in:google,facebook,apple',
                'service_provider_id' => 'required|string',
                'email'               => 'nullable|email',
                'name'                => 'nullable|string',
                'fcm_token'           => 'nullable|string',
                'device_type'         => 'nullable|string',
                'timezone'            => 'required|string|timezone',
            ]);

            // Step 1: Try to find existing user by service provider ID (primary check)
            $user = User::where('service_provider', $request->service_provider)
                ->where('service_provider_id', trim($request->service_provider_id))
                ->first();

            // Step 2: If not found by social ID but email exists, check by email (to link accounts)
            if (!$user && $request->email) {
                $existingByEmail = User::where('email', $request->email)->first();

                if ($existingByEmail) {
                    // Link this social account to existing email account
                    $existingByEmail->update([
                        'service_provider'    => $request->service_provider,
                        'service_provider_id' => trim($request->service_provider_id),
                    ]);
                    $user = $existingByEmail;
                }
            }

            // Step 3: If user still doesn't exist → create new account
            if (!$user) {
                $user = User::create([
                    'name'                => $request->name ?? 'Guest',
                    'email'               => $request->email ?? null,
                    'service_provider'    => $request->service_provider,
                    'service_provider_id' => trim($request->service_provider_id),
                    'profile_image'       => $request->profile_image ?? 'default.jpg',
                    'email_verified_at'   => now(),
                    'otp_status'          => 'verified',
                    'status'              => 'active',
                    'fcm_token'           => $request->fcm_token ?? null,
                    'device_type'         => $request->device_type ?? null,
                    'timezone'            => $request->timezone,
                    'online_status'       => 'online',
                    'last_login_at'       => now(),
                    'last_activity_at'    => now(),
                ]);
            } else {
                // Step 4: User exists → update session/device info only
                $user->update([
                    'fcm_token'        => $request->fcm_token ?? $user->fcm_token,
                    'device_type'      => $request->device_type ?? $user->device_type,
                    'online_status'    => 'online',
                    'otp_status'       => 'verified',
                    'status'           => 'active',
                    'timezone'         => $request->timezone,
                    'last_login_at'    => now(),
                    'last_activity_at' => now(),
                ]);
            }

            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'status'  => true,
                'message' => 'Social login successful',
                'token'   => $token,
                'user'    => $user->only([
                    'id',
                    'name',
                    'email',
                    'phone_number',
                    'fcm_token',
                    'service_provider'
                ])
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status'  => false,
                'message' => 'Social login failed',
                'error'   => $e->getMessage()
            ], 500);
        }
    }


   public function login(Request $request)
{
    // dd("fdfd");
    try {
        // Step 1: validate only email and timezone first
        $request->validate([
            'email'    => 'required|email',
            'timezone' => 'required|string|timezone',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'status'  => false,
                'message' => 'User not found'
            ], 404);
        }

        // Step 2: If user is social login only (no password)
        if (!empty($user->service_provider) && ($user->password == NULL)) {
            return response()->json([
                'status'  => false,
                'message' => 'Your account was created using ' . ucfirst($user->service_provider) .
                    '. To use password login, please forgot your password.'
            ], 400);
        }

        // Step 3: Now validate password (for normal accounts only)
        $request->validate([
            'password' => 'required|string',
        ]);

        // Step 4: Check password
        if (!Hash::check($request->password, $user->password)) {
            return response()->json([
                'status'  => false,
                'message' => 'Invalid credentials'
            ], 400);
        }

        if (!$user->hasVerifiedEmail()) {
            return response()->json([
                'status'  => false,
                'message' => 'Please verify your email first'
            ], 400);
        }

        if ($user->status === 'inactive') {
            return response()->json([
                'status'  => false,
                'message' => 'Your account is inactive. Please contact support.'
            ], 400);
        }

        // update user status and login activity
        $user->update([
            'status'           => 'active',
            'last_login_at'    => now(),
            'last_activity_at' => now(),
            'fcm_token'        => $request->fcm_token ?? null,
            'device_type'      => $request->device_type ?? null,
            'timezone'         => $request->timezone,
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'status'  => true,
            'message' => 'Login successful',
            'token'   => $token,
            'user'    => $user->only([
                'id',
                'name',
                'email',
                'phone_number',
                'status',
                'last_login_at',
                'last_activity_at',
                'last_logout_time',
                'fcm_token'
            ]),
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'status'  => false,
            'message' => 'Login failed',
            'error'   => $e->getMessage()
        ], 500);
    }
}


    public function forgotPassword(AuthRequest $request)
    {


        // dd('fdfd');
        $user = User::where('email', $request->email)->first();

        if (!$user) {
            // dd('fdfd');
            return response()->json([
                'status'  => false,
                'message' => 'No account found with this email address'
            ], 404);
        }

        $otp = strtolower(Str::random(5)) . rand(100, 999);
        $expiry = now()->addMinutes(10);

        $user->update([
            'password_reset_token'            => $otp,
            'password_reset_token_expires_at' => $expiry,
        ]);

        Mail::to($user->email)->send(new forgotPasswordMail($otp));

        return response()->json([
            'status' => true,
            'message' => 'OTP sent to your email address',
            'expires_at' => $expiry->toDateTimeString()
        ]);
    }

 
    public function resetPassword(AuthRequest $request)
    {
        try {
            $user = User::where('email', $request->email)
                ->where('password_reset_token', $request->token)
                ->first();

            if (!$user) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Invalid OTP or email address.'
                ], 400);
            }

            if (!$user->password_reset_token_expires_at || now()->gt($user->password_reset_token_expires_at)) {
                return response()->json([
                    'status'  => false,
                    'message' => 'OTP has expired. Please request a new one.'
                ], 400);
            }

            $user->update([
                'password'                        => Hash::make($request->new_password),
                'password_reset_token'            => null,
                'password_reset_token_expires_at' => null,
                'last_password_reset_at'          => now(),
            ]);

            return response()->json([
                'status'  => true,
                'message' => 'Password reset successfully. You can now login with your new password.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status'  => false,
                'message' => 'Password reset failed.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }


    //Profile Update
    public function updateProfile(Request $request)
    {
        try {
            $user = auth()->user();
            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'User not authenticated.'
                ], 400);
            }


            $validated = $request->validate([
                'name' => 'nullable|string|max:255',
                'email' => 'nullable|email|unique:users,email,' . $user->id,
                'phone_number' => 'nullable|string|max:20',
                'city' => 'nullable|string|max:100',
                'state' => 'nullable|string|max:100',
                'country' => 'nullable|string|max:100',
                'address_one' => 'nullable|string|max:255',
                'address_two' => 'nullable|string|max:255',
                'skills' => 'nullable|string|max:500',
                'profile_image' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
                'cv' => 'nullable|mimes:pdf,doc,docx|max:5120',
            ]);


            $user->fill([
                'name' => $validated['name'] ?? $user->name,
                'email' => $validated['email'] ?? $user->email,
                'phone_number' => $validated['phone_number'] ?? $user->phone_number,
                'city' => $validated['city'] ?? $user->city,
                'state' => $validated['state'] ?? $user->state,
                'country' => $validated['country'] ?? $user->country,
                'address_one' => $validated['address_one'] ?? $user->address_one,
                'address_two' => $validated['address_two'] ?? $user->address_two,

                'skills' => isset($validated['skills']) ? explode(',', $validated['skills']) : $user->skills,
            ]);



            if ($request->hasFile('profile_image')) {
                if ($user->profile_image && $user->profile_image !== 'default.jpg') {
                    Storage::disk('public')->delete('profile_images/' . $user->profile_image);
                }

                $image = $request->file('profile_image');
                $imageName = uniqid('profile_') . '.' . $image->getClientOriginalExtension();
                $path = $image->storeAs('profile_images', $imageName, 'public');
                $user->profile_image = $imageName;
            }

            if ($request->hasFile('cv')) {
                if ($user->cv) {
                    Storage::disk('public')->delete('cv/' . $user->cv);
                }

                $cv = $request->file('cv');
                $cvName = uniqid('cv_') . '.' . $cv->getClientOriginalExtension();
                $path = $cv->storeAs('cv', $cvName, 'public');
                $user->cv = $cvName;
            }

            $user->save();

            return response()->json([
                'status' => true,
                'message' => 'Profile updated successfully.',
                'data' => $user
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Something went wrong.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function logout(Request $request)
    {

        $user = Auth::user();
        if ($user) {
            Cache::forget('user-is-online-' . $user->id);
            $user->tokens()->delete();
            $user->update([
                'last_logout_time' => now(),
                'last_activity_at' => now(),
                'online_status' => 'offline',
            ]);
        }
        // Token revoke


        // Update logout time & status


        return response()->json([
            'status'  => true,
            'message' => 'Logout successful',
        ]);
    }

 public function deleteAccount(Request $request)
    {
        // dd('fdfd');
        try {
            $user = auth()->user();

            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            // Optional: Verify password before deletion
            if ($request->has('password') && !empty($user->password)) {
                if (!Hash::check($request->password, $user->password)) {
                    return response()->json([
                        'status' => false,
                        'message' => 'Invalid password. Account deletion failed.'
                    ], 403);
                }
            }

            $userId = $user->id;
            $userName = $user->name;
            $userEmail = $user->email;

            // 1. Delete all user's lists and their related data
            $lists = $user->lists()->get();
            foreach ($lists as $list) {
                // Delete list images from storage
                foreach ($list->images as $image) {
                    if ($image->path) {
                        Storage::disk('public')->delete($image->path);
                    }
                    $image->delete();
                }

                // Delete list commits
                $list->comments()->delete();

                // Delete list reviews
                \App\Models\ListReview::where('list_id', $list->id)->delete();

                // Delete the list
                $list->delete();
            }

            // 2. Delete all user's tasks and related data
            $tasks = $user->tasks()->get();
            foreach ($tasks as $task) {
                // Delete task reminders
                \App\Models\Reminder::where('task_id', $task->id)->delete();
                

                // Delete task payments
                $task->taskPayments()->delete();

                // Delete the task
                $task->delete();
            }

            // 3. Delete user's employers
            \App\Models\Employer::where('user_id', $userId)->delete();

            // 4. Delete user's reminders
            \App\Models\Reminder::where('user_id', $userId)->delete();

            // 5. Delete user's support emails
            // \DB::table('support_emails')->where('user_id', $userId)->delete();

            // 6. Delete user's password reset tokens
            \DB::table('password_reset_tokens')->where('email', $userEmail)->delete();

            // 7. Delete user's sessions
            \DB::table('sessions')->where('user_id', $userId)->delete();

            // 8. Delete user profile image from storage
            if ($user->profile_image && $user->profile_image !== 'default.jpg') {
                Storage::disk('public')->delete('profile_images/' . $user->profile_image);
            }

            // 9. Delete user CV from storage
            if ($user->cv) {
                Storage::disk('public')->delete('cv/' . $user->cv);
            }

            // 10. Revoke all user's API tokens (this effectively logs out the user)
            $user->tokens()->delete();

            // 11. Force delete the user permanently (bypasses soft delete)
            $user->forceDelete();

            return response()->json([
                'status' => true,
                'message' => 'Your account has been permanently deleted. You cannot login again with the same credentials.'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to delete account',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    /**
     * Handle errors consistently
     */
    private function handleError(\Exception $e, string $message): JsonResponse
    {
        return response()->json([
            'status' => false,
            'message' => $message,
            'error' => config('app.debug') ? $e->getMessage() : null
        ], 500);
    }



    /**
     * Update user FCM token
     */
    public function updateFcmToken(Request $request)
    {
        $request->validate([
            'fcm_token' => 'required|string'
        ]);

        $user = auth()->user();
        $user->update(['fcm_token' => $request->fcm_token]);

        return response()->json([
            'success' => true,
            'message' => 'FCM token updated successfully'
        ]);
    }

    /**
     * Remove FCM token (logout)
     */
    public function removeFcmToken()
    {
        $user = auth()->user();
        $user->update(['fcm_token' => null]);

        return response()->json([
            'success' => true,
            'message' => 'FCM token removed successfully'
        ]);
    }

    public function updateUserStatus(Request $request)
    {
        $request->validate([
            'notifications_enabled' => 'required|in:1,0'
        ]);
        $user = auth()->user();
        $user->update(['notifications_enabled' => $request->notifications_enabled]);

        return response()->json([
            'status' => true,
            'message' => 'User status updated successfully',
            'data' => $user
        ], 200);
    }
}
