<?php
require_once __DIR__ . '/config.php';

function google_callback_failure(string $message, int $status = 400): never {
    http_response_code($status);
    page('Google sign-in');
    ?>
    <section class="auth">
        <h1>Google sign-in could not be completed.</h1>
        <p class="error" role="alert"><?= h($message) ?></p>
        <a class="btn" href="login.php">Return to sign in</a>
    </section>
    <?php
    endpage();
    exit;
}

function google_http(string $url, array $options): array {
    if (!function_exists('curl_init')) {
        throw new RuntimeException('PHP cURL is required for Google sign-in.');
    }
    $curl = curl_init($url);
    if ($curl === false) {
        throw new RuntimeException('Could not initialize Google OAuth request.');
    }
    curl_setopt_array($curl, $options + [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    $body = curl_exec($curl);
    $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $error = curl_error($curl);
    curl_close($curl);
    if (!is_string($body) || $status < 200 || $status >= 300) {
        throw new RuntimeException('Google OAuth request failed: ' . ($error !== '' ? $error : 'HTTP ' . $status));
    }
    try {
        $data = json_decode($body, true, 16, JSON_THROW_ON_ERROR);
    } catch (JsonException $exception) {
        throw new RuntimeException('Google returned an invalid response.', 0, $exception);
    }
    if (!is_array($data)) {
        throw new RuntimeException('Google returned an invalid response.');
    }
    return $data;
}

function google_username(PDO $connection, string $email): string {
    $localPart = strstr($email, '@', true);
    $base = preg_replace('/[^A-Za-z0-9_.-]/', '', (string)$localPart);
    $base = trim(substr((string)$base, 0, 20), '._-');
    if (strlen($base) < 3) {
        $base = 'member';
    }
    $check = $connection->prepare('SELECT 1 FROM users WHERE username = ?');
    for ($attempt = 0; $attempt < 8; $attempt++) {
        $username = $base . '_' . bin2hex(random_bytes(3));
        $check->execute([$username]);
        if (!$check->fetchColumn()) {
            return $username;
        }
    }
    throw new RuntimeException('Could not allocate a unique username.');
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    exit('GET is required.');
}
$expectedState = (string)($_SESSION['google_oauth_state'] ?? '');
$providedState = (string)($_GET['state'] ?? '');
unset($_SESSION['google_oauth_state']);
if ($expectedState === '' || $providedState === '' || !hash_equals($expectedState, $providedState)) {
    google_callback_failure('The sign-in request expired or could not be verified. Start again.');
}
if (isset($_GET['error']) || !isset($_GET['code']) || !google_login_available()) {
    google_callback_failure('Google authorization was cancelled or is not configured.');
}

try {
    $token = google_http('https://oauth2.googleapis.com/token', [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'code' => (string)$_GET['code'],
            'client_id' => app_setting('GOOGLE_CLIENT_ID'),
            'client_secret' => app_setting('GOOGLE_CLIENT_SECRET'),
            'redirect_uri' => app_setting('GOOGLE_REDIRECT_URI'),
            'grant_type' => 'authorization_code',
        ]),
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
    ]);
    if (!isset($token['access_token']) || !is_string($token['access_token'])) {
        throw new RuntimeException('Google did not return an access token.');
    }
    $identity = google_http('https://openidconnect.googleapis.com/v1/userinfo', [
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token['access_token']],
    ]);
    $googleSubject = (string)($identity['sub'] ?? '');
    $email = strtolower(trim((string)($identity['email'] ?? '')));
    if ($googleSubject === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || ($identity['email_verified'] ?? false) !== true) {
        throw new RuntimeException('Google did not provide a verified email address.');
    }

    $connection = db();
    $connection->beginTransaction();
    $mapped = $connection->prepare(
        'SELECT u.id, u.email FROM google_accounts g JOIN users u ON u.id = g.user_id WHERE g.google_sub = ? FOR UPDATE'
    );
    $mapped->execute([$googleSubject]);
    $user = $mapped->fetch();
    $createdUser = false;
    if ($user) {
        $userId = (int)$user['id'];
        if (strcasecmp((string)$user['email'], $email) !== 0) {
            $updateEmail = $connection->prepare('UPDATE users SET email = ? WHERE id = ?');
            $updateEmail->execute([$email, $userId]);
        }
    } else {
        $existing = $connection->prepare('SELECT id FROM users WHERE email = ? FOR UPDATE');
        $existing->execute([$email]);
        $userId = (int)$existing->fetchColumn();
        if ($userId === 0) {
            $username = google_username($connection, $email);
            $insert = $connection->prepare('INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)');
            $insert->execute([$username, $email, password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT)]);
            $userId = (int)$connection->lastInsertId();
            $createdUser = true;
        }
        $link = $connection->prepare('INSERT INTO google_accounts (google_sub, user_id, password_set) VALUES (?, ?, ?)');
        $link->execute([$googleSubject, $userId, $createdUser ? 0 : 1]);
        if ($createdUser) {
            create_notification($userId, 'Welcome to KageGate!', 'Welcome! Explore the catalog and start building your watchlist.');
        }
    }
    $connection->commit();
    $_SESSION['google_authenticated_at'] = time();
    go(begin_login($userId));
} catch (Throwable $exception) {
    if (isset($connection) && $connection instanceof PDO && $connection->inTransaction()) {
        $connection->rollBack();
    }
    error_log('KageGate Google sign-in failed: ' . $exception->getMessage());
    google_callback_failure('Could not verify your Google account. Check the OAuth configuration and try again.', 503);
}
