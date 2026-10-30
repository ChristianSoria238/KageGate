# KageGate V3 — Full Readable Source

An anime/donghua discovery website and educational PHP + MySQL TOTP MFA project.
All PHP pages contain expanded, editable code. No minified PHP files.

## Requirements
- PHP 8.1+ with PDO MySQL
- PHP cURL, OpenSSL, and mbstring extensions
- Composer dependencies installed (`composer install`)
- MySQL or MariaDB
- Apache / XAMPP or compatible PHP hosting
- A TOTP authenticator app (Google Authenticator, 2FAS, Microsoft Authenticator)

## Local setup in VS Code + XAMPP
1. Extract the ZIP to `C:\xampp\htdocs\kagegate`.
2. Start Apache and MySQL in XAMPP.
3. Open `http://localhost/phpmyadmin` and create a database named `kagegate`.
4. Import `database.sql` into that database.
5. In `config.php`, use `DB_HOST=localhost`, `DB_NAME=kagegate`, `DB_USER=root`, `DB_PASS=''` for default local XAMPP only.
6. Visit `http://localhost/kagegate/`. Use Apache, not VS Code Live Server.

## InfinityFree deployment
This project has only been tested locally with XAMPP. It has **not** been uploaded to or verified on InfinityFree. Check the current InfinityFree panel and support documentation before relying on a feature that needs a specific PHP extension, outbound connection, environment variable, or mail service; hosting limits can change.

1. **Create the hosting account and site.** In the InfinityFree client area, create a hosting account and choose a free subdomain or connect a domain. Confirm the site's document root and the PHP version selected for that site. KageGate requires PHP 8.1+ and `PDO MySQL`, cURL, OpenSSL, and mbstring.
2. **Create the production database.** Use the hosting control panel's MySQL Databases page to create a database and user. Record the exact database host, database name, username, and password shown there; these may include account-specific prefixes and the host is not necessarily `localhost`.
3. **Import the schema.** Open the database's phpMyAdmin from the hosting panel, select the database, choose **Import**, and upload `database.sql`. This script uses `CREATE TABLE IF NOT EXISTS` to add missing tables without replacing existing accounts or data. Confirm the tables are present before continuing. Never import into a database containing unrelated data.
4. **Prepare Composer dependencies locally.** In the project folder, run `composer install --no-dev --optimize-autoloader` using a PHP/Composer setup that satisfies `composer.json`. The finished `vendor/autoload.php` must exist. If Composer is not available on the host, upload the complete generated `vendor` directory along with `composer.json` and `composer.lock`.
5. **Upload the application.** Connect with the FTP credentials and secure connection method provided in the hosting panel. Upload the *contents* of this project—including all PHP pages, `database.sql`, `assets`, and `vendor`—to the website's document root (commonly `htdocs`). Do not upload the project under an extra nested folder unless that folder is intentionally part of the site's URL. Do not upload temporary test files or local database dumps containing personal data.
6. **Configure the database.** Edit `config.php` on the host and replace the four local database constants (`DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`) with the exact values from step 2. Do not leave the XAMPP `root` account or an empty password in the deployed configuration. Keep a private backup of the configured values and never publish them.
7. **Set the passkey hostname.** Before enabling passkeys, set `PASSKEY_RP_ID` in `config.php` to the site's exact hostname, without `https://` or a path (for example, `example.infinityfreeapp.com`). Passkeys are bound to the hostname and require HTTPS in production.
8. **Enable HTTPS.** Use the hosting panel's SSL/HTTPS setup for the chosen domain and verify that the site opens with `https://` before creating accounts or configuring OAuth/passkeys. The app sets secure session cookies when it detects HTTPS. Do not rely on passkeys or MFA enrollment until the HTTPS page loads without certificate warnings.
9. **Configure optional Google sign-in.** Configure the `GOOGLE_*` server environment variables as described below and register the exact HTTPS callback URI, `https://YOUR_HOST/google-callback.php`, in Google Cloud.
10. **Run the production smoke tests.** Register a disposable account, log out and back in, check catalog search/category filters, a title detail page, adding and removing a watchlist item, and its notifications; enroll and verify TOTP MFA; test passkey enrollment only on an HTTPS browser/device that supports WebAuthn; and test Google only after configuring it. Confirm a logged-out visitor is redirected to login. Then delete the disposable account and verify existing accounts and data remain intact.
11. **Keep recovery copies.** Back up the database and the unconfigured application source privately before updates. After a code or configuration change, repeat the relevant smoke tests. Clear old site cookies if they conflict with the current session.

## Security features
- The catalog and title pages require a signed-in account. Password, Google, and passkey sign-ins all return to the protected catalog.
- Signed-in navigation provides Discover, Contact, About, Notifications, Account, VIP, and Log out. The login/register header provides About, Reference, Login, and Join.
- New accounts receive a welcome notification. The Discover homepage greets signed-in members by username. Successful sign-ins, watchlist additions/removals, and account-security changes create in-app notifications, which can be marked read. VIP is an informational placeholder; no payment is processed.
- Registration immediately creates an account with a hashed password. Email confirmation is not required.
- Password and passkey sign-ins do not require emailed codes. If authenticator MFA is enabled, login still requires the current TOTP code.
- Google sign-in uses Google's OpenID Connect authorization-code flow and only accepts a Google-verified email. A verified matching email links an existing account; otherwise a new account is created.
- Newly created Google accounts use a temporary random password. After Google sign-in, the account page offers password setup before MFA/passkey management; the password is never provided by Google.
- If MFA is enabled, sign-in remains pending until `verify.php` accepts a valid TOTP code. No authenticated session is created before MFA passes.
- `mfa.php` generates a random Base32 secret, verifies a code before enabling MFA, and requires both password and code to disable it.
- MFA setup offers both a locally generated QR code and a manual Base32 setup key. The QR code is generated in the browser with the bundled MIT-licensed QRCode.js library; the OTP URI is not sent to an external QR service.
- Account settings show explicit enabled/disabled states for authenticator MFA and passkeys. Signed-in users can change a password after confirming the current password; there is no email-based forgotten-password flow while transactional email is not configured.
- TOTP uses RFC 6238 (SHA-1, six digits, 30-second period) and accepts a code with spaces or hyphens copied from an authenticator. For a rejected code, enable automatic date and time on the phone and enter a fresh code before it expires.
- Passkeys use WebAuthn public-key credentials. The site stores the public key, never fingerprint or face data; the browser and device perform user verification.
- Passkey sign-in uses the username/email plus the device passkey; the password is not needed for that sign-in. Device user verification is required. Passkey enrollment and removal require the account password.
- Passkey and authenticator setup require HTTPS in production. `localhost` is allowed by browsers for local development.
- CSRF tokens protect state-changing POST requests.
- Login and verification attempts are limited per PHP session. For a real production system, add IP/account-based throttling, backup codes, secure account recovery, secret encryption at rest, and monitoring.

## Google sign-in setup
1. Import `database.sql` (the `login_activity` and `google_accounts` tables are created without replacing existing users).
   If you already created `google_accounts` from an earlier iteration of this feature and it lacks `password_set`, run once: `ALTER TABLE google_accounts ADD COLUMN password_set TINYINT(1) NOT NULL DEFAULT 0 AFTER user_id;`.
2. In Google Cloud Console, create an OAuth 2.0 Web application client. Add the exact deployed callback URI, for example `https://your-domain.example/google-callback.php`, as an authorized redirect URI.
3. Configure these server environment variables; do not put credentials in source control:
   - `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_REDIRECT_URI`
4. Install Composer dependencies (`composer install`) and enable PHP cURL and OpenSSL. Google OAuth requires outbound HTTPS access.
5. For local testing, set the Google redirect URI to the exact `http://localhost/kagegate/google-callback.php` URI registered in the OAuth client. Google may require HTTPS for non-local production origins.

Keep the OAuth client secret in the Apache/PHP server environment, not in `config.php` or a public file. In local XAMPP, configure the values for Apache and restart it so PHP can read them with `getenv()`. Hosting providers differ in whether they allow custom environment variables; confirm this feature before enabling Google sign-in.

## Catalog
The catalog is loaded from AniList GraphQL and cached in MySQL for six hours. It includes 75 popular and 75 recent Japanese anime, 75 popular and 75 recent Chinese donghua, plus 50 manga, 50 Korean manhwa, and 50 novels (450 titles total). The homepage shows AniList upcoming episode releases over the next seven days (times in Philippines Time, UTC+8), with Anime/Donghua filters, a 10-minute schedule cache, randomized anime and donghua picks, and separate AniList-popularity top-10 charts. Schedule entries appear only when AniList lists a future episode time for a title. Library search supports category, genre, season, studio, status, format, and sort filters; alternate titles, detail pages, and the persistent watchlist use the same metadata. Mature-theme badges are based on AniList tags; titles explicitly marked as adult are excluded. Some requested titles may not be indexed by AniList and therefore cannot be added from this source.

The LuciferDonghua homepage was used as a reference for donghua title candidates (including 2026 listings). Catalog metadata and artwork are fetched from AniList; episode cards link to the HTTPS source provided in AniList and do not display third-party provider names. Availability varies by region and provider.

YouTube trailers may be embedded. Episode cards show the title artwork and episode name, then open AniList's HTTPS source link without provider branding. AniList is a metadata API, not a license to stream full episodes; availability depends on the source and region. This project does not host or proxy video files.

After importing the updated `database.sql`, run `composer install` locally and upload `composer.json`, `composer.lock`, and the generated `vendor/` directory along with the app files when your host does not provide Composer. Set `PASSKEY_RP_ID` in `config.php` to your site's exact hostname (without `https://` or a path) before enabling public passkeys; `localhost` is used automatically for local development when it is left empty.

## Security notes
- Never upload real DB passwords to GitHub or share them in chat.
- Rotate any database password previously exposed.
- Keep server and phone clocks synchronized for TOTP; the verifier allows the current and immediately adjacent 30-second steps.
- MFA enrollment is optional in this educational build. For mandatory MFA, enforce setup before permitting other account functions.
- Do not use as a production identity service without an independent security review.
