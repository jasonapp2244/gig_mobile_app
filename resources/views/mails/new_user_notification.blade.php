<!DOCTYPE html>
<html>
<head>
    <title>New User Registration</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background-color: #aa2b2b; color: white; padding: 10px; text-align: center; }
        .content { padding: 20px; }
        .user-details { margin: 15px 0; padding: 10px; background: #f8f9fa; }
        .footer { margin-top: 20px; font-size: 12px; text-align: center; color: #777; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>New User Registration</h1>
        </div>

        <div class="content">
            <p>Hello Admin,</p>
            <p>A new user has registered on your platform:</p>

            <div class="user-details">
                <p><strong>Name:</strong> {{ $userName }}</p>
                <p><strong>Email:</strong> {{ $userEmail }}</p>
                <p><strong>Phone:</strong> {{ $userPhone ?? 'Not provided' }}</p>
                <p><strong>Registered At:</strong> {{ $registrationDate }}</p>
            </div>

            <p>You can view the user in the admin panel.</p>
        </div>

        <div class="footer">
            &copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.
        </div>
    </div>
</body>
</html>
