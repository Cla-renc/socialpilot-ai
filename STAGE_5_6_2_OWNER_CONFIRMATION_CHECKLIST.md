# STAGE 5.6.2 — OWNER CONFIRMATION CHECKLIST & PUBLIC PAGE DESIGN PLAN

**Project:** SocialPilot AI  
**Production Host:** `https://socialpilot-ai-y4lm.onrender.com`  
**Execution Timestamp:** `2026-11-23T14:34:00Z` (`17:34:00` Local)  
**Deliverable Type:** ACTIONABLE OWNER DECISION CHECKLIST & IMPLEMENTATION PLAN  

---

## 1. OWNER CONFIRMATION CHECKLIST

Before the draft compliance documents ([Privacy Policy](file:///c:/PROJECTS/SOCIALPILOT%20AI/SOCIALPILOT_AI_PRIVACY_POLICY_DRAFT.md), [Terms of Service](file:///c:/PROJECTS/SOCIALPILOT%20AI/SOCIALPILOT_AI_TERMS_OF_SERVICE_DRAFT.md), and [Data Deletion Instructions](file:///c:/PROJECTS/SOCIALPILOT%20AI/SOCIALPILOT_AI_DATA_DELETION_INSTRUCTIONS_DRAFT.md)) can be published to live production and submitted to Meta, TikTok, Google, or LinkedIn, the project owner must confirm the following factual placeholders:

| Placeholder ID | Field Description | Current Draft Value | Owner Input / Confirmed Value Required |
| :--- | :--- | :--- | :--- |
| **`CONF-01`** | **Official Support Email** | `support@socialpilot-ai.com` | Specify active support/contact email (to set in `CONTACT_EMAIL=`). |
| **`CONF-02`** | **Privacy/Legal Contact Email** | `privacy@socialpilot-ai.com` | Specify designated email for privacy & data deletion inquiries. |
| **`CONF-03`** | **Legal Entity / Business Name** | `SocialPilot AI` | Specify formal business entity (e.g. `SocialPilot AI Inc.`, `LLC`, or personal name). |
| **`CONF-04`** | **Physical Business Address** | `[OWNER TO CONFIRM]` | City, State/Province, and Country of legal operation. (Required for Google verification & CAN-SPAM compliance). |
| **`CONF-05`** | **Governing Jurisdiction** | `[OWNER TO CONFIRM]` | State / Country whose laws govern the Terms of Service. |
| **`CONF-06`** | **Effective Date** | `November 24, 2026` | Target publication launch date. |
| **`CONF-07`** | **Database Snapshot Retention** | `7 to 30 days` | Confirm TiDB Cloud rolling snapshot retention window. |
| **`CONF-08`** | **Arbitration / Court Venue** | `[OWNER TO CONFIRM]` | Preferred city/court venue for legal dispute resolution. |

> [!NOTE]
> None of the placeholder items above affect technical OAuth functionality, but they are scrutinized during Meta Business Verification and Google OAuth Verification reviews.

---

## 2. PUBLIC COMPLIANCE PAGES DESIGN PLAN

To eliminate the App Review blocker identified in Stage 5.6.1, SocialPilot AI requires three dedicated, publicly accessible web routes.

### 2.1. Recommended Public Routes (`routes/web.php`)

```php
// Public Compliance & Legal Pages (No auth required)
Route::view('/privacy', 'legal.privacy')->name('privacy');
Route::view('/terms', 'legal.terms')->name('terms');
Route::view('/data-deletion', 'legal.data-deletion')->name('data-deletion');
```

These routes are public, unauthenticated, and cached by Cloudflare edge nodes.

### 2.2. Recommended Blade Views Directory Structure

```
resources/views/
├── legal/
│   ├── layout.blade.php        # Shared legal layout (clean marketing topbar + document body + footer)
│   ├── privacy.blade.php       # Renders SOCIALPILOT_AI_PRIVACY_POLICY_DRAFT.md content
│   ├── terms.blade.php         # Renders SOCIALPILOT_AI_TERMS_OF_SERVICE_DRAFT.md content
│   └── data-deletion.blade.php # Renders SOCIALPILOT_AI_DATA_DELETION_INSTRUCTIONS_DRAFT.md content
```

### 2.3. Layout & Styling Conventions
- **Shared Layout (`resources/views/legal/layout.blade.php`):**
  - Inherits standard fonts (`Figtree`), styles (`assets/socialpilot.css`), and logos (`assets/logo.png`) from `welcome.blade.php`.
  - Includes a simplified topbar: Brand mark linking to `/`, "Log in" button, and "Create account" button.
  - Centered document container (`max-width: 860px; margin: 2rem auto; padding: 2rem; background: var(--surface); border: 1px solid var(--stroke); border-radius: 12px;`).
  - Standard marketing footer linking between `/privacy`, `/terms`, and `/data-deletion`.

### 2.4. Navigation & Footer Integration
Update the footers in `resources/views/welcome.blade.php` and registration views:
```html
<!-- Footer Links -->
<footer class="marketing-footer">
    <a class="brand-mark" href="{{ route('welcome') }}">...</a>
    <span>Social planning, made more manageable.</span>
    <div class="footer-links">
        <a href="{{ route('terms') }}">Terms of Service</a>
        <a href="{{ route('privacy') }}">Privacy Policy</a>
        <a href="{{ route('data-deletion') }}">User Data Deletion</a>
    </div>
</footer>
```

And in `resources/views/auth/register.blade.php`:
```html
<label for="terms">
    I agree to the <a href="{{ route('terms') }}" target="_blank">Terms of Service</a> 
    and acknowledge the <a href="{{ route('privacy') }}" target="_blank">Privacy Policy</a>.
</label>
```

---

## 3. EXTERNAL CONSOLE SUBMISSION URLS MATRIX

Once the three routes are deployed to production, the owner can directly configure the following URLs in external developer portals:

| Developer Console | Setting / Field Name | Production URL to Submit |
| :--- | :--- | :--- |
| **Meta for Developers** | **Privacy Policy URL** | `https://socialpilot-ai-y4lm.onrender.com/privacy` |
| **Meta for Developers** | **Terms of Service URL** | `https://socialpilot-ai-y4lm.onrender.com/terms` |
| **Meta for Developers** | **User Data Deletion Instructions URL** | `https://socialpilot-ai-y4lm.onrender.com/data-deletion` |
| **TikTok for Developers** | **Terms of Service URL** | `https://socialpilot-ai-y4lm.onrender.com/terms` |
| **TikTok for Developers** | **Privacy Policy URL** | `https://socialpilot-ai-y4lm.onrender.com/privacy` |
| **Google Cloud Console** | **Application Privacy Policy link** | `https://socialpilot-ai-y4lm.onrender.com/privacy` |
| **Google Cloud Console** | **Application Terms of Service link** | `https://socialpilot-ai-y4lm.onrender.com/terms` |
| **LinkedIn Developer Portal** | **Privacy Policy URL** | `https://socialpilot-ai-y4lm.onrender.com/privacy` |

---

## 4. NEXT ACTIONS REQUIRED FOR IMPLEMENTATION

When authorized in **Stage 5.6.3 — Compliance Pages Implementation & Deployment**:
1. Confirm the 8 checklist items above with the project owner.
2. Replace placeholders in the draft markdown documents.
3. Create `resources/views/legal/layout.blade.php`, `privacy.blade.php`, `terms.blade.php`, and `data-deletion.blade.php`.
4. Register the three public routes in `routes/web.php`.
5. Update `welcome.blade.php` and `register.blade.php` footer links.
6. Verify locally and deploy to Render production.

*(STOPPED. Drafting phase completed. Awaiting owner review and authorization.)*
