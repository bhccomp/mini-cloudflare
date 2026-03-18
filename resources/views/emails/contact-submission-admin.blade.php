<x-mail::message>
# New contact request

**Topic:** {{ $submission->topic }}

**From:** {{ $submission->name }} ({{ $submission->email }})

@if ($submission->company_name)
**Company:** {{ $submission->company_name }}
@endif

@if ($submission->website_url)
**Website:** {{ $submission->website_url }}
@endif

**Message**

{{ $submission->message }}

@if ($submission->submitted_at)
Submitted {{ $submission->submitted_at->toDayDateTimeString() }}
@endif
</x-mail::message>
