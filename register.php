<?php
require_once __DIR__ . '/config.php';
if (me()) go('index.php');
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf();
    if (!limit('register', 5, 600)) {
        $error = 'Too many attempts. Try again later.';
    } else {
        $username = trim((string)($_POST['username'] ?? ''));
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        $password = (string)($_POST['password'] ?? '');
        $confirm = (string)($_POST['confirm_password'] ?? '');
        if (!preg_match('/^[A-Za-z0-9_.-]{3,30}$/', $username)) {
            $error = 'Username must be 3–30 letters, numbers, dots, dashes or underscores.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
            $error = 'Enter a valid email address.';
        } elseif (strlen($password) < 12 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
            $error = 'Password must be at least 12 characters with letters and numbers.';
        } elseif ($password !== $confirm) {
            $error = 'Passwords do not match.';
        } else {
            try {
                $connection = db();
                $connection->beginTransaction();
                $query = $connection->prepare('INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)');
                $query->execute([$username, $email, password_hash($password, PASSWORD_DEFAULT)]);
                $userId = (int)$connection->lastInsertId();
                create_notification($userId, 'Welcome to KageGate!', 'Welcome, ' . $username . '! Explore the catalog and start building your watchlist.');
                $connection->commit();
                finish_login($userId);
                go('index.php');
            } catch (PDOException $exception) {
                if (isset($connection) && $connection instanceof PDO && $connection->inTransaction()) {
                    $connection->rollBack();
                }
                error_log($exception->getMessage());
                $error = $exception->getCode() === '23000'
                    ? 'Username or email already exists.'
                    : 'Database unavailable. Check your configuration.';
            }
        }
    }
}
page('Create account');
?>
<section class="auth auth-layout register-layout">
    <div class="auth-story">
        <span class="kicker">JOIN THE ANIMEVERSE</span>
        <h1>Make this world yours.</h1>
        <p>Create your profile, save the stories you love, and pick up right where you left off.</p>
        <div class="auth-perks">
            <span><i aria-hidden="true">✦</i> Build your personal watchlist</span>
            <span><i aria-hidden="true">◇</i> Add MFA and device passkeys</span>
            <span><i aria-hidden="true">↗</i> One account for every discovery</span>
        </div>
    </div>
    <div class="auth-card">
        <span class="kicker">START YOUR JOURNEY</span>
        <h2>Create your account</h2>
        <p class="auth-subtitle">It only takes a moment to get started.</p>
        <?php if ($error): ?><p class="error" role="alert"><?= h($error) ?></p><?php endif; ?>
        <form method="post">
            <input type="hidden" name="csrf" value="<?= h(token()) ?>">
            <label>Username<input name="username" placeholder="Choose a username" required minlength="3" maxlength="30" autocomplete="username" value="<?= h($_POST['username'] ?? '') ?>"></label>
            <label>Email<input type="email" name="email" placeholder="you@example.com" required maxlength="190" autocomplete="email" value="<?= h($_POST['email'] ?? '') ?>"></label>
            <label>Password <span class="label-hint">12+ characters, letters and numbers</span><input type="password" name="password" placeholder="Create a strong password" minlength="12" required autocomplete="new-password"></label>
            <label>Confirm password<input type="password" name="confirm_password" placeholder="Enter your password again" minlength="12" required autocomplete="new-password"></label>
            <button class="btn auth-submit" type="submit">Create account <span aria-hidden="true">→</span></button>
        </form>
        <p class="auth-switch">Already a member? <a href="login.php">Sign in <span aria-hidden="true">→</span></a></p>
    </div>
</section>
<?php endpage(); ?>
