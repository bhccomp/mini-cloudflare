<x-marketing.legal-page-layout
    title="Cookie Policy"
    description="This page explains how FirePhage uses necessary cookies, how consent preferences are stored, and how optional cookie categories are handled on the website."
>
    <p><strong>Last updated:</strong> April 1, 2026</p>

    <h2>1. Overview</h2>
    <p>FirePhage uses cookies and similar browser-side storage to keep the website secure, maintain session state, protect forms, remember cookie preferences, and support optional website features where applicable.</p>

    <h2>2. Cookie categories</h2>
    <p>FirePhage currently uses the following categories:</p>
    <ul>
        <li><strong>Necessary cookies:</strong> required for security, login sessions, CSRF protection, and storing your cookie preferences.</li>
        <li><strong>Preference cookies:</strong> optional cookies for remembering non-essential website preferences if those features are enabled later.</li>
        <li><strong>Analytics cookies:</strong> optional cookies for traffic or product analytics if analytics tools are enabled later.</li>
        <li><strong>Marketing cookies:</strong> optional cookies for attribution or advertising tools if those services are enabled later.</li>
    </ul>

    <h2>3. Cookies and storage FirePhage may use</h2>
    <ul>
        <li><strong>Session and security cookies:</strong> used by Laravel and FirePhage for login state, CSRF protection, and authenticated sessions.</li>
        <li><strong>Cookie preference storage:</strong> FirePhage stores your cookie consent choice so the website can respect it on future visits.</li>
        <li><strong>Form-protection services:</strong> if Turnstile is enabled on forms, Cloudflare may set cookies or process browser signals for abuse prevention.</li>
        <li><strong>Billing flows:</strong> Stripe may use cookies or similar mechanisms on checkout or billing-related pages when those services are loaded.</li>
    </ul>

    <h2>4. Necessary cookies</h2>
    <p>Necessary cookies do not require the same prior opt-in handling as non-essential cookies because they are used to provide core website functions such as secure login, request integrity, and remembering your consent choice.</p>

    <h2>5. Optional cookies</h2>
    <p>Optional cookie categories are disabled unless you choose to allow them. If FirePhage adds analytics, marketing, or other optional website technologies later, the website should respect the cookie preferences you saved.</p>

    <h2>6. How to change your choice</h2>
    <p>You can change your cookie settings by using the cookie banner or by clearing FirePhage cookies from your browser and revisiting the site.</p>

    <h2>7. More information</h2>
    <p>For broader information about how FirePhage handles personal data, see the <a href="{{ route('privacy') }}">Privacy Policy</a>. For questions about cookies or privacy, contact <a href="mailto:privacy@firephage.com">privacy@firephage.com</a>.</p>
</x-marketing.legal-page-layout>
