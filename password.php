<?php
require_once __DIR__ . '/config.php';
$user = require_user();
if (google_password_setup_required((int)$user['id'])) go('set-password.php');
$query = db()->prepare('SELECT password_hash FROM users WHERE id = ?');
$query->execute([(int)$user['id']]);
$passwordHash = $query->fetchColumn();
if (!is_string($passwordHash) || $passwordHash === '') {
    throw new RuntimeException('The account password could not be loaded.');
}
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf();
    if (!limit('change_password', 5, 600)) {
        $error = 'Too many attempts. Try again in 10 minutes.';
    } else {
        $currentPassword = (string)($_POST['current_password'] ?? '');
        $newPassword = (string)($_POST['new_password'] ?? '');
        $confirmPassword = (string)($_POST['confirm_password'] ?? '');
        if (!password_verify($currentPassword, $passwordHash)) {
            $error = 'Your current password is incorrect.';
        } elseif (strlen($newPassword) < 12 || !preg_match('/[A-Za-z]/', $newPassword) || !preg_match('/[0-9]/', $newPassword)) {
            $error = 'New password must be at least 12 characters with letters and numbers.';
        } elseif ($newPassword !== $confirmPassword) {
            $error = 'The new passwords do not match.';
        } elseif (password_verify($newPassword, $passwordHash)) {
            $error = 'Choose a password different from your current password.';
        } else {
            $update = db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
            $update->execute([password_hash($newPassword, PASSWORD_DEFAULT), (int)$user['id']]);
            if ($update->rowCount() !== 1) {
                throw new RuntimeException('The password was not updated.');
            }
            create_notification((int)$user['id'], 'Password changed', 'Your account password was changed successfully.');
            unset($_SESSION['rate']['change_password']);
            session_regenerate_id(true);
            go('security.php');
        }
    }
}
page('Change password');
?>
<section class="settings-page">
    <header class="settings-page-heading">
        <span class="kicker">ACCOUNT SETTINGS</span>
        <h1>Password and authentication</h1>
        <p>Change your account password.</p>
    </header>
    <nav class="settings-nav" aria-label="Account settings">
        <a href="account.php"><span aria-hidden="true">◉</span> Profile</a>
        <a class="active" href="security.php"><span aria-hidden="true">◇</span> Password and authentication</a>
    </nav>
    <section class="settings-card password-form-card">
        <div class="settings-card-heading">
            <div><span class="kicker">PASSWORD</span><h2>Change password</h2></div>
        </div>
        <p class="settings-help">Enter your current password to confirm this change. Use at least 12 characters with letters and numbers.</p>
        <?php if ($error !== ''): ?><p class="error" role="alert"><?= h($error) ?></p><?php endif; ?>
        <form method="post" class="password-change-form">
            <input type="hidden" name="csrf" value="<?= h(token()) ?>">
            <label>Current password<input type="password" name="current_password" autocomplete="current-password" required></label>
            <label>New password<input type="password" name="new_password" minlength="12" autocomplete="new-password" required></label>
            <label>Confirm new password<input type="password" name="confirm_password" minlength="12" autocomplete="new-password" required></label>
            <div class="settings-form-actions">
                <a class="settings-cancel" href="security.php">Cancel</a>
                <button class="btn" type="submit">Update password</button>
            </div>
        </form>
    </section>
</section>
<?php endpage(); ?>
