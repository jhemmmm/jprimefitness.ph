@extends('emails.layouts.branded')

@section('title', 'New contact message')
@section('preheader', 'A new message was submitted through the contact form.')
@section('eyebrow', 'New Inquiry')
@section('heading', 'New contact form message')

@section('content')
    <p style="margin:0 0 16px 0;">
        A new message was submitted through the <strong>{{ $business->name }}</strong> contact form.
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f8fafc;border-radius:8px;border:1px solid #e2e8f0;margin:16px 0;">
        <tr>
            <td style="padding:16px 20px;border-bottom:1px solid #e2e8f0;">
                <div style="font-size:11px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:#64748b;margin-bottom:2px;">From</div>
                <div style="font-size:14px;color:#0f172a;font-weight:600;">{{ $payload['name'] }}</div>
                <div style="font-size:13px;margin-top:2px;">
                    <a href="mailto:{{ $payload['email'] }}" style="color:#c8102e;text-decoration:none;">{{ $payload['email'] }}</a>
                </div>
                @if (!empty($payload['contact']))
                    <div style="font-size:13px;color:#475569;margin-top:2px;">{{ $payload['contact'] }}</div>
                @endif
            </td>
        </tr>
        <tr>
            <td style="padding:16px 20px;border-bottom:1px solid #e2e8f0;">
                <div style="font-size:11px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:#64748b;margin-bottom:2px;">Inquiry</div>
                <div style="font-size:14px;color:#0f172a;font-weight:600;">{{ $payload['topic'] }}</div>
            </td>
        </tr>
        <tr>
            <td style="padding:16px 20px;">
                <div style="font-size:11px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:#64748b;margin-bottom:6px;">Message</div>
                <div style="font-size:14px;color:#0f172a;white-space:pre-wrap;border-left:3px solid #c8102e;padding:8px 12px;background:#ffffff;border-radius:4px;">{{ $payload['message'] }}</div>
            </td>
        </tr>
    </table>

    <p style="margin:24px 0 0 0;color:#475569;font-size:14px;">
        Reply directly to this email to respond to {{ $payload['name'] }}.
    </p>
@endsection
