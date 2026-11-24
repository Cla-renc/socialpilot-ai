@extends('legal.layout')

@section('content')
<article>
    <header>
        <h1>Privacy Policy</h1>
        <p class="muted">
            <strong>Effective Date:</strong> <span class="placeholder-tag">[Pending Owner Confirmation: e.g. November 24, 2026]</span> &middot;
            <strong>Last Updated:</strong> <span class="placeholder-tag">[Pending Owner Confirmation: e.g. November 24, 2026]</span>
        </p>
        <p class="muted">
            <strong>Operating Entity:</strong> <span class="placeholder-tag">[Pending Owner Confirmation: Business / Operator Name]</span> &middot;
            <strong>Contact:</strong> <a href="mailto:socialpilotai.help@gmail.com">socialpilotai.help@gmail.com</a>
        </p>
    </header>

    <h2>1. Introduction</h2>
    <p>SocialPilot AI ("we", "us", or "our") provides a social media content planning, scheduling, and workspace SaaS platform accessible at <code>https://socialpilot-ai-y4lm.onrender.com</code> (the "Service").</p>
    <p>We respect your privacy and are committed to protecting your personal data. This Privacy Policy explains what information we collect, how we use and store it, how we protect social media authorization tokens, and the choices and rights you have over your data.</p>

    <h2>2. Information We Collect</h2>
    <p>We collect information directly from you, automatically through your use of the Service, and via third-party social media platforms that you choose to connect.</p>

    <h3>2.1. Information You Provide Directly</h3>
    <ul>
        <li><strong>Account Registration:</strong> When registering, we collect your name, email address, password (stored strictly as a cryptographic bcrypt hash), and a record of your acceptance of our terms.</li>
        <li><strong>Brand Information:</strong> When configuring a brand workspace, you may provide brand names, descriptions, website URLs, industry categories, audience demographics, brand voice guidelines, keywords, and color preferences.</li>
        <li><strong>User Content:</strong> Content drafts, text captions, hashtags, and media files (images and videos) uploaded or generated within the platform.</li>
    </ul>

    <h3>2.2. Information Collected via Connected Social Media Platforms (OAuth)</h3>
    <p>When you connect an account from Meta (Facebook or Instagram), TikTok, Google (YouTube), or LinkedIn, we receive access through official OAuth authorization:</p>
    <ul>
        <li><strong>Account Identifiers:</strong> Platform-assigned account IDs (e.g. Facebook Page ID, Instagram Professional ID, TikTok OpenID, YouTube Channel ID, LinkedIn Member/Organization URN).</li>
        <li><strong>Profile Data:</strong> Display names, usernames, and profile picture/avatar URLs.</li>
        <li><strong>Authorization Tokens:</strong> OAuth access tokens and refresh tokens necessary to publish content on your behalf.</li>
        <li><strong>Performance Metrics:</strong> Aggregated post engagement metrics (impressions, reach, likes, comments, shares, views) for posts published via the Service.</li>
    </ul>

    <h3>2.3. Information Collected Automatically</h3>
    <ul>
        <li><strong>Session and Device Data:</strong> We store session identifiers, client IP addresses (in standard 45-character IPv4/IPv6 fields), browser user-agent strings, and activity timestamps to secure your session.</li>
    </ul>

    <h2>3. How We Use Your Information</h2>
    <p>We use collected data solely to deliver, secure, and operate the Service:</p>
    <ul>
        <li>Authenticating your access and enforcing tenant isolation between brand workspaces.</li>
        <li>Generating content drafts, variations, and captions based on your brand context and prompts.</li>
        <li>Executing post scheduling and publishing approved content to your designated social accounts.</li>
        <li>Aggregating post engagement metrics to display performance insights.</li>
        <li>Providing in-app notifications regarding publishing and token status.</li>
    </ul>
    <p><strong>We do not sell, rent, or monetize your personal information or submitted content to third-party data brokers or advertisers.</strong></p>

    <h2>4. Social Platform Integrations &amp; Access Token Protection</h2>
    <p>SocialPilot AI connects to external platforms through official developer APIs:</p>
    <ul>
        <li><strong>Meta (Facebook Pages &amp; Instagram Professional):</strong> Graph API v22.0.</li>
        <li><strong>TikTok:</strong> Content Posting API v2.</li>
        <li><strong>Google / YouTube:</strong> YouTube Data API v3.</li>
        <li><strong>LinkedIn:</strong> LinkedIn Share API &amp; OpenID Connect v2.</li>
    </ul>

    <h3>Token Security Standards</h3>
    <ul>
        <li><strong>At-Rest Encryption:</strong> All third-party OAuth access tokens and refresh tokens are encrypted at rest using AES-256-CBC cryptography before database insertion.</li>
        <li><strong>Serialization Masking:</strong> Token attributes are masked and excluded from API responses, JSON resources, and application logs.</li>
        <li><strong>CSRF State Verification:</strong> Authorization flows use 64-character cryptographically secure state tokens with a strict 10-minute expiry and single-use atomic consumption.</li>
        <li><strong>Revocation upon Disconnection:</strong> Disconnecting an account triggers a remote revocation request to the social platform and permanently purges stored tokens.</li>
    </ul>

    <h2>5. Cloud Infrastructure &amp; Third-Party Service Providers</h2>
    <p>We rely on trusted cloud infrastructure providers to host and secure the platform:</p>
    <table>
        <thead>
            <tr>
                <th>Service Provider</th>
                <th>Role / Infrastructure</th>
                <th>Region</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><strong>Render Services, Inc.</strong></td>
                <td>Cloud application hosting &amp; runtime compute</td>
                <td>Frankfurt, Germany (EU)</td>
            </tr>
            <tr>
                <td><strong>PingCAP (TiDB Cloud)</strong></td>
                <td>Serverless relational database infrastructure</td>
                <td>Frankfurt, Germany (EU)</td>
            </tr>
            <tr>
                <td><strong>Supabase, Inc.</strong></td>
                <td>Cloud object storage (S3-compatible media bucket)</td>
                <td>Ireland (EU)</td>
            </tr>
            <tr>
                <td><strong>Cloudflare, Inc.</strong></td>
                <td>Reverse proxy, TLS 1.3 encryption, DDoS protection</td>
                <td>Global Edge Network</td>
            </tr>
        </tbody>
    </table>

    <h2>6. Data Retention &amp; Account Deletion</h2>
    <ul>
        <li><strong>Active Accounts:</strong> User data and brand assets are retained as long as your account remains open.</li>
        <li><strong>Automated Self-Service Deletion:</strong> You may delete your account at any time via <code>/profile</code>. Our database enforces cascading deletion (<code>ON DELETE CASCADE</code>), immediately and permanently deleting your profile, brands, settings, content drafts, uploaded media, and OAuth tokens.</li>
        <li><strong>Backup Retention:</strong> Automated transactional database snapshots age out and expire automatically over a rolling window of 7 to 30 days.</li>
    </ul>

    <h2>7. Your Rights</h2>
    <p>Depending on your jurisdiction, you have the right to access your data, request corrections, export your content, revoke social media access, or request complete account erasure. You can exercise self-service erasure directly through your profile settings.</p>

    <h2>8. Contact Information</h2>
    <p>For questions or requests concerning this Privacy Policy, please contact:</p>
    <ul>
        <li><strong>Email:</strong> <a href="mailto:socialpilotai.help@gmail.com">socialpilotai.help@gmail.com</a></li>
        <li><strong>Operating Address:</strong> <span class="placeholder-tag">[Pending Owner Confirmation: Business Location / Jurisdiction]</span></li>
    </ul>
</article>
@endsection
