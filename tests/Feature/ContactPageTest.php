<?php

namespace Tests\Feature;

use App\Models\ContactSubmission;
use App\Models\User;
use App\Notifications\ContactSubmissionAdminNotification;
use App\Notifications\ContactSubmissionCustomerNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ContactPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_contact_page_loads(): void
    {
        $response = $this->get('/contact');

        $response->assertOk();
        $response->assertSee('Contact FirePhage');
        $response->assertSee('Send a message');
    }

    public function test_contact_submission_is_stored_and_notifies_admin_and_customer_without_turnstile(): void
    {
        Notification::fake();

        $admin = User::factory()->create([
            'is_super_admin' => true,
            'email' => 'admin@example.com',
        ]);

        $response = $this->withSession(['_token' => 'contact-test-token'])->post('/contact', [
            '_token' => 'contact-test-token',
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'company_name' => 'Demo Co',
            'website_url' => 'https://demo.example.com',
            'topic' => 'Onboarding & Migration',
            'message' => 'We are moving a WooCommerce site and need help planning DNS cutover and onboarding.',
            'website' => '',
            'submitted_from' => now()->subSeconds(5)->getTimestampMs(),
        ]);

        $response->assertRedirect('/contact');
        $response->assertSessionHas('contact_success');

        $submission = ContactSubmission::query()->first();

        $this->assertNotNull($submission);
        $this->assertSame('Jane Doe', $submission->name);
        $this->assertSame('Onboarding & Migration', $submission->topic);

        Notification::assertSentTo($admin, ContactSubmissionAdminNotification::class);
        Notification::assertSentOnDemand(ContactSubmissionCustomerNotification::class);
    }

    public function test_contact_submission_requires_valid_turnstile_when_configured(): void
    {
        Notification::fake();

        Config::set('services.turnstile.site_key', 'site-key');
        Config::set('services.turnstile.secret_key', 'secret-key');

        Http::fake([
            'https://challenges.cloudflare.com/turnstile/v0/siteverify' => Http::response([
                'success' => false,
            ]),
        ]);

        $response = $this->withSession(['_token' => 'contact-test-token'])
            ->from('/contact')
            ->post('/contact', [
            '_token' => 'contact-test-token',
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'topic' => 'Sales & Plans',
            'message' => 'I need details about plan sizing for several customer sites.',
            'website' => '',
            'submitted_from' => now()->subSeconds(5)->getTimestampMs(),
            'cf-turnstile-response' => 'bad-token',
        ]);

        $response->assertRedirect('/contact');
        $response->assertSessionHasErrors('message');
        $this->assertDatabaseCount('contact_submissions', 0);
    }
}
