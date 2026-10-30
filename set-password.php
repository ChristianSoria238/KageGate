<?php
require_once __DIR__ . '/config.php';
$user = require_user();
if (!google_password_setup_required((int)$user['id'])) go('account.php');
$recentGoogleSignIn = time() - (int)($_SESSION['google_authenticated_at'] ?? 0) <= 600;
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf();
    if (!$recentGoogleSignIn) {
        $error = 'For security, sign in with Google again before setting a password.';
    } elseif (!limit('set_password', 5, 600)) {
        $error = 'Too many attempts. Try again in 10 minutes.';
    } else {
        $password = (string)($_POST['password'] ?? '');
        $confirm = (string)($_POST['confirm_password'] ?? '');
        if (strlen($password) < 12 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
            $error = 'Password must be at least 12 characters with letters and numbers.';
        } elseif ($password !== $confirm) {
            $error = 'Passwords do not match.';
        } else {
            try {
                $connection = db();
                $connection->beginTransaction();
                $updatePassword = $connection->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
                $updatePassword->execute([password_hash($password, PASSWORD_DEFAULT), (int)$user['id']]);
                $markPasswordSet = $connection->prepare('UPDATE google_accounts SET password_set = 1 WHERE user_id = ?');
                $markPasswordSet->execute([(int)$user['id']]);
                if ($markPasswordSet->rowCount() !== 1) {
                    throw new RuntimeException('Google account password status was not updated.');
                }
                $connection->commit();
                unset($_SESSION['google_authenticated_at'], $_SESSION['rate']['set_password']);
                go('account.php');
            } catch (Throwable $exception) {
                if (isset($connection) && $connection instanceof PDO && $connection->inTransaction()) {
                    $connection->rollBack();
                }
                error_log('KageGate password setup failed: ' . $exception->getMessage());
                $error = 'Could not save the password. Try again.';
            }
        }
    }
}
page('Set account password');
?>
<section class="auth">
    <span class="kicker">SECURE YOUR ACCOUNT</span>
    <h1>Set an account password.</h1>
    <p>A recent Google sign-in is required. Setting a password lets you manage authenticator MFA and register passkeys on this account.</p>
    <?php if (!$recentGoogleSignIn): ?>
        <p class="notice">Your Google sign-in is no longer recent. Sign out, sign in with Google again, and then set your password.</p>
    <?php endif; ?>
    <?php if ($error): ?><p class="error" role="alert"><?= h($error) ?></p><?php endif; ?>
    <form method="post">
        <input type="hidden" name="csrf" value="<?= h(token()) ?>">
        <label>Password <span class="label-hint">12+ characters, letters and numbers</span><input type="password" name="password" minlength="12" autocomplete="new-password" required></label>
        <label>Confirm password<input type="password" name="confirm_password" minlength="12" autocomplete="new-password" required></label>
        <button class="btn" type="submit" <?= $recentGoogleSignIn ? '' : 'disabled' ?>>Save password →</button>
    </form>
    <p><a href="account.php">← Return to account</a></p>
</section>
<?php endpage(); ?>
