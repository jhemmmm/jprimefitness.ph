@extends('emails.layouts.branded')

@section('title', 'We received your message')
@section('preheader', 'Thanks for reaching out — we got your message.')
@section('eyebrow', 'Message Received')
@section('heading', 'Thanks for reaching out, ' . $payload['name'] . '!')

@section('content')
    <p style="margin:0 0 16px 0;">
        We've received your message at <strong>{{ $business->name }}</strong> and our team will reply as soon as we can.
    </p>

    <p style="margin:0 0 8px 0;">For your records, here's a copy of what you sent:</p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f8fafc;border-radius:8px;border:1px solid #e2e8f0;margin:16px 0;">
        <tr>
            <td style="padding:16px 20px;">
                <div style="font-size:11px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:#64748b;margin-bottom:4px;">Inquiry</div>
                <div style="font-size:14px;color:#0f172a;font-weight:600;">{{ $payload['topic'] }}</div>
            </td>
        </tr>
        <tr>
            <td style="padding:0 20px 16px 20px;">
                <div style="font-size:11px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:#64748b;margin-bottom:6px;">Your message</div>
                <div style="font-size:14px;color:#0f172a;white-space:pre-wrap;border-left:3px solid #c8102e;padding:8px 12px;background:#ffffff;border-radius:4px;">{{ $payload['message'] }}</div>
            </td>
        </tr>
    </table>

    <p style="margin:24px 0 0 0;">If you have anything to add, just reply to this email.</p>

    <p style="margin:16px 0 0 0;color:#475569;">Talk soon,<br><strong>The {{ $business->name }} team</strong></p>
@endsection
