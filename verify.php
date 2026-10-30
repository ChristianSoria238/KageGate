<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/totp.php';
if (empty($_SESSION['pending']) || ($_SESSION['pending_stage'] ?? '') !== 'mfa' || time() - (int)($_SESSION['pending_time'] ?? 0) > 300) {
    unset($_SESSION['pending'], $_SESSION['pending_time'], $_SESSION['pending_stage']);
    go('login.php');
}
$query = db()->prepare('SELECT mfa_secret, mfa_enabled FROM users WHERE id = ?');
$query->execute([(int)$_SESSION['pending']]);
$record = $query->fetch();
if (!$record || !(int)$record['mfa_enabled'] || empty($record['mfa_secret'])) {
    unset($_SESSION['pending'], $_SESSION['pending_time'], $_SESSION['pending_stage']);
    go('login.php');
}
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf();
    if (!limit('verify', 6, 300)) {
        $error = 'Too many incorrect codes. Try again later.';
    } elseif (validotp((string)$record['mfa_secret'], trim((string)($_POST['code'] ?? '')))) {
        $userId = (int)$_SESSION['pending'];
        session_regenerate_id(true);
        finish_login($userId);
        unset($_SESSION['rate']['verify']);
        go('index.php');
    } else {
        $error = 'Code not accepted. Enable automatic date and time on your phone, then enter a fresh code before it changes.';
    }
}
page('Verify second factor');
?>
<section class="auth">
    <span class="kicker">SECOND SECURITY SEAL</span>
    <h1>Verify your identity.</h1>
    <p>Open your authenticator app and enter the current six-digit code.</p>
    <?php if ($error): ?><p class="error" role="alert"><?= h($error) ?></p><?php endif; ?>
    <form method="post">
        <input type="hidden" name="csrf" value="<?= h(token()) ?>">
        <label>Authenticator code<input name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="16" required></label>
        <button class="btn" type="submit">Verify and continue →</button>
    </form>
    <p><a href="login.php">Return to sign in</a></p>
</section>
<?php endpage(); ?>
