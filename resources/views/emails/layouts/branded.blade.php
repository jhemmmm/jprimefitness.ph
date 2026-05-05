@php
    $business = $business ?? \App\Models\BusinessProfile::current();
    $logoCid = $message->embed(public_path('logo.png'));
    $footerAddress = collect([$business->address ?? null, $business->city ?? null, $business->province ?? null])->filter()->join(', ');
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="x-apple-disable-message-reformatting">
    <title>@yield('title', $business->name)</title>
</head>
<body style="margin:0;padding:0;background:#f4f5f7;font-family:'Segoe UI',Roboto,Arial,sans-serif;color:#111827;">
    <div style="display:none;max-height:0;overflow:hidden;mso-hide:all;color:transparent;">
        @yield('preheader', $business->name)
    </div>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f4f5f7;padding:24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 1px 3px rgba(15,23,42,0.06);">
                    <tr>
                        <td style="background:#0f172a;padding:24px 28px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td style="vertical-align:middle;width:48px;">
                                        <img src="{{ $logoCid }}" alt="JPrime Fitness" width="40" height="40" style="display:block;width:40px;height:40px;border:0;outline:none;">
                                    </td>
                                    <td style="vertical-align:middle;padding-left:12px;">
                                        <div style="font-family:'Oswald','Segoe UI',Arial,sans-serif;font-size:20px;font-weight:700;letter-spacing:1px;color:#ffffff;line-height:1;">
                                            JPRIME <span style="color:#c8102e;">FITNESS</span>
                                        </div>
                                        <div style="font-size:11px;color:#94a3b8;margin-top:4px;letter-spacing:0.5px;text-transform:uppercase;">
                                            {{ $business->city ?? 'Philippines' }}
                                        </div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="height:4px;background:#c8102e;line-height:4px;font-size:0;">&nbsp;</td>
                    </tr>
                    <tr>
                        <td style="padding:32px 32px 8px 32px;">
                            @hasSection('eyebrow')
                                <div style="font-size:11px;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;color:#c8102e;margin-bottom:8px;">
                                    @yield('eyebrow')
                                </div>
                            @endif
                            @hasSection('heading')
                                <h1 style="margin:0 0 16px 0;font-size:24px;font-weight:700;color:#0f172a;line-height:1.3;">
                                    @yield('heading')
                                </h1>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:0 32px 32px 32px;font-size:15px;line-height:1.65;color:#334155;">
                            @yield('content')
                        </td>
                    </tr>
                    <tr>
                        <td style="background:#f8fafc;padding:24px 32px;border-top:1px solid #e2e8f0;">
                            <div style="font-size:13px;color:#0f172a;font-weight:600;margin-bottom:6px;">{{ $business->name }}</div>
                            @if ($footerAddress)
                                <div style="font-size:12px;color:#64748b;line-height:1.5;">{{ $footerAddress }}</div>
                            @endif
                            @if (!empty($business->opening_time) && !empty($business->closing_time))
                                <div style="font-size:12px;color:#64748b;line-height:1.5;margin-top:4px;">
                                    Open daily {{ \Illuminate\Support\Carbon::parse($business->opening_time)->format('g:i A') }} – {{ \Illuminate\Support\Carbon::parse($business->closing_time)->format('g:i A') }}
                                </div>
                            @endif
                        </td>
                    </tr>
                </table>
                <div style="font-size:11px;color:#94a3b8;margin-top:16px;max-width:600px;">
                    You're receiving this because you interacted with {{ $business->name }}. If this wasn't you, please ignore this email.
                </div>
            </td>
        </tr>
    </table>
</body>
</html>
