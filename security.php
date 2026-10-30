<?php
require_once __DIR__ . '/config.php';
$user = require_user();
$passkeyQuery = db()->prepare('SELECT COUNT(*) FROM passkeys WHERE user_id = ?');
$passkeyQuery->execute([(int)$user['id']]);
$passkeyCount = (int)$passkeyQuery->fetchColumn();
$mfaEnabled = (int)$user['mfa_enabled'] === 1;
$needsPasswordSetup = google_password_setup_required((int)$user['id']);
page('Password and authentication');
?>
<section class="settings-page">
    <header class="settings-page-heading">
        <span class="kicker">ACCOUNT SETTINGS</span>
        <h1>Password and authentication</h1>
        <p>Manage how you sign in and protect your KageGate account.</p>
    </header>
    <nav class="settings-nav" aria-label="Account settings">
        <a href="account.php"><span aria-hidden="true">◉</span> Profile</a>
        <a class="active" href="security.php"><span aria-hidden="true">◇</span> Password and authentication</a>
    </nav>

    <section class="settings-card" aria-labelledby="sign-in-methods">
        <div class="settings-card-heading">
            <div><span class="kicker">SIGN-IN METHODS</span><h2 id="sign-in-methods">Your sign-in methods</h2></div>
        </div>
        <article class="method-row">
            <span class="method-icon" aria-hidden="true">Aa</span>
            <div class="method-copy">
                <h3>Password</h3>
                <p><?= $needsPasswordSetup ? 'Set a password before managing local sign-in methods.' : 'Password sign-in is configured for this account.' ?></p>
            </div>
            <span class="security-state <?= $needsPasswordSetup ? 'is-disabled' : 'is-enabled' ?>"><span class="state-dot" aria-hidden="true"></span><?= $needsPasswordSetup ? 'Setup required' : 'Configured' ?></span>
            <a class="settings-action" href="<?= $needsPasswordSetup ? 'set-password.php' : 'password.php' ?>"><?= $needsPasswordSetup ? 'Set password' : 'Change password' ?></a>
        </article>
        <article class="method-row">
            <span class="method-icon" aria-hidden="true">⌁</span>
            <div class="method-copy">
                <h3>Passkeys</h3>
                <p><?= $passkeyCount > 0 ? 'Use a device passkey to sign in.' : 'Use a device PIN, fingerprint, or face unlock where supported.' ?></p>
            </div>
            <span class="security-state <?= $passkeyCount > 0 ? 'is-enabled' : 'is-disabled' ?>"><span class="state-dot" aria-hidden="true"></span><?= $passkeyCount > 0 ? 'Enabled · ' . $passkeyCount : 'Not enabled' ?></span>
            <a class="settings-action" href="passkeys.php">Manage</a>
        </article>
        <article class="method-row">
            <span class="method-icon" aria-hidden="true">◷</span>
            <div class="method-copy">
                <h3>Authenticator app</h3>
                <p>Use a time-based one-time code as an additional sign-in step.</p>
            </div>
            <span class="security-state <?= $mfaEnabled ? 'is-enabled' : 'is-disabled' ?>"><span class="state-dot" aria-hidden="true"></span><?= $mfaEnabled ? 'Enabled' : 'Not enabled' ?></span>
            <a class="settings-action" href="mfa.php"><?= $mfaEnabled ? 'Manage' : 'Enable' ?></a>
        </article>
    </section>

    <section class="settings-card" aria-labelledby="two-factor-heading">
        <div class="settings-card-heading">
            <div><span class="kicker">TWO-FACTOR AUTHENTICATION</span><h2 id="two-factor-heading">Authenticator app</h2></div>
            <span class="security-state <?= $mfaEnabled ? 'is-enabled' : 'is-disabled' ?>"><span class="state-dot" aria-hidden="true"></span><?= $mfaEnabled ? 'Enabled' : 'Disabled' ?></span>
        </div>
        <p><?= $mfaEnabled ? 'Your account asks for a current authenticator code at sign-in.' : 'Add an authenticator app so a password alone cannot sign in to your account.' ?></p>
        <a class="settings-action" href="mfa.php"><?= $mfaEnabled ? 'Manage authenticator' : 'Set up authenticator' ?></a>
    </section>

    <section class="settings-card password-reset-note" aria-labelledby="password-reset-heading">
        <div class="settings-card-heading">
            <div><span class="kicker">PASSWORD RECOVERY</span><h2 id="password-reset-heading">Change your password</h2></div>
        </div>
        <p>For security, changing a password requires your current password. Email-based password recovery is not available because email delivery is not configured.</p>
        <a class="settings-action" href="<?= $needsPasswordSetup ? 'set-password.php' : 'password.php' ?>"><?= $needsPasswordSetup ? 'Set password' : 'Change password' ?></a>
    </section>
</section>
<?php endpage(); ?>
