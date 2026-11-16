# Platform API Setup & Developer Console Configuration Guide

## 1. Meta for Developers (Facebook & Instagram)
1. Register an App at [developers.facebook.com](https://developers.facebook.com/) under the **Business** app type.
2. Add **Facebook Login for Business** and **Instagram Graph API** products.
3. Add redirect URI: `https://your-domain.com/social/callback/facebook` and `https://your-domain.com/social/callback/instagram`.
4. Configure in `.env`:
   ```env
   META_APP_ID=your_meta_app_id
   META_APP_SECRET=your_meta_app_secret
   META_REDIRECT_URI=https://your-domain.com/social/callback/facebook
   ```

## 2. TikTok for Developers
1. Register at [developers.tiktok.com](https://developers.tiktok.com/) and request access to **Content Posting API**.
2. Configure OAuth redirect URI: `https://your-domain.com/social/callback/tiktok`.
3. Configure in `.env`:
   ```env
   TIKTOK_CLIENT_KEY=your_tiktok_client_key
   TIKTOK_CLIENT_SECRET=your_tiktok_client_secret
   TIKTOK_REDIRECT_URI=https://your-domain.com/social/callback/tiktok
   ```

## 3. Google Cloud Console (YouTube)
1. Create a Project in [Google Cloud Console](https://console.cloud.google.com/).
2. Enable **YouTube Data API v3**.
3. Create OAuth 2.0 Credentials (Web Application) with redirect URI: `https://your-domain.com/social/callback/youtube`.
4. Configure in `.env`:
   ```env
   GOOGLE_CLIENT_ID=your_google_client_id
   GOOGLE_CLIENT_SECRET=your_google_client_secret
   GOOGLE_REDIRECT_URI=https://your-domain.com/social/callback/youtube
   ```

## 4. LinkedIn Developer Portal
1. Create an App in [developer.linkedin.com](https://developer.linkedin.com/).
2. Request **Share on LinkedIn** and **Sign In with LinkedIn using OpenID Connect** products.
3. Configure redirect URI: `https://your-domain.com/social/callback/linkedin`.
4. Configure in `.env`:
   ```env
   LINKEDIN_CLIENT_ID=your_linkedin_client_id
   LINKEDIN_CLIENT_SECRET=your_linkedin_client_secret
   LINKEDIN_REDIRECT_URI=https://your-domain.com/social/callback/linkedin
   ```
