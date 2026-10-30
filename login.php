<?php
require_once __DIR__ . '/config.php';
if (me()) go('index.php');
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf();
    if (!limit('login', 8, 300)) {
        $error = 'Too many attempts. Try again in five minutes.';
    } else {
        try {
            $login = trim((string)($_POST['login'] ?? ''));
            $password = (string)($_POST['password'] ?? '');
            $query = db()->prepare('SELECT id, password_hash FROM users WHERE username = ? OR email = ? LIMIT 1');
            $query->execute([$login, $login]);
            $record = $query->fetch();
            if ($record && password_verify($password, $record['password_hash'])) {
                go(begin_login((int)$record['id']));
            } else {
                $error = 'Incorrect username, email, or password.';
            }
        } catch (PDOException $exception) {
            error_log($exception->getMessage());
            $error = 'Sign-in services are unavailable. Check the database configuration.';
        }
    }
}
page('Sign in');
?>
<section class="auth auth-layout login-layout">
    <div class="auth-story">
        <span class="kicker">WELCOME BACK, TRAVELER</span>
        <h1>Your next story is waiting.</h1>
        <p>Step back into your personal animeverse. Your watchlist and account security are ready when you are.</p>
        <div class="auth-perks">
            <span><i aria-hidden="true">✦</i> Your library, all in one place</span>
            <span><i aria-hidden="true">◇</i> Sign in with MFA or a passkey</span>
        </div>
    </div>
    <div class="auth-card">
        <span class="kicker">MEMBER SIGN IN</span>
        <h2>Unlock your gate</h2>
        <p class="auth-subtitle">Use your account details to continue.</p>
        <?php if ($error): ?><p class="error" role="alert"><?= h($error) ?></p><?php endif; ?>
        <form method="post">
            <input type="hidden" name="csrf" value="<?= h(token()) ?>">
            <label>Username or email
                <input name="login" autocomplete="username" placeholder="Your username or email" required>
            </label>
            <label>Password
                <input type="password" name="password" autocomplete="current-password" placeholder="Enter your password" required>
            </label>
            <button class="btn auth-submit" type="submit">Sign in securely <span aria-hidden="true">→</span></button>
        </form>
        <div class="auth-divider"><span>or continue with</span></div>
        <?php if (google_login_available()): ?>
            <a class="btn google-button" href="google-auth.php"><span class="google-mark" aria-hidden="true">G</span> Continue with Google</a>
        <?php else: ?>
            <button class="btn google-button" type="button" disabled aria-label="Google sign-in is not configured">
                <svg class="google-mark" viewBox="0 0 48 48" aria-hidden="true">
                    <path fill="#4285F4" d="M43.6 24.5c0-1.4-.1-2.8-.4-4.1H24v7.8h11a9.4 9.4 0 0 1-4.1 6.2v5.1h6.7c3.9-3.6 6-8.9 6-15z"/>
                    <path fill="#34A853" d="M24 44c5.5 0 10.1-1.8 13.5-4.8l-6.7-5.1c-1.8 1.2-4.1 2-6.8 2-5.2 0-9.6-3.5-11.2-8.2H5.9v5.3A20 20 0 0 0 24 44z"/>
                    <path fill="#FBBC05" d="M12.8 27.9a12 12 0 0 1 0-7.8v-5.3H5.9a20 20 0 0 0 0 18.4l6.9-5.3z"/>
                    <path fill="#EA4335" d="M24 11.9c3 0 5.7 1 7.8 3.1l5.8-5.8C34.1 5.9 29.5 4 24 4A20 20 0 0 0 5.9 14.8l6.9 5.3c1.6-4.7 6-8.2 11.2-8.2z"/>
                </svg>
                Continue with Google
            </button>
        <?php endif; ?>
        <button id="passkey-login" class="btn passkey-button" type="button" data-csrf="<?= h(token()) ?>"><span aria-hidden="true">⌘</span> Sign in with a passkey</button>
        <div id="passkey-status" role="status" aria-live="polite"></div>
        <p class="auth-switch">New to KageGate? <a href="register.php">Create your account <span aria-hidden="true">→</span></a></p>
    </div>
</section>
<?php endpage(); ?>
