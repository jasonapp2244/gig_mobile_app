<!DOCTYPE html>
<html>
<head>
    <title>Welcome to Gig App</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background-color: #aa2b2b; color: white; padding: 10px; text-align: center; }
        .content { padding: 20px; }
        .otp { font-size: 24px; font-weight: bold; text-align: center; margin: 20px 0; }
        .footer { margin-top: 20px; font-size: 12px; text-align: center; color: #777; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Welcome to Gig App</h1>
        </div>

        <div class="content">
            <p>Hello {{ $user->name }},</p>
            <p>Thank you for registering with our service. Please use the following OTP to verify your email address:</p>

            <div class="otp">{{ $otp }}</div>

            <p>This OTP will expire in 10 minutes.</p>
            <p>If you did not request this registration, please ignore this email.</p>
        </div>

        <div class="footer">
            &copy; {{ date('Y') }} Gig App. All rights reserved.
        </div>
    </div>
</body>
</html>
