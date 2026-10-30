<?php
require_once __DIR__ . '/config.php';
if (me()) go('index.php');
if (!google_login_available()) {
    http_response_code(503);
    page('Google sign-in unavailable');
    ?>
    <section class="auth">
        <h1>Google sign-in is not configured.</h1>
        <p>Set the Google OAuth client ID, client secret, and exact callback URL in the server environment, then try again.</p>
        <a class="btn" href="login.php">Return to sign in</a>
    </section>
    <?php
    endpage();
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    exit('GET is required.');
}
$state = bin2hex(random_bytes(32));
$_SESSION['google_oauth_state'] = $state;
$parameters = [
    'client_id' => app_setting('GOOGLE_CLIENT_ID'),
    'redirect_uri' => app_setting('GOOGLE_REDIRECT_URI'),
    'response_type' => 'code',
    'scope' => 'openid email profile',
    'state' => $state,
    'prompt' => 'select_account',
];
go('https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($parameters));
