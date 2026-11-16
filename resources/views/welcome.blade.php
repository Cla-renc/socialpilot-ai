<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="Plan, create, and manage social content for every brand in one workspace.">
    <title>{{ config('app.name', 'SocialPilot AI') }} â€” Social media, thoughtfully managed</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('assets/logo-32.png') }}">
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <link rel="apple-touch-icon" sizes="192x192" href="{{ asset('assets/logo-192.png') }}">
    <meta name="application-name" content="SocialPilot AI">
    <meta name="theme-color" content="#6366f1">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/socialpilot.css') }}">
</head>
<body>
    <div class="marketing-shell">
        <nav class="topbar marketing-topbar" aria-label="Site navigation">
            <a class="brand-mark" href="{{ route('welcome') }}" aria-label="SocialPilot AI home">
                <span class="brand-icon"><img src="{{ asset('assets/logo.png') }}" alt="SocialPilot AI logo" width="36" height="36"></span>
                <span>SocialPilot AI</span>
            </a>
            <button class="menu-toggle" type="button" aria-expanded="false" aria-controls="marketing-navigation">
                <span class="sr-only">Toggle navigation</span>
                <span aria-hidden="true">â˜°</span>
            </button>
            <div class="marketing-navigation" id="marketing-navigation">
                <div class="nav-links">
                    <a href="#platform">Platform</a>
                    <a href="#agents">AI agents</a>
                    <a href="#pricing">Pricing</a>
                    <a href="#resources">Resources</a>
                </div>
                <div class="nav-actions">
                    @auth
                        <a href="{{ route('dashboard') }}" class="button-secondary">Dashboard</a>
                    @else
                        <a href="{{ route('login') }}" class="button-secondary">Log in</a>
                        <a href="{{ route('register') }}" class="button">Create account</a>
                    @endauth
                </div>
            </div>
        </nav>

        <main>
            <section class="hero" aria-labelledby="hero-title">
                <div class="hero-copy">
                    <p class="eyebrow">One workspace for your social presence</p>
                    <h1 class="landing-title" id="hero-title">A clearer way to grow every brand.</h1>
                    <p>Plan campaigns, create platform-ready content, coordinate reviews, and understand performance from one calm, organized workspace.</p>
                    <div class="hero-actions">
                        @auth
                            <a href="{{ route('dashboard') }}" class="button">Go to dashboard</a>
                        @else
                            <a href="{{ route('register') }}" class="button">Start free</a>
                            <a href="#demo" class="button-secondary">Book a demo</a>
                        @endauth
                    </div>
                    <p class="hero-caption">Start with the free local workspace. Connect integrations when youâ€™re ready.</p>
                </div>

                <div class="hero-panel product-preview" aria-label="Illustrative SocialPilot AI workspace preview">
                    <div class="preview-header">
                        <div>
                            <span class="preview-kicker">WORKSPACE PREVIEW</span>
                            <strong>Weekly overview</strong>
                        </div>
                        <span class="preview-status"><span aria-hidden="true"></span> Your workspace</span>
                    </div>
                    <div class="preview-summary">
                        <span>Content calendar</span>
                        <strong>Plan, review, publish</strong>
                        <div class="preview-progress" aria-hidden="true"><span></span></div>
                        <small>Bring your channels together in one place.</small>
                    </div>
                    <div class="preview-list">
                        <div><span class="preview-dot preview-dot--purple">01</span><span>Draft content in your brand voice</span><span class="preview-label">Create</span></div>
                        <div><span class="preview-dot preview-dot--blue">02</span><span>Review with your collaborators</span><span class="preview-label">Review</span></div>
                        <div><span class="preview-dot preview-dot--green">03</span><span>Track results over time</span><span class="preview-label">Learn</span></div>
                    </div>
                </div>
            </section>

            <section class="landing-section" id="platform" aria-labelledby="platform-title">
                <div class="section-heading">
                    <p class="eyebrow">The platform</p>
                    <h2 class="section-title" id="platform-title">From first idea to informed next step.</h2>
                    <p>Keep brand information, content, campaigns, and performance connected instead of scattered across tools.</p>
                </div>
                <div class="feature-grid">
                    <article class="feature-box">
                        <span class="feature-icon" aria-hidden="true">01</span>
                        <h3>Brand workspaces</h3>
                        <p>Keep audiences, voice, settings, and social accounts organized by brand.</p>
                    </article>
                    <article class="feature-box">
                        <span class="feature-icon" aria-hidden="true">02</span>
                        <h3>Campaign planning</h3>
                        <p>Organize content around campaigns and coordinate drafts and scheduled posts.</p>
                    </article>
                    <article class="feature-box">
                        <span class="feature-icon" aria-hidden="true">03</span>
                        <h3>Performance summaries</h3>
                        <p>Review the analytics data available to your workspace and see what needs attention.</p>
                    </article>
                </div>
            </section>

            <section class="landing-section landing-section--soft" id="agents" aria-labelledby="agents-title">
                <div class="section-heading">
                    <p class="eyebrow">AI-assisted, human-led</p>
                    <h2 class="section-title" id="agents-title">Useful assistance, with you in control.</h2>
                    <p>Use content generation to get started faster, then edit and approve every draft before it goes anywhere.</p>
                </div>
                <div class="feature-grid">
                    <article class="feature-box">
                        <span class="feature-icon" aria-hidden="true">A</span>
                        <h3>Draft assistant</h3>
                        <p>Generate a starting point using your brand context and chosen platform.</p>
                    </article>
                    <article class="feature-box">
                        <span class="feature-icon" aria-hidden="true">R</span>
                        <h3>Review workflow</h3>
                        <p>Request review and keep approval decisions attached to brand work.</p>
                    </article>
                    <article class="feature-box">
                        <span class="feature-icon" aria-hidden="true">I</span>
                        <h3>Local AI option</h3>
                        <p>Configure a local Ollama model for generation without a paid AI API subscription.</p>
                    </article>
                </div>
            </section>

            <section class="landing-section" id="pricing" aria-labelledby="pricing-title">
                <div class="section-heading">
                    <p class="eyebrow">Pricing</p>
                    <h2 class="section-title" id="pricing-title">Begin with the free workspace.</h2>
                    <p>No paid AI provider is required to try locally configured Ollama. External social platforms may require their own app credentials and access approvals.</p>
                </div>
                <article class="pricing-card">
                    <div>
                        <span class="preview-kicker">LOCAL DEVELOPMENT</span>
                        <h3>Free to get started</h3>
                        <p class="muted">Create an account and explore brand management. Local AI usage depends on your computerâ€™s available resources.</p>
                    </div>
                    <a href="{{ route('register') }}" class="button">Create your free account</a>
                </article>
            </section>

            <section class="landing-section landing-section--soft" id="resources" aria-labelledby="resources-title">
                <div class="section-heading">
                    <p class="eyebrow">Resources</p>
                    <h2 class="section-title" id="resources-title">A practical place to get started.</h2>
                </div>
                <div class="resource-links">
                    <a href="{{ route('register') }}"><strong>Set up your workspace</strong><span>Create your account and add your first brand.</span></a>
                    <a href="{{ route('login') }}"><strong>Already have an account?</strong><span>Log in to return to your dashboard.</span></a>
                    <a href="#demo"><strong>Talk through a demo</strong><span>See the demo contact options below.</span></a>
                </div>
            </section>

            <section class="demo-section" id="demo" aria-labelledby="demo-title">
                <div>
                    <p class="eyebrow">See the workflow</p>
                    <h2 id="demo-title">Want a walkthrough?</h2>
                    <p class="muted">
                        @if (config('services.socialpilot.contact_email'))
                            Send us a note and weâ€™ll follow up about a demo.
                        @else
                            Demo scheduling is not configured for this local project yet. You can create a free account and explore the workspace now.
                        @endif
                    </p>
                </div>
                @if (config('services.socialpilot.contact_email'))
                    <a class="button" href="mailto:{{ config('services.socialpilot.contact_email') }}?subject={{ rawurlencode('SocialPilot AI demo request') }}">Email to book a demo</a>
                @else
                    <a class="button" href="{{ route('register') }}">Explore for free</a>
                @endif
            </section>

            <section class="legal-note" id="terms">
                <p><strong>Terms and privacy:</strong> This development app is still evolving. Registration records when you accept the current terms version. Review and publish complete legal terms and privacy disclosures before opening the service to the public.</p>
                <span id="privacy"></span>
            </section>
        </main>

        <footer class="marketing-footer">
            <a class="brand-mark" href="{{ route('welcome') }}"><span class="brand-icon"><img src="{{ asset('assets/logo.png') }}" alt="SocialPilot AI logo" width="36" height="36"></span><span>SocialPilot AI</span></a>
            <span>Social planning, made more manageable.</span>
            <a href="#terms">Terms &amp; privacy notice</a>
        </footer>
    </div>
    <script src="{{ asset('assets/socialpilot.js') }}" defer></script>
</body>
</html>

