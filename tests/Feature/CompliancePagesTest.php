<?php

namespace Tests\Feature;

use Tests\TestCase;

class CompliancePagesTest extends TestCase
{
    /**
     * Test that the Privacy Policy page is publicly accessible and contains required compliance disclosures.
     */
    public function test_privacy_policy_page_is_publicly_accessible(): void
    {
        $response = $this->get('/privacy');

        $response->assertStatus(200)
            ->assertSee('Privacy Policy')
            ->assertSee('SocialPilot AI')
            ->assertSee('Information We Collect')
            ->assertSee('Information Collected via Connected Social Media Platforms')
            ->assertSee('Cloud Infrastructure &amp; Third-Party Service Providers', false)
            ->assertSee('Data Retention &amp; Account Deletion', false)
            ->assertSee('Token Security Standards')
            ->assertSee('Your Rights')
            ->assertSee('mailto:socialpilotai.help@gmail.com', false)
            ->assertSee('socialpilotai.help@gmail.com')
            ->assertDontSee('support@example.com');
    }

    /**
     * Test that the Terms of Service page is publicly accessible and contains required legal clauses.
     */
    public function test_terms_of_service_page_is_publicly_accessible(): void
    {
        $response = $this->get('/terms');

        $response->assertStatus(200)
            ->assertSee('Terms of Service')
            ->assertSee('SocialPilot AI')
            ->assertSee('Acceptance of Terms')
            ->assertSee('Eligibility &amp; Account Security', false)
            ->assertSee('Prohibited Activities')
            ->assertSee('AI-Generated Content &amp; Mandatory Human Review', false)
            ->assertSee('Social Platform Integrations &amp; Third-Party Rules', false)
            ->assertSee('Limitation of Liability')
            ->assertSee('mailto:socialpilotai.help@gmail.com', false)
            ->assertSee('socialpilotai.help@gmail.com')
            ->assertDontSee('support@example.com');
    }

    /**
     * Test that the Data Deletion Instructions page is publicly accessible and contains actionable instructions.
     */
    public function test_data_deletion_page_is_publicly_accessible(): void
    {
        $response = $this->get('/data-deletion');

        $response->assertStatus(200)
            ->assertSee('User Data Deletion Instructions')
            ->assertSee('SocialPilot AI')
            ->assertSee('1. Overview')
            ->assertSee('2. Disconnecting a Social Media Account')
            ->assertSee('3. Revoking Access Directly via Facebook / Meta Settings')
            ->assertSee('4. Permanent Account &amp; Complete Data Deletion', false)
            ->assertSee('Data Deletion Guarantees')
            ->assertSee('5. Backup Retention &amp; External Network Notice', false)
            ->assertSee('6. Manual Assistance &amp; Inquiries', false)
            ->assertSee('socialpilotai.help@gmail.com')
            ->assertDontSee('support@example.com');
    }

    /**
     * Test that all three compliance pages are fully viewable without requiring authentication or redirection.
     */
    public function test_compliance_pages_do_not_require_authentication(): void
    {
        $endpoints = ['/privacy', '/terms', '/data-deletion'];

        foreach ($endpoints as $endpoint) {
            $response = $this->get($endpoint);
            $response->assertStatus(200);
            $this->assertGuest();
        }
    }

    /**
     * Test that the marketing landing page footer links to all three compliance pages.
     */
    public function test_marketing_footer_contains_compliance_links(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200)
            ->assertSee(route('terms'), false)
            ->assertSee(route('privacy'), false)
            ->assertSee(route('data-deletion'), false);
    }

    /**
     * Test that the registration page links to the Terms of Service and Privacy Policy.
     */
    public function test_registration_page_contains_compliance_links(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200)
            ->assertSee(route('terms'), false)
            ->assertSee(route('privacy'), false);
    }

    /**
     * Test that the confirmed official support email is consistently present and clickable on all compliance pages.
     */
    public function test_official_support_email_is_clickable_on_all_compliance_pages(): void
    {
        $endpoints = ['/privacy', '/terms', '/data-deletion'];

        foreach ($endpoints as $endpoint) {
            $response = $this->get($endpoint);
            $response->assertStatus(200)
                ->assertSee('socialpilotai.help@gmail.com')
                ->assertSee('mailto:socialpilotai.help@gmail.com', false)
                ->assertDontSee('support@example.com');
        }
    }
}
