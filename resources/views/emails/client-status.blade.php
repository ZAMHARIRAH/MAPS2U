<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $subjectLine }}</title>
</head>
<body style="margin:0;padding:24px;background:#f4f7fb;font-family:Arial,Helvetica,sans-serif;color:#17324d;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:680px;margin:0 auto;background:#ffffff;border-radius:16px;overflow:hidden;border:1px solid #d9e4f2;">
    <tr>
        <td style="background:#0f2b55;padding:20px 24px;color:#ffffff;">
            <h1 style="margin:0;font-size:22px;">{{ config('maps2u_notifications.app_name') }}</h1>
            <p style="margin:6px 0 0;font-size:13px;opacity:0.9;">Job status notification</p>
        </td>
    </tr>
    <tr>
        <td style="padding:24px;">
            <p style="margin:0 0 12px;font-size:14px;">Assalamualaikum {{ $clientRequest->full_name ?: $clientRequest->user?->name ?: 'Client' }},</p>
            <h2 style="margin:0 0 12px;font-size:24px;color:#0f2b55;">{{ $headline }}</h2>
            <p style="margin:0 0 18px;font-size:15px;line-height:1.7;">{{ $messageBody }}</p>

            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;margin:0 0 18px;">
                <tr>
                    <td style="padding:12px;border:1px solid #d9e4f2;background:#f8fbff;width:180px;"><strong>Job ID</strong></td>
                    <td style="padding:12px;border:1px solid #d9e4f2;">{{ $clientRequest->request_code }}</td>
                </tr>
                <tr>
                    <td style="padding:12px;border:1px solid #d9e4f2;background:#f8fbff;"><strong>Request Type</strong></td>
                    <td style="padding:12px;border:1px solid #d9e4f2;">{{ $clientRequest->requestType?->name ?? '-' }}</td>
                </tr>
                <tr>
                    <td style="padding:12px;border:1px solid #d9e4f2;background:#f8fbff;"><strong>Status</strong></td>
                    <td style="padding:12px;border:1px solid #d9e4f2;">{{ $clientRequest->status }}</td>
                </tr>
            </table>

            @if($ctaUrl && $ctaLabel)
                <p style="margin:0 0 24px;">
                    <a href="{{ $ctaUrl }}" style="display:inline-block;padding:12px 18px;background:#6d39f6;color:#ffffff;text-decoration:none;border-radius:10px;font-weight:600;">{{ $ctaLabel }}</a>
                </p>
            @endif

            <p style="margin:0;font-size:13px;color:#58708d;line-height:1.6;">This email was sent automatically by {{ config('maps2u_notifications.app_name') }}.</p>
        </td>
    </tr>
</table>
</body>
</html>
