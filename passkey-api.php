<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function passkey_reply(array $body, int $status = 200): never {
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    exit;
}

try {
    $autoload = __DIR__ . '/vendor/autoload.php';
    if (!is_file($autoload)) {
        throw new RuntimeException('Composer autoloader is missing.');
    }
    require_once $autoload;
} catch (Throwable $exception) {
    error_log('KageGate passkey dependencies could not load: ' . $exception->getMessage());
    passkey_reply(['error' => 'Passkey dependencies are missing or incompatible. Upload the complete vendor directory and check the hosting PHP error log.'], 503);
}

function passkey_decode(string $value): string {
    if ($value === '' || !preg_match('/^[A-Za-z0-9_-]+$/D', $value)) {
        throw new InvalidArgumentException('Invalid encoded credential data.');
    }
    $decoded = base64_decode(strtr($value, '-_', '+/') . str_repeat('=', (4 - strlen($value) % 4) % 4), true);
    if (!is_string($decoded)) throw new InvalidArgumentException('Invalid encoded credential data.');
    return $decoded;
}

function passkey_encode(string $value): string {
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

function passkey_user_handle(int $userId): string {
    return pack('N2', intdiv($userId, 4294967296), $userId % 4294967296);
}

function passkey_relying_party(): \lbuchs\WebAuthn\WebAuthn {
    $host = strtolower(PASSKEY_RP_ID !== '' ? PASSKEY_RP_ID : (string)($_SERVER['SERVER_NAME'] ?? 'localhost'));
    if (!preg_match('/^(localhost|[a-z0-9](?:[a-z0-9.-]{0,251}[a-z0-9])?)$/D', $host)
        || str_contains($host, '..')) {
        throw new RuntimeException('The WebAuthn relying-party hostname is invalid.');
    }
    $applicationName = defined('APP_NAME') ? APP_NAME : 'KageGate';
    return new \lbuchs\WebAuthn\WebAuthn($applicationName, $host, ['none'], true);
}

function passkey_require_challenge(string $operation): string {
    if (($_SESSION['passkey_operation'] ?? '') !== $operation
        || !isset($_SESSION['passkey_challenge'], $_SESSION['passkey_started'])
        || time() - (int)$_SESSION['passkey_started'] > 240
        || !is_string($_SESSION['passkey_challenge'])) {
        unset($_SESSION['passkey_operation'], $_SESSION['passkey_challenge'], $_SESSION['passkey_started'], $_SESSION['passkey_user_id']);
        passkey_reply(['error' => 'Passkey request expired. Start again.'], 400);
    }
    return $_SESSION['passkey_challenge'];
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') passkey_reply(['error' => 'POST is required.'], 405);
$csrfExpected = (string)($_SESSION['csrf'] ?? '');
$csrfProvided = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if ($csrfExpected === '' || $csrfProvided === '' || !hash_equals($csrfExpected, $csrfProvided)) {
    passkey_reply(['error' => 'Invalid request token. Refresh the page and retry.'], 400);
}

$raw = file_get_contents('php://input');
if (!is_string($raw) || strlen($raw) > 65536) passkey_reply(['error' => 'Invalid request body.'], 400);
try {
    $payload = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
} catch (JsonException) {
    passkey_reply(['error' => 'Invalid request body.'], 400);
}
$action = (string)($payload['action'] ?? '');

try {
    $webAuthn = passkey_relying_party();

    if ($action === 'register-options') {
        $user = me();
        if (!$user) passkey_reply(['error' => 'Sign in before registering a passkey.'], 401);
        if (google_password_setup_required((int)$user['id'])) {
            passkey_reply(['error' => 'Set an account password before registering a passkey.'], 403);
        }
        if (!limit('passkey_register', 5, 300)) passkey_reply(['error' => 'Too many passkey attempts. Try again later.'], 429);
        $passwordQuery = db()->prepare('SELECT password_hash FROM users WHERE id = ?');
        $passwordQuery->execute([(int)$user['id']]);
        $passwordHash = $passwordQuery->fetchColumn();
        if (!is_string($passwordHash) || !password_verify((string)($payload['password'] ?? ''), $passwordHash)) {
            passkey_reply(['error' => 'Enter your current password to add a passkey.'], 403);
        }
        $existing = db()->prepare('SELECT credential_id FROM passkeys WHERE user_id = ?');
        $existing->execute([(int)$user['id']]);
        $excludeIds = [];
        foreach ($existing->fetchAll(PDO::FETCH_COLUMN) as $credentialId) {
            $excludeIds[] = passkey_decode((string)$credentialId);
        }
        $args = $webAuthn->getCreateArgs(
            passkey_user_handle((int)$user['id']),
            (string)$user['email'],
            (string)$user['username'],
            240,
            true,
            true,
            null,
            $excludeIds
        );
        $_SESSION['passkey_operation'] = 'register';
        $_SESSION['passkey_challenge'] = $webAuthn->getChallenge()->getBinaryString();
        $_SESSION['passkey_started'] = time();
        $_SESSION['passkey_user_id'] = (int)$user['id'];
        passkey_reply(['publicKey' => $args->publicKey]);
    }

    if ($action === 'register') {
        $challenge = passkey_require_challenge('register');
        $user = me();
        if (!$user || (int)($_SESSION['passkey_user_id'] ?? 0) !== (int)$user['id']) {
            passkey_reply(['error' => 'Your sign-in expired. Sign in again and retry.'], 401);
        }
        $credential = $webAuthn->processCreate(
            passkey_decode((string)($payload['clientDataJSON'] ?? '')),
            passkey_decode((string)($payload['attestationObject'] ?? '')),
            $challenge,
            true,
            true,
            false
        );
        $credentialId = passkey_encode((string)$credential->credentialId);
        $label = trim((string)($payload['label'] ?? ''));
        if ($label === '') $label = 'Passkey';
        $label = mb_substr($label, 0, 80);
        $insert = db()->prepare(
            'INSERT INTO passkeys (user_id, credential_id, public_key, sign_count, label) VALUES (?, ?, ?, ?, ?)'
        );
        $insert->execute([
            (int)$user['id'],
            $credentialId,
            (string)$credential->credentialPublicKey,
            max(0, (int)($credential->signatureCounter ?? 0)),
            $label,
        ]);
        create_notification((int)$user['id'], 'Passkey added', 'A new passkey was added to your account.');
        unset($_SESSION['passkey_operation'], $_SESSION['passkey_challenge'], $_SESSION['passkey_started'], $_SESSION['passkey_user_id']);
        passkey_reply(['message' => 'Passkey added. Your device may ask for a fingerprint, face unlock, or PIN.']);
    }

    if ($action === 'login-options') {
        if (!limit('passkey_login', 8, 300)) passkey_reply(['error' => 'Too many sign-in attempts. Try again later.'], 429);
        $identifier = trim((string)($payload['login'] ?? ''));
        if ($identifier === '' || strlen($identifier) > 190) {
            passkey_reply(['error' => 'Enter your username or email before using a passkey.'], 400);
        }
        $userQuery = db()->prepare('SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1');
        $userQuery->execute([$identifier, $identifier]);
        $userId = (int)$userQuery->fetchColumn();
        if ($userId === 0) passkey_reply(['error' => 'Passkey sign-in failed. Check the username or email.'], 401);
        $credentialQuery = db()->prepare('SELECT credential_id FROM passkeys WHERE user_id = ?');
        $credentialQuery->execute([$userId]);
        $credentialIds = array_map(
            static fn(string $credentialId): string => passkey_decode($credentialId),
            $credentialQuery->fetchAll(PDO::FETCH_COLUMN)
        );
        if (!$credentialIds) passkey_reply(['error' => 'Passkey sign-in failed. Check the username or email.'], 401);
        $args = $webAuthn->getGetArgs($credentialIds, 240, true, true, true, true, true, true);
        $_SESSION['passkey_operation'] = 'login';
        $_SESSION['passkey_challenge'] = $webAuthn->getChallenge()->getBinaryString();
        $_SESSION['passkey_started'] = time();
        $_SESSION['passkey_user_id'] = $userId;
        passkey_reply(['publicKey' => $args->publicKey]);
    }

    if ($action === 'login') {
        $challenge = passkey_require_challenge('login');
        $expectedUserId = (int)($_SESSION['passkey_user_id'] ?? 0);
        if ($expectedUserId === 0) passkey_reply(['error' => 'Passkey sign-in failed. Try again.'], 401);
        $credentialId = passkey_encode(passkey_decode((string)($payload['id'] ?? '')));
        $credentialQuery = db()->prepare('SELECT id, user_id, public_key, sign_count FROM passkeys WHERE credential_id = ? AND user_id = ?');
        $credentialQuery->execute([$credentialId, $expectedUserId]);
        $credential = $credentialQuery->fetch();
        if (!$credential) passkey_reply(['error' => 'Passkey sign-in failed. Use your password instead.'], 401);
        $encodedUserHandle = (string)($payload['userHandle'] ?? '');
        if ($encodedUserHandle !== ''
            && !hash_equals(passkey_user_handle((int)$credential['user_id']), passkey_decode($encodedUserHandle))) {
            passkey_reply(['error' => 'Passkey sign-in failed. Use your password instead.'], 401);
        }
        $webAuthn->processGet(
            passkey_decode((string)($payload['clientDataJSON'] ?? '')),
            passkey_decode((string)($payload['authenticatorData'] ?? '')),
            passkey_decode((string)($payload['signature'] ?? '')),
            (string)$credential['public_key'],
            $challenge,
            (int)$credential['sign_count'],
            true,
            true
        );
        $counter = $webAuthn->getSignatureCounter();
        if ($counter !== null) {
            $updateCounter = db()->prepare('UPDATE passkeys SET sign_count = ? WHERE id = ?');
            $updateCounter->execute([$counter, (int)$credential['id']]);
        }
        unset($_SESSION['passkey_operation'], $_SESSION['passkey_challenge'], $_SESSION['passkey_started'], $_SESSION['rate']['passkey_login']);
        $redirect = begin_login((int)$credential['user_id']);
        passkey_reply(['redirect' => $redirect]);
    }

    if ($action === 'delete') {
        $user = me();
        if (!$user) passkey_reply(['error' => 'Sign in and retry.'], 401);
        $passwordQuery = db()->prepare('SELECT password_hash FROM users WHERE id = ?');
        $passwordQuery->execute([(int)$user['id']]);
        $passwordHash = $passwordQuery->fetchColumn();
        if (!is_string($passwordHash) || !password_verify((string)($payload['password'] ?? ''), $passwordHash)) {
            passkey_reply(['error' => 'Password is incorrect.'], 403);
        }
        $delete = db()->prepare('DELETE FROM passkeys WHERE id = ? AND user_id = ?');
        $delete->execute([(int)($payload['id'] ?? 0), (int)$user['id']]);
        if ($delete->rowCount() !== 1) passkey_reply(['error' => 'Passkey not found. Refresh and try again.'], 404);
        create_notification((int)$user['id'], 'Passkey removed', 'A passkey was removed from your account.');
        passkey_reply(['message' => 'Passkey removed.']);
    }

    passkey_reply(['error' => 'Unknown passkey action.'], 400);
} catch (InvalidArgumentException | \lbuchs\WebAuthn\WebAuthnException $exception) {
    error_log('KageGate passkey verification failed: ' . $exception->getMessage());
    passkey_reply(['error' => 'Passkey verification failed. Check your device and try again.'], 400);
} catch (PDOException $exception) {
    error_log('KageGate passkey database error: ' . $exception->getMessage());
    passkey_reply(['error' => 'Passkey storage is unavailable. Check database.sql.'], 500);
} catch (RuntimeException $exception) {
    error_log('KageGate passkey configuration error: ' . $exception->getMessage());
    passkey_reply(['error' => 'Passkeys are not configured for this hostname.'], 500);
} catch (Throwable $exception) {
    error_log('KageGate unexpected passkey error (' . get_class($exception) . '): ' . $exception->getMessage());
    $diagnosticMessage = preg_replace('/[\r\n\t]+/', ' ', $exception->getMessage()) ?? '';
    $diagnosticMessage = preg_replace('~(?:[A-Za-z]:)?[/\\\\][^ ]+~', '[server path]', $diagnosticMessage) ?? '';
    passkey_reply([
        'error' => 'The passkey service encountered a server error.',
        'diagnostic' => get_class($exception) . ' at passkey-api.php:' . $exception->getLine()
            . ($diagnosticMessage !== '' ? ': ' . substr($diagnosticMessage, 0, 180) : ''),
    ], 500);
}
