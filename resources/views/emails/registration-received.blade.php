@extends('emails.layouts.branded')

@section('title', 'Registration received')
@section('preheader', 'Your details are in and your plan is reserved.')
@section('eyebrow', 'Registration Received')
@section('heading', 'Thanks for registering, ' . $member->name . '!')

@section('content')
    <p style="margin:0 0 16px 0;">
        Thanks for registering with <strong>{{ $business->name }}</strong>! We received your details and your chosen plan is reserved.
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f8fafc;border-radius:8px;border:1px solid #e2e8f0;margin:20px 0;">
        <tr>
            <td style="padding:20px;">
                <div style="font-size:11px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:#c8102e;margin-bottom:4px;">Selected Plan</div>
                <div style="font-size:18px;color:#0f172a;font-weight:700;margin-bottom:16px;">{{ $subscription->ratePlan?->name ?? 'Membership Plan' }}</div>

                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                    <tr>
                        <td width="33%" style="padding-right:8px;vertical-align:top;">
                            <div style="font-size:11px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:#64748b;">Price</div>
                            <div style="font-size:16px;color:#0f172a;font-weight:700;margin-top:2px;">&#8369;{{ number_format((float) $subscription->sold_price, 2) }}</div>
                        </td>
                        <td width="33%" style="padding:0 8px;vertical-align:top;">
                            <div style="font-size:11px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:#64748b;">Duration</div>
                            <div style="font-size:14px;color:#0f172a;font-weight:600;margin-top:2px;">{{ $subscription->ratePlan?->duration_days ?? '-' }} days</div>
                        </td>
                        <td width="34%" style="padding-left:8px;vertical-align:top;">
                            <div style="font-size:11px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:#64748b;">Preferred Start</div>
                            <div style="font-size:14px;color:#0f172a;font-weight:600;margin-top:2px;">{{ $subscription->start_date?->format('M j, Y') ?? '-' }}</div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    @if ($paymentMethod === 'online')
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#eff6ff;border-radius:8px;border-left:4px solid #2563eb;margin:16px 0;">
            <tr>
                <td style="padding:16px 20px;font-size:14px;color:#1e3a8a;">
                    <div style="font-weight:700;margin-bottom:4px;">Next: Complete payment online</div>
                    You'll be redirected to PayMongo to finish your payment. Once we receive confirmation, your membership will activate automatically and your QR code will arrive in a separate email.
                </td>
            </tr>
        </table>
    @else
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#fef2f2;border-radius:8px;border-left:4px solid #c8102e;margin:16px 0;">
            <tr>
                <td style="padding:16px 20px;font-size:14px;color:#7f1d1d;">
                    <div style="font-weight:700;margin-bottom:6px;">Next: Pay at the gym</div>
                    Drop by to complete payment and activate your membership:
                    <div style="margin-top:8px;color:#0f172a;">
                        <strong>{{ $business->name }}</strong><br>
                        {{ collect([$business->address, $business->city, $business->province])->filter()->join(', ') ?: 'Address coming soon' }}
                    </div>
                </td>
            </tr>
        </table>
    @endif

    <p style="margin:20px 0 0 0;">If you have any questions, reply to this email and we'll get back to you.</p>

    <p style="margin:16px 0 0 0;color:#475569;">See you at the gym,<br><strong>The {{ $business->name }} team</strong></p>
@endsection
