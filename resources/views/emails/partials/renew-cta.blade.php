{{-- Signed renew button; expects $renewUrl, $button, $lead --}}
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:24px 0 8px 0;">
        <tr>
            <td align="center" style="padding:0;">
                <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                    <tr>
                        <td style="background:#c8102e;border-radius:8px;">
                            <a href="{{ $renewUrl }}" style="display:inline-block;padding:14px 32px;color:#ffffff;font-weight:700;text-decoration:none;font-size:15px;">{{ $button }} &rarr;</a>
                        </td>
                    </tr>
                </table>
                <div style="font-size:13px;color:#475569;margin-top:12px;">
                    {{ $lead }}
                </div>
                <div style="font-size:12px;color:#94a3b8;margin-top:6px;">Prefer to pay at the gym, or renewing at a student / senior citizen / PWD rate? Drop by the front desk and we'll sort it out.</div>
            </td>
        </tr>
    </table>
