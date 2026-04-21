<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Support Request Confirmation</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; background-color: #f4f4f4; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 20px auto; background: #fff; border-radius: 6px; overflow: hidden; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .header { background-color: #aa2b2b; color: white; padding: 15px; text-align: center; }
        .content { padding: 20px; }
        .details { margin: 15px 0; padding: 15px; background: #f8f9fa; border-radius: 4px; }
        .details p { margin: 5px 0; }
        .footer { background: #f1f1f1; padding: 10px; text-align: center; font-size: 12px; color: #555; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>Support Request Submitted</h2>
        </div>
        <div class="content">
            <p>Hello {{ $data['name'] ?? 'User' }},</p>
            <p>Thank you for contacting our support team. We have received your request and our team will get back to you shortly.</p>

            <div class="details">
                <p><strong>📝 Subject:</strong> {{ $data['subject'] ?? 'No subject' }}</p>
                <p><strong>📧 Your Email:</strong> {{ $data['email'] ?? 'N/A' }}</p>
            </div>

            <p><strong>Your Message:</strong></p>
            <p>{{ $data['message'] ?? 'No message provided.' }}</p>

            <p style="margin-top:20px;">We will contact you at your email as soon as possible.</p>
        </div>
        <div class="footer">
            &copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.
        </div>
    </div>
</body>
</html>
