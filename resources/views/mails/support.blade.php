<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>New Support Request</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; background-color: #f4f4f4; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 20px auto; background: #fff; border-radius: 6px; overflow: hidden; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .header { background-color: #aa2b2b; color: white; padding: 15px; text-align: center; }
        .content { padding: 20px; }
        .details { margin: 15px 0; padding: 15px; background: #f8f9fa; border-radius: 4px; }
        .details p { margin: 5px 0; }
        .message-box { background: #fff3cd; border-left: 5px solid #ffc107; padding: 15px; margin-top: 15px; border-radius: 4px; }
        .footer { background: #f1f1f1; padding: 10px; text-align: center; font-size: 12px; color: #555; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>New Support Request</h2>
        </div>
        <div class="content">
            <p>Hello Admin,</p>
            <p>You have received a new support query. Here are the details:</p>

            <div class="details">
                <p><strong>👤 Name:</strong> {{ $data['name'] ?? 'N/A' }}</p>
                <p><strong>📧 Email:</strong> {{ $data['email'] ?? 'N/A' }}</p>
                <p><strong>📝 Subject:</strong> {{ $data['subject'] ?? 'No subject' }}</p>
            </div>

            <div class="message-box">
                <p><strong>Message:</strong></p>
                <p>{{ $data['message'] ?? 'No message provided.' }}</p>
            </div>

            <p style="margin-top:20px;">Please respond to the user as soon as possible.</p>
        </div>
        <div class="footer">
            &copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.
        </div>
    </div>
</body>
</html>
