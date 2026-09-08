<x-mail::message>
# {{ $heading }}

Hello {{ $recipientName }},

{{ $intro }}

<div class="jod-code-card">
    <div class="jod-code-label">Your verification code</div>
    <div class="jod-code">{{ $code }}</div>
</div>

This code expires in **{{ $expiresInMinutes }} minutes**.

<x-mail::panel>
{{ $securityMessage }}
</x-mail::panel>

Thanks for being part of JOD - a platform built to connect people with help, opportunity, and impact.

Regards,  
**JOD Team**
</x-mail::message>
