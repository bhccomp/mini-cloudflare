<?php

namespace App\Http\Controllers;

use App\Http\Requests\ContactSubmissionRequest;
use App\Models\ContactSubmission;
use App\Models\User;
use App\Notifications\ContactSubmissionAdminNotification;
use App\Notifications\ContactSubmissionCustomerNotification;
use App\Services\Support\ContactCaptchaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\View\View;

class ContactController extends Controller
{
    public function create(): View
    {
        return view('marketing.contact', [
            'turnstileSiteKey' => config('services.turnstile.site_key'),
            'usesTurnstile' => app(ContactCaptchaService::class)->shouldUseTurnstile(),
        ]);
    }

    public function store(ContactSubmissionRequest $request, ContactCaptchaService $captcha): RedirectResponse
    {
        if ((int) $request->integer('submitted_from') > now()->subSeconds(3)->getTimestampMs()) {
            return back()
                ->withErrors(['message' => 'Please take a moment to complete the form before sending it.'])
                ->withInput();
        }

        if (! $captcha->verify($request->string('cf-turnstile-response')->toString(), $request->ip())) {
            return back()
                ->withErrors(['message' => 'Please complete the security check and try again.'])
                ->withInput();
        }

        $submission = ContactSubmission::create([
            'name' => $request->string('name')->toString(),
            'email' => $request->string('email')->toString(),
            'company_name' => $request->string('company_name')->toString() ?: null,
            'website_url' => $request->string('website_url')->toString() ?: null,
            'topic' => $request->string('topic')->toString(),
            'message' => $request->string('message')->toString(),
            'status' => 'new',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'submitted_at' => Carbon::now(),
        ]);

        $adminRecipients = User::query()
            ->where('is_super_admin', true)
            ->whereNotNull('email')
            ->get();

        if ($adminRecipients->isNotEmpty()) {
            Notification::send($adminRecipients, new ContactSubmissionAdminNotification($submission));
        }

        Notification::route('mail', $submission->email)
            ->notify(new ContactSubmissionCustomerNotification($submission));

        return redirect()
            ->route('contact')
            ->with('contact_success', 'Message received. We will get back to you shortly.');
    }
}
