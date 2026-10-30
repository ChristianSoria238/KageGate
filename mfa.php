<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/totp.php';
$user = require_user();
if (google_password_setup_required((int)$user['id'])) go('set-password.php');
$query = db()->prepare('SELECT password_hash, mfa_secret, mfa_enabled FROM users WHERE id = ?');
$query->execute([(int)$user['id']]);
$record = $query->fetch();
if (!$record) go('login.php');
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf();
    if (!limit('mfa_actions', 10, 300)) {
        $error = 'Too many attempts. Try again later.';
    } else {
        $action = (string)($_POST['action'] ?? '');
        if ($action === 'begin' && !(int)$record['mfa_enabled']) {
            $_SESSION['setup_secret'] = secret();
            go('mfa.php');
        } elseif ($action === 'confirm' && !(int)$record['mfa_enabled'] && !empty($_SESSION['setup_secret'])) {
            $code = trim((string)($_POST['code'] ?? ''));
            if (validotp((string)$_SESSION['setup_secret'], $code)) {
                $update = db()->prepare('UPDATE users SET mfa_secret = ?, mfa_enabled = 1 WHERE id = ?');
                $update->execute([$_SESSION['setup_secret'], (int)$user['id']]);
                create_notification((int)$user['id'], 'Authenticator enabled', 'Authenticator MFA was enabled for your account.');
                unset($_SESSION['setup_secret']);
                go('account.php');
            }
            $error = 'Code not accepted. Check that your phone uses automatic date and time, then enter a fresh six-digit code.';
        } elseif ($action === 'disable' && (int)$record['mfa_enabled']) {
            $password = (string)($_POST['password'] ?? '');
            $code = trim((string)($_POST['code'] ?? ''));
            if (password_verify($password, $record['password_hash'])
                && validotp((string)$record['mfa_secret'], $code)) {
                $update = db()->prepare('UPDATE users SET mfa_secret = NULL, mfa_enabled = 0 WHERE id = ?');
                $update->execute([(int)$user['id']]);
                create_notification((int)$user['id'], 'Authenticator disabled', 'Authenticator MFA was disabled for your account.');
                go('account.php');
            }
            $error = 'Password or authenticator code is incorrect.';
        }
    }
}
page('Authenticator MFA');
?>
<section class="settings-page">
    <header class="settings-page-heading">
        <span class="kicker">ACCOUNT SETTINGS</span>
        <h1>Password and authentication</h1>
        <p>Manage your password, authenticator, and passkeys.</p>
    </header>
    <nav class="settings-nav" aria-label="Account settings">
        <a href="account.php"><span aria-hidden="true">◉</span> Profile</a>
        <a class="active" href="security.php"><span aria-hidden="true">◇</span> Password and authentication</a>
    </nav>
    <div class="settings-card mfa-page-card">
    <span class="kicker">TWO-FACTOR AUTHENTICATION</span>
    <h2>Authenticator app</h2>
    <p>Use Google Authenticator, Microsoft Authenticator, 2FAS, or any compatible TOTP app.</p>
    <?php if ($error): ?><p class="error" role="alert"><?= h($error) ?></p><?php endif; ?>
    <?php if ((int)$record['mfa_enabled']): ?>
        <p class="success">✓ MFA is enabled. Your next login will require a six-digit code.</p>
        <h2>Disable MFA</h2>
        <p>For your security, disabling MFA requires both your password and current code.</p>
        <form method="post">
            <input type="hidden" name="csrf" value="<?= h(token()) ?>">
            <input type="hidden" name="action" value="disable">
            <label>Password<input type="password" name="password" required></label>
            <label>Current six-digit code<input name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="16" required></label>
            <button class="btn" type="submit">Disable MFA</button>
        </form>
    <?php elseif (!empty($_SESSION['setup_secret'])): ?>
        <?php
        $otpLabel = rawurlencode('KageGate:' . (string)$user['email']);
        $otpAuthUri = 'otpauth://totp/' . $otpLabel
            . '?secret=' . rawurlencode((string)$_SESSION['setup_secret'])
            . '&issuer=' . rawurlencode(APP_NAME)
            . '&algorithm=SHA1&digits=6&period=30';
        ?>
        <div class="mfa-setup-card">
            <div class="mfa-setup-heading">
                <span class="mfa-step">01</span>
                <div><h2>Connect your authenticator</h2><p>Scan the QR code or choose setup key to enter it manually.</p></div>
            </div>
            <div class="mfa-method-tabs" role="tablist" aria-label="Authenticator setup method">
                <button class="mfa-method-tab active" id="mfa-qr-tab" type="button" role="tab" aria-selected="true" aria-controls="mfa-qr-panel" data-mfa-method="qr">Scan QR code</button>
                <button class="mfa-method-tab" id="mfa-key-tab" type="button" role="tab" aria-selected="false" aria-controls="mfa-key-panel" data-mfa-method="key">Enter setup key</button>
            </div>
            <section class="mfa-method-panel mfa-qr-panel" id="mfa-qr-panel" role="tabpanel" aria-labelledby="mfa-qr-tab">
                <div class="mfa-qr-frame">
                    <div id="mfa-qr-code" data-otpauth="<?= h($otpAuthUri) ?>" aria-label="Authenticator enrollment QR code"></div>
                    <span id="mfa-qr-error" class="mfa-qr-error" role="status" hidden>QR code could not be displayed. Select “Enter setup key” to continue.</span>
                </div>
                <div class="mfa-qr-help">
                    <strong>Scan with your authenticator app</strong>
                    <span>Open your authenticator, add an account, and scan this QR code. It is generated on this page and is not sent to an external QR service.</span>
                    <small><?= h($user['email']) ?> · Time-based · 6 digits · 30 seconds</small>
                </div>
            </section>
            <section class="mfa-method-panel mfa-key-panel" id="mfa-key-panel" role="tabpanel" aria-labelledby="mfa-key-tab" hidden>
                <div>
                    <strong>Manual setup key</strong>
                    <p>In your authenticator app choose “Enter setup key.”</p>
                    <div class="secret"><?= h($_SESSION['setup_secret']) ?></div>
                    <small>Time-based · SHA-1 · 6 digits · 30-second period</small>
                </div>
            </section>
            <p class="mfa-secret-warning">Keep the QR code and setup key private. Anyone who can see either can generate your sign-in codes.</p>
        </div>
        <div class="mfa-setup-card mfa-confirm-card">
            <div class="mfa-setup-heading">
                <span class="mfa-step">02</span>
                <div><h2>Verify and enable MFA</h2><p>Enter the current six-digit code from the authenticator to finish setup.</p></div>
            </div>
        <form method="post">
            <input type="hidden" name="csrf" value="<?= h(token()) ?>">
            <input type="hidden" name="action" value="confirm">
            <label>Six-digit code<input name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="16" placeholder="000000" required></label>
            <button class="btn" type="submit">Activate MFA →</button>
        </form>
        </div>
    <?php else: ?>
        <p>MFA adds a second factor to your password. You will need your authenticator every time you log in.</p>
        <form method="post">
            <input type="hidden" name="csrf" value="<?= h(token()) ?>">
            <input type="hidden" name="action" value="begin">
            <button class="btn" type="submit">Generate setup key →</button>
        </form>
    <?php endif; ?>
    <a class="settings-back" href="security.php">← Back to password and authentication</a>
    </div>
</section>
<?php endpage(); ?>
