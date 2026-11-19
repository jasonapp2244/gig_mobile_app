<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Support Reply</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; background-color: #f4f4f4; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 20px auto; background: #fff; border-radius: 6px; overflow: hidden; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .header { background-color: #aa2b2b; color: white; padding: 15px; text-align: center; }
        .content { padding: 20px; }
        .reply { white-space: pre-line; }
        .original { margin-top: 20px; padding: 15px; background: #f8f9fa; border-left: 3px solid #ccc; border-radius: 4px; color: #555; }
        .original p { margin: 5px 0; }
        .footer { background: #f1f1f1; padding: 10px; text-align: center; font-size: 12px; color: #555; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>Reply from Gig Support</h2>
        </div>
        <div class="content">
            <p>Hello {{ $support->name ?: 'User' }},</p>

            <div class="reply">{{ $reply }}</div>

            <div class="original">
                <p><strong>Your request:</strong> {{ $support->subject ?: 'No subject' }}</p>
                <p style="white-space: pre-line;">{{ $support->message }}</p>
            </div>

            <p style="margin-top:20px;">
                Need more help? Just reply to this email and it will reach
                <a href="mailto:{{ config('mail.support.address') }}">{{ config('mail.support.address') }}</a>.
            </p>
        </div>
        <div class="footer">
            &copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.
        </div>
    </div>
</body>
</html>
