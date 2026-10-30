<?php
require_once __DIR__ . '/config.php';
page('Project references');
?>
<section class="content-page">
    <span class="kicker">SOURCES &amp; REFERENCES</span>
    <h1>Project references</h1>
    <p class="page-lead">The metadata, security standards, and design constraints used by KageGate.</p>
    <div class="content-panel">
        <h2>Catalog and title discovery</h2>
        <ul class="reference-list">
            <li><a href="https://anilist.co/" target="_blank" rel="noopener noreferrer">AniList</a><span>Catalog metadata, alternate titles, tags, artwork, trailers, and listed streaming links.</span></li>
            <li><a href="https://luciferdonghua.in/" target="_blank" rel="noopener noreferrer">LuciferDonghua</a><span>Donghua title-discovery reference only; KageGate does not embed or proxy video from it.</span></li>
        </ul>
        <p>Provider links are displayed as episode links without provider branding. Availability and playback are controlled by each source.</p>
    </div>
    <div class="content-panel">
        <h2>Authentication and security</h2>
        <ul class="reference-list">
            <li><a href="https://datatracker.ietf.org/doc/html/rfc6238" target="_blank" rel="noopener noreferrer">RFC 6238</a><span>Time-based one-time password algorithm used for authenticator MFA.</span></li>
            <li><a href="https://www.w3.org/TR/webauthn-2/" target="_blank" rel="noopener noreferrer">W3C Web Authentication</a><span>Public-key standard used for passkey registration and sign-in.</span></li>
            <li><a href="https://developers.google.com/identity/openid-connect/openid-connect" target="_blank" rel="noopener noreferrer">Google OpenID Connect</a><span>Identity flow used by the optional Google sign-in integration.</span></li>
        </ul>
    </div>
</section>
<?php endpage(); ?>
