<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            // Primary Key
            $table->id();

            // Role Management
            $table->foreignId('role_id')->nullable()->constrained('roles')->onDelete('set null');
            $table->enum('role', ['user', 'admin', 'manager'])->default('user'); // Added more roles

            // Authentication
            $table->string('name')->nullable();
            $table->string('email')->unique()->nullable();
            $table->string('password')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->rememberToken();

            // Social Authentication
            $table->string('service_provider')->nullable()->comment('google,facebook,apple');
            $table->string('service_provider_id')->nullable();
            $table->string('auth_token')->nullable()->comment('For API authentication');

            // OTP Verification
            $table->string('otp', 6)->nullable();
            $table->enum('otp_status', ['unverified', 'verified', 'expired'])->default('unverified');
            $table->timestamp('otp_expires_at')->nullable();

            // Profile Information
            $table->string('phone_number', 20)->nullable();
            $table->string('profile_image')->default('default.jpg');
            $table->text('bio')->nullable();

            // Location Information
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('country', 2)->nullable()->comment('ISO country code');
            $table->string('address_one')->nullable();
            $table->string('address_two')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            //add this colume for future

            $table->boolean('hasActiveSubscription')->default(false);
            $table->boolean('payment_status')->default(false);

            // Professional Information
            $table->json('skills')->nullable();
            $table->string('cv')->nullable();

            // Security & Status
            $table->string('password_reset_token')->nullable();
            $table->timestamp('password_reset_token_expires_at')->nullable();
            $table->timestamp('last_password_reset_at')->nullable();
            $table->string('fcm_token')->nullable();
            $table->string('device_type')->default('null');
            $table->string('device_token')->nullable();
            $table->boolean('notifications_enabled')->default(true);
            $table->timestamp('last_login_at')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamp('last_logout_time')->nullable();
            $table->string('online_status', 20)->default('offline')->change();
            $table->enum('status', ['active', 'inactive', 'suspended', 'pending'])->default('pending');

            // Timestamps
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['email', 'phone_number']);
            $table->index('service_provider_id');
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('expires_at')->nullable(); // Added expiry
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
            $table->timestamp('expires_at')->nullable(); // Added expiry
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
