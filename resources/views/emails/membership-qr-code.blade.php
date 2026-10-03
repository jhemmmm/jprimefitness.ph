@extends('emails.layouts.branded')

@section('title', 'Your Membership QR Code')
@section('preheader', 'Scan this at the kiosk to check in and out.')
@section('eyebrow', 'Membership QR Code')
@section('heading', 'Hello ' . ($subscription->member?->name ?? 'Member') . ', here\'s your QR.')

@section('content')
    <p style="margin:0 0 20px 0;">
        Your membership QR code is ready. Scan this at the kiosk when checking in or checking out.
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:20px 0;">
        <tr>
            <td align="center">
                <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="background:#ffffff;border:2px solid #0f172a;border-radius:12px;">
                    <tr>
                        <td style="padding:20px;" align="center">
                            <img src="{{ $message->embedData($qrPng, 'membership-qr.png', 'image/png') }}" alt="Membership QR Code" width="240" height="240" style="display:block;width:240px;height:240px;border:0;outline:none;">
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:0 20px 16px 20px;" align="center">
                            <div style="font-size:11px;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:#c8102e;">Tap-in Code</div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f8fafc;border-radius:8px;border:1px solid #e2e8f0;margin:20px 0;">
        <tr>
            <td style="padding:18px 20px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                    <tr>
                        <td style="padding-bottom:8px;">
                            <span style="font-size:12px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:#64748b;">Plan:</span>
                            <span style="font-size:14px;color:#0f172a;font-weight:600;margin-left:6px;">{{ $subscription->ratePlan?->name ?? 'Membership Plan' }}</span>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding-bottom:8px;">
                            <span style="font-size:12px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:#64748b;">Valid From:</span>
                            <span style="font-size:14px;color:#0f172a;font-weight:600;margin-left:6px;">{{ $subscription->start_date?->format('M j, Y') ?? '-' }}</span>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <span style="font-size:12px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:#64748b;">Valid Until:</span>
                            <span style="font-size:14px;color:#0f172a;font-weight:600;margin-left:6px;">{{ $subscription->end_date?->format('M j, Y') ?? 'Open-ended' }}</span>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#fef2f2;border-radius:8px;border-left:4px solid #c8102e;margin:16px 0;">
        <tr>
            <td style="padding:14px 18px;font-size:13px;color:#7f1d1d;">
                <strong>Keep this private.</strong> Do not share this QR code - it's tied to your membership record.
            </td>
        </tr>
    </table>
@endsection
