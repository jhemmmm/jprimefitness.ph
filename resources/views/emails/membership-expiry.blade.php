@extends('emails.layouts.branded')

@php($expired = $when === null)

@section('title', $expired ? 'Membership expired' : 'Membership expiring soon')
@section('preheader', $expired ? 'Your membership has ended - renew to get back to training.' : 'Your membership expires ' . $when . ' - renew to keep training.')
@section('eyebrow', $expired ? 'Membership Expired' : 'Expiring Soon')
@section('heading', $expired ? 'We miss you already, ' . $member->name : 'Your membership expires ' . $when . ', ' . $member->name)

@section('content')
    <p style="margin:0 0 16px 0;">
        @if ($expired)
            Your <strong>{{ $business->name }}</strong> membership <span style="color:#c8102e;font-weight:600;">expired</span> on {{ $subscription->end_date?->format('M j, Y') ?? '-' }}. Your QR code no longer works at the kiosk until you renew.
        @else
            Your <strong>{{ $business->name }}</strong> membership expires <strong>{{ $when }}</strong>. Renew before then so your QR keeps working at the kiosk.
        @endif
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f8fafc;border-radius:8px;border:1px solid #e2e8f0;margin:20px 0;">
        <tr>
            <td style="padding:20px;">
                <div style="font-size:11px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:#c8102e;margin-bottom:4px;">{{ $expired ? 'Expired Plan' : 'Your Plan' }}</div>
                <div style="font-size:18px;color:#0f172a;font-weight:700;margin-bottom:16px;">{{ $subscription->ratePlan?->name ?? 'Membership Plan' }}</div>

                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                    <tr>
                        <td width="50%" style="padding-right:8px;">
                            <div style="font-size:11px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:#64748b;">Valid From</div>
                            <div style="font-size:14px;color:#0f172a;font-weight:600;margin-top:2px;">{{ $subscription->start_date?->format('M j, Y') ?? '-' }}</div>
                        </td>
                        <td width="50%" style="padding-left:8px;">
                            <div style="font-size:11px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:#64748b;">{{ $expired ? 'Expired On' : 'Valid Until' }}</div>
                            <div style="font-size:14px;color:#c8102e;font-weight:600;margin-top:2px;">{{ $subscription->end_date?->format('M j, Y') ?? '-' }}</div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    @include('emails.partials.renew-cta', [
        'button' => $expired ? 'Renew my membership' : 'Renew now',
        'lead' => $expired
            ? 'Pay online in a minute and your new QR code arrives by email right away.'
            : 'Takes a minute online - your new plan starts right after this one ends and a fresh QR code lands in your inbox.',
    ])

    <p style="margin:20px 0 0 0;color:#475569;">{{ $expired ? 'Hope to see you soon,' : 'See you at the gym,' }}<br><strong>The {{ $business->name }} team</strong></p>
@endsection
