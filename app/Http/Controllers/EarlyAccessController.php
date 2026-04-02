<?php

namespace App\Http\Controllers;

use App\Models\EarlyAccessLead;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Illuminate\View\View;

class EarlyAccessController extends Controller
{
    public function create(): View
    {
        if (! config('marketing.early_access_enabled', true)) {
            throw new NotFoundHttpException();
        }

        return view('marketing.early-access');
    }

    public function store(Request $request): RedirectResponse
    {
        if (! config('marketing.early_access_enabled', true)) {
            throw new NotFoundHttpException();
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'website_url' => ['nullable', 'url:http,https', 'max:255'],
            'monthly_requests_band' => ['nullable', 'string', 'max:255'],
            'websites_managed' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'wants_launch_discount' => ['nullable', 'boolean'],
        ]);

        EarlyAccessLead::query()->updateOrCreate(
            ['email' => strtolower(trim((string) $validated['email']))],
            [
                'name' => trim((string) $validated['name']),
                'company_name' => filled($validated['company_name'] ?? null) ? trim((string) $validated['company_name']) : null,
                'website_url' => filled($validated['website_url'] ?? null) ? trim((string) $validated['website_url']) : null,
                'monthly_requests_band' => filled($validated['monthly_requests_band'] ?? null) ? trim((string) $validated['monthly_requests_band']) : null,
                'websites_managed' => filled($validated['websites_managed'] ?? null) ? trim((string) $validated['websites_managed']) : null,
                'notes' => filled($validated['notes'] ?? null) ? trim((string) $validated['notes']) : null,
                'wants_launch_discount' => (bool) ($validated['wants_launch_discount'] ?? false),
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 65535),
                'signed_up_at' => now(),
            ],
        );

        return back()->with('status', 'You are on the list. We will reach out when early access opens.');
    }
}
