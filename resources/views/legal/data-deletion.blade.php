@extends('legal.layout')

@section('content')
<article>
    <header>
        <h1>User Data Deletion Instructions</h1>
        <p class="muted">
            <strong>Compliance Reference:</strong> Meta Developer Policy &middot; TikTok Developer Policy &middot; GDPR Article 17
        </p>
    </header>

    <h2>1. Overview</h2>
    <p>SocialPilot AI provides full self-service control over your personal data, connected social accounts, and uploaded content. This page provides step-by-step instructions for disconnecting social accounts, removing the application from your platform settings, or permanently deleting your user account and all stored data.</p>

    <h2>2. Disconnecting a Social Media Account</h2>
    <p>If you wish to remove SocialPilot AI's access to an individual social media account (Facebook Page, Instagram Professional account, TikTok account, YouTube channel, or LinkedIn profile) without deleting your SocialPilot AI account:</p>
    <ol>
        <li>Log in to SocialPilot AI at <code>https://socialpilot-ai-y4lm.onrender.com/login</code>.</li>
        <li>Select your brand workspace.</li>
        <li>Navigate to <strong>Social Accounts</strong> (<code>/brands/{brand_id}/social-accounts</code>).</li>
        <li>Locate the account and click the <strong>Disconnect</strong> button.</li>
        <li>Confirm disconnection when prompted.</li>
    </ol>
    <h3>What Happens Upon Disconnection:</h3>
    <ul>
        <li><strong>Remote Token Revocation:</strong> SocialPilot AI immediately dispatches an automated revocation request to the provider's OAuth server (Meta <code>/me/permissions</code>, TikTok <code>/oauth/revoke/</code>, Google <code>/revoke</code>, LinkedIn <code>/revoke</code>) to invalidate the access token.</li>
        <li><strong>Database Deletion:</strong> The encrypted OAuth token record (<code>social_account_tokens</code>) is immediately and permanently deleted from our database.</li>
        <li><strong>Operations Stopped:</strong> All scheduling, publishing, and metrics synchronization for that account immediately cease.</li>
    </ul>

    <h2>3. Revoking Access Directly via Facebook / Meta Settings</h2>
    <p>You can also revoke SocialPilot AI's access directly within Facebook or Instagram:</p>
    <ol>
        <li>Log in to Facebook and navigate to <strong>Settings &amp; Privacy &gt; Settings</strong>.</li>
        <li>Select <strong>Business Integrations</strong> (or visit <a href="https://www.facebook.com/settings?tab=business_tools" target="_blank" rel="noopener">Facebook Business Integrations</a>).</li>
        <li>Find <strong>SocialPilot AI</strong> and click <strong>Remove</strong>.</li>
    </ol>
    <p>Once removed, Meta immediately invalidates the authorization token. Any subsequent requests by SocialPilot AI will be rejected by Meta's API.</p>

    <h2>4. Permanent Account &amp; Complete Data Deletion</h2>
    <p>To permanently delete your SocialPilot AI account and all associated data across all brand workspaces:</p>
    <ol>
        <li>Log in to your account at <code>https://socialpilot-ai-y4lm.onrender.com/login</code>.</li>
        <li>Navigate to your <strong>Profile Settings</strong> (<code>/profile</code>).</li>
        <li>Scroll down to the <strong>Delete Account</strong> section.</li>
        <li>Enter your account password to confirm your identity.</li>
        <li>Click <strong>Delete Account</strong> to permanently purge your data.</li>
    </ol>

    <h3>Data Deletion Guarantees:</h3>
    <p>Our database enforces cascading deletion (<code>ON DELETE CASCADE</code>). Confirming deletion permanently and irreversibly purges:</p>
    <ul>
        <li>Your user profile, email address, password hash, and session cookies.</li>
        <li>All brands, brand voice settings, keywords, and guidelines.</li>
        <li>All content drafts, revisions, variations, and generation jobs.</li>
        <li>All uploaded media records and binary files in Supabase Cloud Storage S3.</li>
        <li>All scheduled posts and publishing queue entries.</li>
        <li>All connected social accounts and encrypted OAuth tokens.</li>
        <li>All notifications and analytics snapshots.</li>
    </ul>

    <h2>5. Backup Retention &amp; External Network Notice</h2>
    <ul>
        <li><strong>Database Disaster Recovery Snapshots:</strong> Automated database snapshots maintained by our cloud database infrastructure (TiDB Cloud / AWS) expire and cycle out automatically over a rolling retention window of 7 to 30 days.</li>
        <li><strong>Previously Published Posts:</strong> Posts previously published to third-party social media feeds (Facebook, Instagram, TikTok, YouTube, LinkedIn) remain on those platforms under your direct account control. You may delete published posts directly using each platform's native interface.</li>
    </ul>

    <h2>6. Manual Assistance &amp; Inquiries</h2>
    <p>If you cannot access your account or require manual confirmation of data deletion, submit a request to:</p>
    <ul>
        <li><strong>Email:</strong> <a href="mailto:socialpilotai.help@gmail.com?subject=Data%20Deletion%20Request">socialpilotai.help@gmail.com</a></li>
        <li><strong>Subject:</strong> <code>Data Deletion Request — [Your Registered Email]</code></li>
    </ul>
    <p>Manual requests are verified and processed within 30 business days with written confirmation.</p>
</article>
@endsection
