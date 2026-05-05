<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>New contact message</title>
</head>
<body style="font-family: Arial, sans-serif; color: #111827; line-height: 1.5;">
    <p>A new message was submitted through the <strong>{{ $business->name }}</strong> contact form.</p>

    <p>
        <strong>Name:</strong> {{ $payload['name'] }}<br>
        <strong>Email:</strong> <a href="mailto:{{ $payload['email'] }}">{{ $payload['email'] }}</a><br>
        @if (!empty($payload['contact']))
            <strong>Phone / Messenger:</strong> {{ $payload['contact'] }}<br>
        @endif
        <strong>Inquiry:</strong> {{ $payload['topic'] }}
    </p>

    <p><strong>Message:</strong></p>
    <pre style="white-space: pre-wrap; font-family: inherit; font-size: 14px; background: #f9fafb; padding: 12px; border-left: 3px solid #dc2626; margin: 0;">{{ $payload['message'] }}</pre>

    <p style="margin-top: 24px;">Reply directly to this email to respond to {{ $payload['name'] }}.</p>
</body>
</html>
