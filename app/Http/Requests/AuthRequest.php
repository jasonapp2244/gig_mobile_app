<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class AuthRequest extends FormRequest
{
    /**
     * Determine which action to validate based on route name
     */
    public function rules(): array
    {
        return match ($this->route()->getName()) {
            'auth.register' => $this->registerRules(),
            'auth.login' => $this->loginRules(),
            'auth.verify-otp' => $this->verifyOtpRules(),
            'auth.forgot-password' => $this->forgotPasswordRules(),
            'auth.reset-password'  => $this->resetPasswordRules(),
            'auth.logout' => $this->logoutRules(),
            'auth.resend-otp' => $this->resendOtpRules(),
            default => $this->defaultRules(),
        };
    }

    protected function signup(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required',
            //  [
            //     'required',
            //     'confirmed',
            //     Password::min(8)
            //         ->mixedCase()
            //         ->numbers()
            //         ->symbols()
            //         ->uncompromised()
            // ],
            'phone_number' => 'required|string|max:20|unique:users,phone_number',
            'password_confirmation' => 'required|same:password'
        ];
    }



    protected function verifyOtpRules(): array
    {
        return [
            'email'    => 'required|email|exists:users,email',
            'otp'      => 'required|digits:6',
            'timezone' => 'required|string|timezone',
        ];
    }

    protected function forgotPasswordRules(): array
    {
        return [
            'email' => 'required|email',
        ];
    }

    protected function resetPasswordRules(): array
    {
        return [
            'email'                    => 'required|email|exists:users,email',
            'token'                    => 'required|string',
            'new_password'             => 'required|string|min:8|confirmed',
            'new_password_confirmation'=> 'required|string',
        ];
    }

    protected function logoutRules(): array
    {
        return [
            'device_id' => 'nullable|string'
        ];
    }



    protected function resendOtpRules(): array
    {
        return [
            'email' => 'required|email|exists:users,email'
        ];
    }


    protected function defaultRules(): array
    {
        return [];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'The email field is required.',
            'email.email' => 'Please enter a valid email address.',
            'email.exists' => 'This email is not registered with us.',
            'email.unique' => 'This email is already registered.',
            'password.required' => 'The password field is required.',
            'password.confirmed' => 'Password confirmation does not match.',
            'password.min' => 'Password must be at least 8 characters.',
            'phone_number.required' => 'The phone number field is required.',
            'phone_number.unique' => 'This phone number is already registered.',
            'otp.required' => 'The OTP field is required.',
            'otp.digits' => 'OTP must be exactly 6 digits.',
            'timezone.required' => 'The timezone field is required.',
            'timezone.timezone' => 'The timezone must be a valid timezone (e.g. America/New_York).',
            'token.exists' => 'Invalid or expired password reset token.',
            'password_confirmation.required' => 'Please confirm your password.',
            'password_confirmation.same' => 'Passwords do not match.',
            'otp_email.exists' => 'No account found with this email address'
        ];
    }

    public function attributes(): array
    {
        return [
            'email' => 'email address',
            'phone_number' => 'phone number',
        ];
    }
}
