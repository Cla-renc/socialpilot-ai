@extends('legal.layout')

@section('content')
<article>
    <header>
        <h1>Terms of Service</h1>
        <p class="muted">
            <strong>Effective Date:</strong> <span class="placeholder-tag">[Pending Owner Confirmation: e.g. November 24, 2026]</span> &middot;
            <strong>Last Updated:</strong> <span class="placeholder-tag">[Pending Owner Confirmation: e.g. November 24, 2026]</span>
        </p>
        <p class="muted">
            <strong>Operating Entity:</strong> <span class="placeholder-tag">[Pending Owner Confirmation: Business / Operator Name]</span> &middot;
            <strong>Governing Jurisdiction:</strong> <span class="placeholder-tag">[Pending Owner Confirmation: Jurisdiction / Country]</span>
        </p>
    </header>

    <h2>1. Acceptance of Terms</h2>
    <p>These Terms of Service ("Terms") govern your access to and use of SocialPilot AI ("Service"), operated by <span class="placeholder-tag">[Pending Owner Confirmation: Business Name]</span>. By registering an account or accessing the Service, you agree to be bound by these Terms and our <a href="{{ route('privacy') }}">Privacy Policy</a>. If you do not agree, you must not use the Service.</p>

    <h2>2. Eligibility &amp; Account Security</h2>
    <ul>
        <li>You must be at least 18 years old (or the age of majority in your jurisdiction) to use the Service.</li>
        <li>You agree to provide accurate and complete registration information and maintain the security of your credentials.</li>
        <li>You are responsible for all activity that occurs under your account.</li>
    </ul>

    <h2>3. User Content &amp; Intellectual Property</h2>
    <ul>
        <li><strong>Your Ownership:</strong> You retain full ownership and intellectual property rights in all text, media, graphics, and brand assets ("User Content") that you submit to the Service.</li>
        <li><strong>Service License:</strong> You grant us a limited, worldwide, non-exclusive license to host, format, and transmit your User Content solely to the extent necessary to display it within your workspaces and publish it to your authorized social accounts.</li>
        <li><strong>Your Responsibility:</strong> You warrant that your User Content does not infringe upon any third-party copyright, trademark, or privacy rights, and complies with all applicable laws.</li>
    </ul>

    <h2>4. AI-Generated Content &amp; Mandatory Human Review</h2>
    <ul>
        <li>SocialPilot AI provides automated content drafting assistance. Output is generated algorithmically and may occasionally contain inaccuracies or errors.</li>
        <li><strong>Mandatory Human Review:</strong> You are solely responsible for reviewing, editing, and verifying all AI-generated content before scheduling or publishing it. SocialPilot AI does not publish any draft without your explicit authorization.</li>
        <li>We make no warranties regarding the uniqueness, legal suitability, or accuracy of AI-generated content.</li>
    </ul>

    <h2>5. Social Platform Integrations &amp; Third-Party Rules</h2>
    <ul>
        <li>By connecting an account via OAuth, you authorize SocialPilot AI to transmit and schedule posts on your behalf.</li>
        <li>Your use of connected accounts is subject to the terms and policies of each respective platform (Meta, TikTok, Google/YouTube, LinkedIn).</li>
        <li>Violating third-party platform rules may result in account restrictions by that platform. SocialPilot AI is not liable for third-party account suspensions or penalties.</li>
    </ul>

    <h2>6. Prohibited Activities</h2>
    <p>You agree not to use the Service to:</p>
    <ul>
        <li>Distribute unlawful, defamatory, abusive, fraudulent, or harassing material.</li>
        <li>Engage in automated spamming or artificial engagement farming.</li>
        <li>Violate any third party's intellectual property rights.</li>
        <li>Interfere with, reverse engineer, or compromise the integrity of the Service or its hosting infrastructure.</li>
    </ul>

    <h2>7. Service Availability &amp; Limitations</h2>
    <ul>
        <li>The Service is provided on cloud infrastructure (Render, TiDB Cloud, Supabase). You acknowledge that background publishing is handled during scheduled operational cycles.</li>
        <li>We reserve the right to perform maintenance, update features, or suspend access for security reasons.</li>
    </ul>

    <h2>8. Account Termination &amp; Deletion</h2>
    <ul>
        <li><strong>By You:</strong> You can delete your account at any time via <code>/profile</code>. Deletion immediately and permanently purges all stored brands, drafts, media, and encrypted tokens.</li>
        <li><strong>By Us:</strong> We may suspend or terminate your account if you materially breach these Terms or if required by law.</li>
    </ul>

    <h2>9. Disclaimers &amp; Limitation of Liability</h2>
    <p>THE SERVICE IS PROVIDED ON AN "AS IS" AND "AS AVAILABLE" BASIS WITHOUT WARRANTIES OF ANY KIND. TO THE MAXIMUM EXTENT PERMITTED BY LAW, SOCIALPILOT AI SHALL NOT BE LIABLE FOR ANY INDIRECT, INCIDENTAL, CONSEQUENTIAL, OR PUNITIVE DAMAGES, OR FOR LOSS OF PROFITS, DATA, OR REPUTATION ARISING OUT OF YOUR USE OF THE SERVICE OR THIRD-PARTY API DOWNTIME.</p>

    <h2>10. Governing Law</h2>
    <p>These Terms shall be governed by and construed in accordance with the laws of <span class="placeholder-tag">[Pending Owner Confirmation: Jurisdiction / Country]</span>, without regard to conflict of law principles.</p>

    <h2>11. Contact</h2>
    <p>For inquiries regarding these Terms, please contact: <a href="mailto:socialpilotai.help@gmail.com">socialpilotai.help@gmail.com</a></p>
</article>
@endsection
