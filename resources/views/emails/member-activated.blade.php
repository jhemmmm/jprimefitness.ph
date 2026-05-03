<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Membership activated</title>
</head>
<body style="font-family: Arial, sans-serif; color: #111827; line-height: 1.5;">
    <p>Hi {{ $member->name }},</p>

    <p>Your <strong>{{ $business->name }}</strong> membership is now active. Welcome aboard!</p>

    <p>
        <strong>Plan:</strong> {{ $subscription->ratePlan?->name ?? 'Membership Plan' }}<br>
        <strong>Valid from:</strong> {{ $subscription->start_date?->format('M j, Y') ?? '-' }}<br>
        <strong>Valid until:</strong> {{ $subscription->end_date?->format('M j, Y') ?? 'Open-ended' }}
    </p>

    <p>Your QR membership card is being sent in a separate email — use it to tap-in at the kiosk on every visit.</p>

    <p>See you at the gym,<br>The {{ $business->name }} team</p>
</body>
</html>
