<?php
declare(strict_types=1);

// Configure these four values for your local XAMPP or InfinityFree database.
const DB_HOST = 'sql106.infinityfree.com';
const DB_NAME = 'if0_43282147_kagegate';
const DB_USER = 'if0_43282147';
const DB_PASS = 'paste the database password here locally';
const PASSKEY_RP_ID = 'kagegate.infinityfree.io';
const APP_NAME = 'KageGate';


function app_setting(string $name, string $default = ''): string {
    $value = getenv($name);
    return is_string($value) && $value !== '' ? $value : $default;
}

// Session cookies work on local HTTP and on HTTPS hosting.
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
session_name('kagegate_v3');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => $isHttps,
    'httponly' => true,
    'samesite' => 'Lax',
]);
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header('Cache-Control: private, no-store, max-age=0');
header("Content-Security-Policy: default-src 'self'; style-src 'self'; script-src 'self'; img-src 'self' data: https://s1.anilist.co https://s2.anilist.co https://s3.anilist.co https://s4.anilist.co; frame-src https://www.youtube-nocookie.com; form-action 'self'; base-uri 'self'; frame-ancestors 'none'");

function db(): PDO {
    static $connection = null;
    if ($connection instanceof PDO) return $connection;
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    $connection = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $connection;
}
function h($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrf(): void {
    $expected = (string)($_SESSION['csrf'] ?? '');
    $provided = (string)($_POST['csrf'] ?? '');
    if ($expected === '' || $provided === '' || !hash_equals($expected, $provided)) {
        http_response_code(400);
        exit('Invalid request token. Refresh the form and retry.');
    }
}
function go(string $path): never {
    header('Location: ' . $path);
    exit;
}
function me(): ?array {
    if (empty($_SESSION['uid'])) return null;
    $statement = db()->prepare('SELECT id, username, email, mfa_enabled, created_at FROM users WHERE id = ?');
    $statement->execute([(int)$_SESSION['uid']]);
    $user = $statement->fetch();
    if (!$user) {
        unset($_SESSION['uid']);
        return null;
    }
    return $user;
}
function require_user(): array {
    $user = me();
    if (!$user) go('login.php');
    return $user;
}
function google_sign_in_configured(): bool {
    return app_setting('GOOGLE_CLIENT_ID') !== ''
        && app_setting('GOOGLE_CLIENT_SECRET') !== ''
        && app_setting('GOOGLE_REDIRECT_URI') !== '';
}
function google_login_available(): bool {
    return google_sign_in_configured();
}
function google_password_setup_required(int $userId): bool {
    $statement = db()->prepare('SELECT password_set FROM google_accounts WHERE user_id = ?');
    $statement->execute([$userId]);
    $passwordSet = $statement->fetchColumn();
    return $passwordSet !== false && (int)$passwordSet === 0;
}
function begin_login(int $userId): string {
    $statement = db()->prepare('SELECT id, email, mfa_enabled, created_at FROM users WHERE id = ?');
    $statement->execute([$userId]);
    $user = $statement->fetch();
    if (!$user) {
        throw new RuntimeException('The account could not be found.');
    }
    session_regenerate_id(true);
    unset($_SESSION['uid'], $_SESSION['pending'], $_SESSION['pending_time'], $_SESSION['pending_stage']);
    if ((int)$user['mfa_enabled'] === 1) {
        $_SESSION['pending'] = $userId;
        $_SESSION['pending_time'] = time();
        $_SESSION['pending_stage'] = 'mfa';
        return 'verify.php';
    }
    finish_login($userId);
    return 'index.php';
}
function finish_login(int $userId): void {
    $statement = db()->prepare(
        'INSERT INTO login_activity (user_id, last_login_at) VALUES (?, CURRENT_TIMESTAMP)
         ON DUPLICATE KEY UPDATE last_login_at = CURRENT_TIMESTAMP'
    );
    $statement->execute([$userId]);
    session_regenerate_id(true);
    $_SESSION['uid'] = $userId;
    unset($_SESSION['pending'], $_SESSION['pending_time'], $_SESSION['pending_stage']);
    create_notification($userId, 'New sign-in', 'Your KageGate account was signed in successfully.');
}
function create_notification(int $userId, string $title, string $message): void {
    $statement = db()->prepare('INSERT INTO notifications (user_id, title, message) VALUES (?, ?, ?)');
    $statement->execute([$userId, $title, $message]);
}
function unread_notification_count(int $userId): int {
    $statement = db()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND read_at IS NULL');
    $statement->execute([$userId]);
    return (int)$statement->fetchColumn();
}
function limit(string $key, int $max = 6, int $period = 300): bool {
    $now = time();
    $entry = $_SESSION['rate'][$key] ?? ['count' => 0, 'since' => $now];
    if ($now - (int)$entry['since'] >= $period) {
        $entry = ['count' => 0, 'since' => $now];
    }
    $entry['count']++;
    $_SESSION['rate'][$key] = $entry;
    return $entry['count'] <= $max;
}
function page(string $title): void {
    $user = me();
    $script = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
    ?>
    <!doctype html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#100e20">
        <title><?= h($title) ?> · KageGate</title>
        <link rel="stylesheet" href="assets/app.css">
        <link rel="stylesheet" href="assets/auth.css">
        <link rel="stylesheet" href="assets/site-refresh.css">
        <?php if ($script === 'index.php'): ?><link rel="stylesheet" href="assets/discover.css"><?php endif; ?>
        <?php if ($script === 'detail.php'): ?><link rel="stylesheet" href="assets/detail.css"><?php endif; ?>
        <?php if (in_array($script, ['account.php', 'security.php', 'password.php', 'mfa.php', 'passkeys.php'], true)): ?><link rel="stylesheet" href="assets/account-settings.css"><?php endif; ?>
        <script src="assets/app.js" defer></script>
        <?php if ($script === 'mfa.php'): ?>
            <script src="assets/qrcode.min.js" defer></script>
            <script src="assets/mfa.js" defer></script>
        <?php endif; ?>
    </head>
    <body>
    <header class="site-header">
        <a class="logo" href="index.php">影 KAGEGATE</a>
        <nav aria-label="Main navigation" class="site-nav">
            <?php if ($user): ?>
                <a href="index.php">Discover</a>
                <a href="about.php#contact">Contact</a>
                <a href="about.php">About</a>
                <a class="notification-link" href="notifications.php">Notifications<?php $unreadCount = unread_notification_count((int)$user['id']); ?><?php if ($unreadCount > 0): ?><span class="notification-count"><?= $unreadCount > 99 ? '99+' : $unreadCount ?></span><?php endif; ?></a>
                <a href="account.php">Account</a>
                <a href="vip.php">VIP</a>
                <form action="logout.php" method="post">
                    <input type="hidden" name="csrf" value="<?= h(token()) ?>">
                    <button type="submit">Log out</button>
                </form>
            <?php elseif (in_array($script, ['login.php', 'register.php'], true)): ?>
                <a href="about.php">About</a>
                <a href="references.php">Reference</a>
                <a href="login.php">Login</a>
                <a class="nav-cta" href="register.php">Join</a>
            <?php else: ?>
                <a href="about.php">About</a>
                <a href="references.php">Reference</a>
                <a href="login.php">Login</a>
                <a class="nav-cta" href="register.php">Join</a>
            <?php endif; ?>
        </nav>
    </header>
    <main class="site-main">
    <?php
}
function endpage(): void {
    ?>
    </main>
    <footer>KageGate · Anime, Donghua &amp; Comics Discovery · Secure MFA Project · Metadata by AniList · Videos embedded from YouTube</footer>
    </body>
    </html>
    <?php
}
