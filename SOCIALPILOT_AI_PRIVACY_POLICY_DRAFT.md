# SOCIALPILOT AI — PRIVACY POLICY (DRAFT)

**Effective Date:** `[OWNER TO CONFIRM: e.g. November 24, 2026]`  
**Last Updated:** `[OWNER TO CONFIRM: e.g. November 24, 2026]`  
**Service URL:** `https://socialpilot-ai-y4lm.onrender.com`  
**Provider / Operator:** `[OWNER TO CONFIRM: e.g. SocialPilot AI / Legal Entity Name]`  
**Contact Email:** `[OWNER TO CONFIRM: e.g. support@socialpilot-ai.com]`  

---

## 1. INTRODUCTION

SocialPilot AI ("we", "us", or "our") provides a social media management, content planning, and scheduling SaaS platform accessible at `https://socialpilot-ai-y4lm.onrender.com` (the "Service").

We respect your privacy and are committed to protecting your personal data. This Privacy Policy describes how we collect, use, store, process, and protect your information when you access our Service, connect social media accounts, and use our content generation and scheduling tools.

Please read this Privacy Policy carefully. By creating an account or using SocialPilot AI, you acknowledge that you have read and understood the practices described herein.

---

## 2. INFORMATION WE COLLECT

We collect information directly from you, automatically through your use of the Service, and via third-party social media platforms that you explicitly choose to connect.

### 2.1. Information You Provide Directly
- **Account Registration:** When you register, we collect your name, email address, and a hashed representation of your password. We also record the timestamp and version of your acceptance of our Terms of Service.
- **Brand Information:** When you configure a brand workspace, you may provide brand names, descriptions, website URLs, industry categories, target audience demographics, regional preferences, brand voice, values, keywords, topics to avoid, and color palettes.
- **Content & Media:** Content drafts, captions, headlines, hashtags, and media files (images and videos) that you upload or generate within the Service.

### 2.2. Information Collected via Social Media Integrations (OAuth)
When you choose to connect a third-party social media platform, we receive information through that platform's official OAuth authorization flow:
- **Account Identifiers:** Unique platform account IDs (e.g., Facebook Page ID, Instagram Business Account ID, TikTok OpenID, YouTube Channel ID, LinkedIn Member/Organization URN).
- **Public Profile Data:** Display names, usernames, and profile picture/avatar URLs.
- **Authentication Tokens:** OAuth access tokens and refresh tokens necessary to authenticate API requests on your behalf.
- **Performance Metrics:** When using our analytics studio, we retrieve aggregate engagement metrics (views, likes, comments, shares, impressions) for posts published through the Service.

> [!IMPORTANT]
> SocialPilot AI requests only the permissions necessary to manage and publish content to your chosen accounts. We **never** access your personal private messages, personal financial details, or unrelated browsing activity on external social platforms.

### 2.3. Information Collected Automatically
- **Log and Device Information:** When you access the Service, our servers and network infrastructure (via Cloudflare and Render) automatically record standard technical log data, including your Internet Protocol (IP) address, browser type and version, operating system, referring URLs, and timestamps.
- **Session Data:** We use session cookies to maintain your authenticated session while using the Service.

---

## 3. HOW WE USE YOUR INFORMATION

We use your information strictly to provide, maintain, and improve the Service, specifically:
1. **Account Administration:** Authenticating your identity, verifying access rights, and maintaining your account security.
2. **Multi-Tenant Brand Workspaces:** Organizing your content, assets, and settings within your private, tenant-isolated brand workspaces.
3. **Content Generation & Optimization:** Processing your brand context and prompts through our internal AI engine to produce drafts, variations, and captions.
4. **Post Scheduling & Publishing:** Executing scheduled publication of your approved text, images, and videos to your connected social accounts via official third-party APIs.
5. **Analytics & Performance Tracking:** Aggregating engagement statistics for your published posts to provide performance insights within your dashboard.
6. **Service Notifications:** Displaying in-app alerts regarding publishing outcomes, scheduling status, or token expiration.
7. **Compliance & Protection:** Enforcing our Terms of Service, preventing fraud, and complying with applicable legal obligations.

We **do not** sell, rent, or monetize your personal data or submitted content to third-party data brokers or advertisers.

---

## 4. SOCIAL PLATFORM INTEGRATIONS & ACCESS TOKEN PROTECTION

### 4.1. Supported Platforms
SocialPilot AI integrates with third-party social platforms via official developer APIs:
- **Meta (Facebook Pages & Instagram Professional):** Graph API (`v22.0`)
- **TikTok:** TikTok Content Posting API (`v2`)
- **Google / YouTube:** YouTube Data API (`v3`)
- **LinkedIn:** LinkedIn Share API & OpenID Connect (`v2`)

### 4.2. Access Token Encryption & Security
We recognize that OAuth credentials represent sensitive authorization tokens. SocialPilot AI implements rigorous security safeguards to protect stored tokens:
- **At-Rest Encryption:** All access tokens and refresh tokens are encrypted at rest using strong AES-256-CBC cryptography before being written to the database.
- **Serialization Protection:** OAuth token attributes are strictly masked (`$hidden`) within our application models to prevent them from appearing in API responses, logs, or debugging outputs.
- **Cryptographic State Protection:** Authorization requests use cryptographically random 64-character state tokens with a strict 10-minute expiry and single-use atomic consumption to prevent Cross-Site Request Forgery (CSRF).

### 4.3. Disconnection & Revocation
You can disconnect any linked social account at any time from your brand's Social Accounts settings. Upon disconnection:
- We invoke the platform's remote revocation endpoint to invalidate the access token with the provider.
- We permanently delete the stored encrypted token records from our database.

---

## 5. MEDIA & FILE STORAGE

Media assets (images, graphics, videos) uploaded to SocialPilot AI are stored in cloud object storage hosted by Supabase Storage (AWS S3-compatible infrastructure).
- **Directory Partitioning:** Files are organized into brand-isolated directories (`brands/{brand_id}/media/...`).
- **Access Control:** File access, modification, and deletion are governed by application-level ownership verification. Cross-tenant access is strictly denied.
- **Deletion:** When you delete a media asset from your Media Library, the database record and the corresponding cloud object are permanently deleted.

---

## 6. THIRD-PARTY DATA PROCESSORS

We share data with trusted third-party service providers solely to the extent necessary to operate the cloud infrastructure of the Service:

| Provider | Service Provided | Location / Region |
| :--- | :--- | :--- |
| **Render Services, Inc.** | Cloud application hosting and compute | AWS `eu-central-1` (Frankfurt, Germany) |
| **PingCAP (TiDB Cloud)** | Serverless database infrastructure | AWS `eu-central-1` (Frankfurt, Germany) |
| **Supabase, Inc.** | Cloud object storage (media storage) | AWS `eu-west-1` (Ireland) |
| **Cloudflare, Inc.** | Reverse proxy, SSL termination, DDoS protection | Global edge network |
| **Meta Platforms, Inc.** | Social publishing (Facebook & Instagram) | United States / Global |
| **TikTok Inc. / ByteDance** | Social publishing (TikTok) | United States / Global |
| **Google LLC** | Social publishing (YouTube) | United States / Global |
| **LinkedIn Corporation** | Social publishing (LinkedIn) | United States / Global |

Each cloud infrastructure provider operates under stringent data protection standards and maintains industry-standard security safeguards.

---

## 7. DATA RETENTION & ACCOUNT DELETION

### 7.1. Retention Periods
- **Account Data:** Maintained for as long as your account remains active.
- **Drafts and Scheduled Posts:** Maintained until published, deleted by you, or your account is closed.
- **Session Records:** Database session records expire automatically after inactivity (default 120 minutes).
- **OAuth States:** Temporary CSRF authorization states expire automatically after 10 minutes.

### 7.2. Account Deletion (Self-Service)
You have the right to delete your SocialPilot AI account at any time directly through the Service:
1. Log in to your SocialPilot AI dashboard.
2. Navigate to your **Profile** settings (`/profile`).
3. Under the **Delete Account** section, confirm your request by entering your current password.
4. Upon confirmation, your user record is permanently deleted.

**Effect of Account Deletion:**  
Our relational database enforces cascading deletion (`ON DELETE CASCADE`). Deleting your account permanently and irreversibly purges:
- Your user profile and authentication credentials.
- All associated brands and brand settings.
- All media assets, storyboard records, and content drafts.
- All scheduled posts and publishing queue entries.
- All connected social accounts and stored encrypted OAuth tokens.
- All notifications and analytics history.

*(Note: Data that has already been published to external social platforms remains on those platforms subject to their respective terms and retention policies.)*

---

## 8. YOUR PRIVACY RIGHTS

Depending on your country or region of residence (including the European Economic Area, United Kingdom, or California), you may have certain statutory rights regarding your personal data:
- **Right to Access:** The right to request confirmation of whether we process your data and access copies of your personal information.
- **Right to Rectification:** The right to correct inaccurate or incomplete profile or brand information.
- **Right to Erasure ("Right to be Forgotten"):** The right to request deletion of your account and all associated data, accessible directly via `/profile`.
- **Right to Data Portability:** The right to export your content drafts, scheduled posts, and analytics in structured digital formats.
- **Right to Withdraw Consent:** The right to revoke authorization for any connected social account at any time via your Social Accounts dashboard.

To exercise any right not directly available through your self-service settings, please contact us at our official contact email listed below.

---

## 9. SECURITY PRACTICES

We employ industry-standard technical and operational measures to protect your data:
- **Encryption in Transit:** All web traffic is strictly served over HTTPS with TLS 1.3 encryption.
- **Encryption at Rest:** Database volumes and cloud storage are encrypted at rest. Third-party OAuth tokens receive an additional layer of application-level AES-256-CBC encryption.
- **Least-Privilege Database Access:** The production web runtime uses a restricted DML database user with zero schema modification (`ALTER`/`DROP`/`CREATE`) permissions.
- **Tenant Isolation:** Multi-tenant boundaries are enforced at the database, route, controller, and storage levels.

However, no method of transmission over the Internet or electronic storage is 100% secure. While we strive to use commercially acceptable means to protect your data, we cannot guarantee absolute security.

---

## 10. CHILDREN'S PRIVACY

SocialPilot AI is a professional marketing and social media management SaaS intended strictly for business and professional use. The Service is not directed to individuals under the age of 18 (or the age of majority in your jurisdiction). We do not knowingly collect personal information from children. If you become aware that a child has provided us with personal data, please contact us immediately.

---

## 11. CHANGES TO THIS PRIVACY POLICY

We may update this Privacy Policy from time to time to reflect changes in our service features, legal requirements, or external platform policies. When material changes are made, we will notify you by updating the "Last Updated" date at the top of this policy and, where appropriate, through an in-app dashboard notification. Your continued use of the Service following any update constitutes your acknowledgment of the revised Privacy Policy.

---

## 12. CONTACT INFORMATION

If you have questions, concerns, or requests regarding this Privacy Policy or our data practices, please contact us at:

**SocialPilot AI**  
**Attention:** Privacy & Data Protection  
**Email:** `[OWNER TO CONFIRM: e.g. support@socialpilot-ai.com]`  
**Physical Address:** `[OWNER TO CONFIRM: Legal Business Address or Country of Operation]`  
