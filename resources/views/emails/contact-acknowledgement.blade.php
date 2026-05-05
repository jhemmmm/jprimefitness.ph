<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>We received your message</title>
</head>
<body style="font-family: Arial, sans-serif; color: #111827; line-height: 1.5;">
    <p>Hi {{ $payload['name'] }},</p>

    <p>Thanks for reaching out to <strong>{{ $business->name }}</strong>! We've received your message and our team will reply as soon as we can.</p>

    <p>For your records, here's a copy of what you sent:</p>

    <p>
        <strong>Inquiry:</strong> {{ $payload['topic'] }}
    </p>

    <p><strong>Your message:</strong></p>
    <pre style="white-space: pre-wrap; font-family: inherit; font-size: 14px; background: #f9fafb; padding: 12px; border-left: 3px solid #dc2626; margin: 0;">{{ $payload['message'] }}</pre>

    <p style="margin-top: 24px;">If you have anything to add, just reply to this email.</p>

    <p>Talk soon,<br>The {{ $business->name }} team</p>
</body>
</html>
