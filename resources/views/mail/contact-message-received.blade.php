<x-mail::message>
# New contact enquiry

A message was submitted through the Herocom marketing site.

**Name:** {{ $senderName }}
@if ($company)
**Company:** {{ $company }}
@endif
**Email:** {{ $email }}
@if ($phone)
**Phone:** {{ $phone }}
@endif

**Message:**

{{ $messageBody }}

<x-mail::button :url="'mailto:'.$email">
Reply to {{ $senderName }}
</x-mail::button>

Reply directly to this email to reach the sender (Reply-To is set to their address).

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
