<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Membership QR Code</title>
</head>
<body style="font-family: Arial, sans-serif; color: #111827; line-height: 1.5;">
    <p>Hello {{ $subscription->member?->name ?? 'Member' }},</p>

    <p>Your membership QR code is ready. Please scan this at the kiosk when checking in or checking out.</p>

    <p>
        <strong>Plan:</strong> {{ $subscription->ratePlan?->name ?? 'Membership Plan' }}<br>
        <strong>Valid from:</strong> {{ $subscription->start_date?->format('M j, Y') ?? '-' }}<br>
        <strong>Valid until:</strong> {{ $subscription->end_date?->format('M j, Y') ?? 'Open-ended' }}
    </p>

    <p>
        <img src="{{ $message->embedData($qrPng, 'membership-qr.png', 'image/png') }}" alt="Membership QR Code" style="width: 240px; height: 240px;">
    </p>

    <p>Do not share this QR code. It is tied to your membership record.</p>
</body>
</html>
