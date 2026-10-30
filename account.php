<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/catalog.php';
$user = require_user();
$catalogData = catalog_data();
$catalog = [];
foreach ($catalogData['items'] as $entry) $catalog[(int)$entry['id']] = $entry;
$query = db()->prepare('SELECT title_id FROM watchlist WHERE user_id = ? ORDER BY id DESC');
$query->execute([(int)$user['id']]);
$saved = $query->fetchAll();
$passkeyQuery = db()->prepare('SELECT COUNT(*) FROM passkeys WHERE user_id = ?');
$passkeyQuery->execute([(int)$user['id']]);
$passkeyCount = (int)$passkeyQuery->fetchColumn();
$needsPasswordSetup = google_password_setup_required((int)$user['id']);
$initial = mb_strtoupper(mb_substr((string)$user['username'], 0, 1));
$mfaEnabled = (int)$user['mfa_enabled'] === 1;
page('My account');
?>
<section class="account account-dashboard">
    <div class="profile-card">
        <div class="profile-avatar" aria-hidden="true"><?= h($initial) ?></div>
        <div class="profile-copy">
            <span class="kicker">YOUR KAGEGATE PROFILE</span>
            <h1><?= h($user['username']) ?></h1>
            <p><?= h($user['email']) ?></p>
            <span class="profile-member">Member since <?= h(date('F Y', strtotime((string)$user['created_at']))) ?></span>
        </div>
        <div class="profile-stats" aria-label="Account overview">
            <div><strong><?= count($saved) ?></strong><span>Saved titles</span></div>
            <div><strong><?= $passkeyCount ?></strong><span>Passkeys</span></div>
        </div>
    </div>

    <nav class="settings-nav" aria-label="Account settings">
        <a class="active" href="account.php"><span aria-hidden="true">◉</span> Profile</a>
        <a href="security.php"><span aria-hidden="true">◇</span> Password and authentication</a>
    </nav>

    <div class="account-grid">
        <aside class="account-panel">
            <div class="panel-heading">
                <span class="kicker">ACCOUNT PROTECTION</span>
                <h2>Security</h2>
            </div>
            <div class="security-item">
                <span class="security-icon" aria-hidden="true">01</span>
                <div>
                    <h3>Authenticator MFA</h3>
                    <p>
                        <span class="security-state <?= $mfaEnabled ? 'is-enabled' : 'is-disabled' ?>">
                            <span class="state-dot" aria-hidden="true"></span><?= $mfaEnabled ? 'Enabled' : 'Not enabled' ?>
                        </span>
                        <?= $mfaEnabled ? 'Code required at sign in' : 'Add a time-based second factor' ?>
                    </p>
                </div>
                <a class="text-link" href="mfa.php"><?= $mfaEnabled ? 'Manage' : 'Set up' ?> →</a>
            </div>
            <div class="security-item">
                <span class="security-icon" aria-hidden="true">02</span>
                <div>
                    <h3>Passkeys</h3>
                    <p>
                        <span class="security-state <?= $passkeyCount > 0 ? 'is-enabled' : 'is-disabled' ?>">
                            <span class="state-dot" aria-hidden="true"></span><?= $passkeyCount > 0 ? 'Enabled · ' . $passkeyCount . ($passkeyCount === 1 ? ' passkey' : ' passkeys') : 'Not enabled' ?>
                        </span>
                        <?= $passkeyCount > 0 ? 'Device sign-in is ready' : 'Fingerprint, face unlock, or PIN' ?>
                    </p>
                </div>
                <a class="text-link" href="passkeys.php">Manage →</a>
            </div>
            <div class="security-item">
                <span class="security-icon" aria-hidden="true">03</span>
                <div>
                    <h3>Password</h3>
                    <p><span class="security-state <?= $needsPasswordSetup ? 'is-disabled' : 'is-enabled' ?>"><span class="state-dot" aria-hidden="true"></span><?= $needsPasswordSetup ? 'Setup required' : 'Configured' ?></span></p>
                </div>
                <a class="text-link" href="<?= $needsPasswordSetup ? 'set-password.php' : 'password.php' ?>"><?= $needsPasswordSetup ? 'Set up' : 'Change' ?> →</a>
            </div>
            <a class="panel-action" href="index.php">Discover something new <span aria-hidden="true">↗</span></a>
        </aside>

        <div class="watchlist-panel">
            <div class="section-title">
                <div><span class="kicker">YOUR PERSONAL COLLECTION</span><h2>Watchlist</h2></div>
                <span class="watchlist-count"><?= count($saved) ?> saved</span>
            </div>
            <div class="cards">
                <?php $count = 0; foreach ($saved as $entry): ?>
                    <?php $id = (int)$entry['title_id']; if (!isset($catalog[$id])) continue; $item = $catalog[$id]; $count++; ?>
                    <article class="poster">
                        <a href="detail.php?id=<?= $id ?>">
                            <?php if ($item['image'] !== ''): ?>
                                <img class="poster-image" src="<?= h($item['image']) ?>" alt="<?= h($item['title']) ?> cover" loading="lazy">
                            <?php else: ?>
                                <div class="poster-art"><?= h($item['title']) ?></div>
                            <?php endif; ?>
                            <div class="poster-info"><small><?= h($item['category']) ?></small><h3><?= h($item['title']) ?></h3></div>
                        </a>
                        <form method="post" action="watchlist.php">
                            <input type="hidden" name="csrf" value="<?= h(token()) ?>">
                            <input type="hidden" name="id" value="<?= $id ?>">
                            <button type="submit">Remove from watchlist</button>
                        </form>
                    </article>
                <?php endforeach; ?>
                <?php if ($count === 0): ?>
                    <div class="empty-watchlist">
                        <span aria-hidden="true">✦</span>
                        <h3>Your collection starts here</h3>
                        <p>Save anime, donghua, manga, and more to keep your next pick close.</p>
                        <a class="btn" href="index.php">Explore the library →</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>
<?php endpage(); ?>
