<x-mail::message>
# We got your message

Hi {{ $submission->name }},

Thanks for reaching out to FirePhage. Your message is in front of us now, and we will reply as soon as possible.

**Topic:** {{ $submission->topic }}

**What you sent**

{{ $submission->message }}

If this is about an active customer account, you can also use the in-app support area once you sign in.

Thanks,<br>
FirePhage
</x-mail::message>
