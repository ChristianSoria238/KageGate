<?php
require_once __DIR__ . '/config.php';
$user = require_user();
if (google_password_setup_required((int)$user['id'])) go('set-password.php');
$query = db()->prepare('SELECT id, label, created_at FROM passkeys WHERE user_id = ? ORDER BY id DESC');
$query->execute([(int)$user['id']]);
$passkeys = $query->fetchAll();
page('Manage passkeys');
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
    <section class="settings-card">
    <div class="settings-card-heading">
        <div><span class="kicker">PASSKEYS</span><h2>Manage passkeys</h2></div>
        <span class="security-state <?= $passkeys ? 'is-enabled' : 'is-disabled' ?>"><span class="state-dot" aria-hidden="true"></span><?= count($passkeys) > 0 ? count($passkeys) . ' registered' : 'Not enabled' ?></span>
    </div>
    <p>A passkey uses public-key cryptography. Your device may verify you with fingerprint, face unlock, or PIN; KageGate never receives biometric data.</p>
    <p>Passkeys require HTTPS on public sites. Localhost works for development.</p>
    <div id="passkey-status" role="status" aria-live="polite"></div>
    <h2>Your passkeys</h2>
    <?php if (!$passkeys): ?>
        <p>No passkeys registered yet.</p>
    <?php else: ?>
        <ul class="episode-list">
            <?php foreach ($passkeys as $passkey): ?>
                <li>
                    <strong><?= h($passkey['label']) ?></strong>
                    <small>Added <?= h($passkey['created_at']) ?></small>
                    <button type="button" class="passkey-delete" data-id="<?= (int)$passkey['id'] ?>" data-csrf="<?= h(token()) ?>">Remove</button>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
    <label>Current password
        <input id="passkey-password" type="password" autocomplete="current-password" required>
    </label>
    <label>Passkey name
        <input id="passkey-label" maxlength="80" value="This device" autocomplete="off">
    </label>
    <button id="passkey-register" class="btn" type="button" data-csrf="<?= h(token()) ?>">Add passkey</button>
    <a class="settings-back" href="security.php">← Back to password and authentication</a>
    </section>
</section>
<?php endpage(); ?>
