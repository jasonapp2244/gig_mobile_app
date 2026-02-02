<!DOCTYPE html>
<html>
<head>
    <title>New Password</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; background-color: #f4f4f4; }
        .container { max-width: 600px; margin: 30px auto; padding: 20px; background-color: #ffffff; box-shadow: 0 0 10px rgba(0,0,0,0.05); }
        .header { background-color: #aa2b2b; color: white; padding: 15px; text-align: center; }
        .content { padding: 20px; }
        .otp-box { font-size: 24px; font-weight: bold; background: #f8f9fa; padding: 10px 20px; display: inline-block; margin: 15px 0; border-radius: 5px; }
        .footer { margin-top: 30px; font-size: 12px; text-align: center; color: #777; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>New Password</h1>
        </div>

        <div class="content">
            <p>Hello,</p>
            <p>You recently requested to reset your password. Use the OTP below to proceed:</p>

            <div class="otp-box">
                {{ $otp }}
            </div>

            <p>This OTP will expire in <strong>10 minutes</strong>.</p>
            <p>If you didn’t request this, you can safely ignore this email.</p>
        </div>

        
        <div class="footer">
            &copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.
        </div>
    </div>
</body>
</html>
