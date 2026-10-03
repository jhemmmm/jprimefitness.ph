@extends('emails.layouts.branded')

@section('title', 'Membership activated')
@section('preheader', 'Your membership is now active. Welcome aboard!')
@section('eyebrow', 'Membership Active')
@section('heading', 'Welcome aboard, ' . $member->name . '!')

@section('content')
    <p style="margin:0 0 16px 0;">
        Your <strong>{{ $business->name }}</strong> membership is now <span style="color:#16a34a;font-weight:600;">active</span>. Time to get to work.
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f8fafc;border-radius:8px;border:1px solid #e2e8f0;margin:20px 0;">
        <tr>
            <td style="padding:20px;">
                <div style="font-size:11px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:#c8102e;margin-bottom:4px;">Your Plan</div>
                <div style="font-size:18px;color:#0f172a;font-weight:700;margin-bottom:16px;">{{ $subscription->ratePlan?->name ?? 'Membership Plan' }}</div>

                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                    <tr>
                        <td width="50%" style="padding-right:8px;">
                            <div style="font-size:11px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:#64748b;">Valid From</div>
                            <div style="font-size:14px;color:#0f172a;font-weight:600;margin-top:2px;">{{ $subscription->start_date?->format('M j, Y') ?? '-' }}</div>
                        </td>
                        <td width="50%" style="padding-left:8px;">
                            <div style="font-size:11px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:#64748b;">Valid Until</div>
                            <div style="font-size:14px;color:#0f172a;font-weight:600;margin-top:2px;">{{ $subscription->end_date?->format('M j, Y') ?? 'Open-ended' }}</div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#fef2f2;border-radius:8px;border-left:4px solid #c8102e;margin:16px 0;">
        <tr>
            <td style="padding:14px 18px;font-size:14px;color:#7f1d1d;">
                <strong>QR card incoming:</strong> Your membership QR is being sent in a separate email - use it to tap-in at the kiosk on every visit.
            </td>
        </tr>
    </table>

    <p style="margin:20px 0 0 0;color:#475569;">See you at the gym,<br><strong>The {{ $business->name }} team</strong></p>
@endsection
