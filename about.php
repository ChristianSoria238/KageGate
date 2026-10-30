<?php
require_once __DIR__ . '/config.php';
page('About KageGate');
?>
<section class="content-page about-page">
    <span class="kicker">ABOUT THE PROJECT</span>
    <h1>KageGate</h1>
    <p class="page-lead">A secure discovery hub for anime, donghua, manga, manhwa, and novels.</p>
    <div class="content-panel">
        <h2>Project author</h2>
        <p><strong>Christian Soria</strong><br>BSCS 4A · Student project</p>
    </div>
    <div class="content-panel">
        <h2>What is KageGate?</h2>
        <p>KageGate helps members discover stories, explore metadata and official viewing links, and keep a personal watchlist. It is also a Security in Distributed Computing project demonstrating account protection with password authentication, authenticator-based MFA, Google sign-in, and device passkeys.</p>
        <p>Catalog artwork and metadata come from AniList. KageGate does not host or proxy video; episode links open the source provider in a new tab.</p>
    </div>
    <div class="content-panel" id="contact">
        <h2>Contact</h2>
        <p>This student-project demo does not publish a contact number.</p>
    </div>
    <a class="btn" href="index.php">Discover the catalog →</a>
</section>
<?php endpage(); ?>
