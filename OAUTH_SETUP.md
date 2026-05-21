# CodeKeep - Social OAuth Setup Guide

## Enabling Gmail & Facebook Login

This guide will help you set up Gmail and Facebook OAuth authentication for CodeKeep.

### Prerequisites
- Your CodeKeep application running on localhost or a public domain
- Google and Facebook developer accounts

---

## 1. Google OAuth Setup

### Step 1: Create a Google Cloud Project
1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Click "Create Project" and name it "CodeKeep"
3. Wait for the project to be created

### Step 2: Enable OAuth 2.0
1. In the left sidebar, go to **APIs & Services** → **Credentials**
2. Click **Create Credentials** → **OAuth 2.0 Client ID**
3. If prompted, first configure the OAuth consent screen:
   - Choose **External** as the User Type
   - Fill in the app name: "CodeKeep"
   - Add your email in contact information
   - Save and continue
4. Return to creating the OAuth Client ID
5. Choose **Web application**
6. Add Authorized Redirect URIs:
   ```
   http://localhost/CodeKeep/api/oauth_callback.php?provider=google
   ```
   (Replace `localhost` with your domain if deployed)
7. Click **Create** and copy the credentials

### Step 3: Add to CodeKeep Config
Edit `config/config.php` and update:
```php
define('GOOGLE_CLIENT_ID', 'YOUR_GOOGLE_CLIENT_ID_HERE');
define('GOOGLE_CLIENT_SECRET', 'YOUR_GOOGLE_CLIENT_SECRET_HERE');
```

---

## 2. Facebook OAuth Setup

### Step 1: Create a Facebook App
1. Go to [Facebook Developers](https://developers.facebook.com/)
2. Click **Create App**
3. Choose **Consumer** app type
4. Fill in the app details and create the app

### Step 2: Configure OAuth Settings
1. In your app dashboard, go to **Settings** → **Basic** and copy the App ID and App Secret
2. Go to **Products** → **Add Product** → Search for **Facebook Login** → **Set Up**
3. Choose **Web**
4. In the left sidebar under Facebook Login, go to **Settings**
5. Add Valid OAuth Redirect URIs:
   ```
   http://localhost/CodeKeep/api/oauth_callback.php?provider=facebook
   ```
   (Replace `localhost` with your domain if deployed)
6. Save changes

### Step 3: Add to CodeKeep Config
Edit `config/config.php` and update:
```php
define('FACEBOOK_APP_ID', 'YOUR_FACEBOOK_APP_ID_HERE');
define('FACEBOOK_APP_SECRET', 'YOUR_FACEBOOK_APP_SECRET_HERE');
```

---

## 3. Update Database (First Time Only)

Run the following SQL to add OAuth fields to your users table if you haven't already:

```sql
ALTER TABLE users ADD COLUMN oauth_provider VARCHAR(50) DEFAULT NULL;
ALTER TABLE users ADD COLUMN oauth_id VARCHAR(255) DEFAULT NULL;
ALTER TABLE users MODIFY password VARCHAR(255);
```

This allows users who sign up via OAuth to have no password initially.

---

## 4. Testing

1. Go to your login page: `http://localhost/CodeKeep/views/login.php`
2. Click the **Google** or **Facebook** button
3. You should be redirected to the OAuth provider's login page
4. After authorization, you'll be redirected back and logged in!

---

## Notes

- **Local Development**: For localhost testing, Google may restrict some features. Deploy to a proper domain for full functionality.
- **Email Requirement**: Both Google and Facebook require verified email accounts.
- **Account Linking**: If a user already has an account with the same email, their OAuth account will be linked to it.
- **Security**: Keep your OAuth secrets private and never commit them to version control.

---

## Troubleshooting

**"Invalid provider" error**
- Ensure the OAuth provider is set to either 'google' or 'facebook' in the URL

**"Failed to get access token"**
- Check that your Client ID and Secret are correct
- Verify your redirect URI matches exactly in both the config and provider settings

**"cURL error"**
- Ensure cURL is enabled on your server (usually enabled by default on Laragon)
- Check firewall/proxy settings if on a corporate network

---

Happy coding! 🚀
