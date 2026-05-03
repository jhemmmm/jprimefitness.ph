<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Registration received</title>
</head>
<body style="font-family: Arial, sans-serif; color: #111827; line-height: 1.5;">
    <p>Hi {{ $member->name }},</p>

    <p>Thanks for registering with <strong>{{ $business->name }}</strong>! We received your details and your chosen plan is reserved.</p>

    <p>
        <strong>Plan:</strong> {{ $subscription->ratePlan?->name ?? 'Membership Plan' }}<br>
        <strong>Price:</strong> &#8369;{{ number_format((float) $subscription->sold_price, 2) }}<br>
        <strong>Duration:</strong> {{ $subscription->ratePlan?->duration_days ?? '-' }} days<br>
        <strong>Preferred start:</strong> {{ $subscription->start_date?->format('M j, Y') ?? '-' }}
    </p>

    @if ($paymentMethod === 'online')
        <p>You'll be redirected to PayMongo to complete payment. Once we receive confirmation, your membership will be activated automatically and your QR code will arrive in a separate email.</p>
    @else
        <p>To activate your membership, please drop by the gym to complete payment:</p>
        <p>
            <strong>{{ $business->name }}</strong><br>
            {{ collect([$business->address, $business->city, $business->province])->filter()->join(', ') ?: 'Address coming soon' }}
        </p>
    @endif

    <p>If you have any questions, reply to this email and we'll get back to you.</p>

    <p>See you at the gym,<br>The {{ $business->name }} team</p>
</body>
</html>
