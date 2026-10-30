<?php
require_once __DIR__ . '/config.php';
require_user();
page('VIP membership');
?>
<section class="content-page vip-page">
    <span class="kicker">KAGEGATE VIP</span>
    <h1>A premium experience is planned.</h1>
    <p class="page-lead">VIP membership is not available yet. No payments or subscriptions are being accepted.</p>
    <div class="content-panel">
        <h2>Coming soon</h2>
        <p>Membership benefits, pricing, and a payment provider will be announced here after they are configured. Your current account and watchlist remain free.</p>
    </div>
    <a class="btn" href="index.php">Back to Discover →</a>
</section>
<?php endpage(); ?>
