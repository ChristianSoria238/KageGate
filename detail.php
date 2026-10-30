<?php
require_once __DIR__ . '/config.php';
require_user();
require_once __DIR__ . '/catalog.php';
$catalogData = catalog_data();
$catalog = [];
foreach ($catalogData['items'] as $entry) $catalog[(int)$entry['id']] = $entry;
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if ($id === null || $id === false || !isset($catalog[$id])) {
    http_response_code(404);
    exit('Title not found.');
}
$item = $catalog[$id];
$message = '';
$currentUser = me();
$isInWatchlist = false;
if ($currentUser) {
    $savedCheck = db()->prepare('SELECT 1 FROM watchlist WHERE user_id = ? AND title_id = ?');
    $savedCheck->execute([(int)$currentUser['id'], $id]);
    $isInWatchlist = (bool)$savedCheck->fetchColumn();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf();
    $user = require_user();
    $query = db()->prepare('INSERT IGNORE INTO watchlist (user_id, title_id) VALUES (?, ?)');
    $query->execute([(int)$user['id'], $id]);
    if ($query->rowCount() === 1) {
        create_notification((int)$user['id'], 'Added to your watchlist', $item['title'] . ' was added to your watchlist.');
        $message = 'Saved to your watchlist.';
        $isInWatchlist = true;
    } else {
        $message = 'Already in your watchlist.';
        $isInWatchlist = true;
    }
}
page($item['title']);
?>
<section class="detail">
    <?php if ($item['image'] !== ''): ?>
        <img class="detail-image" src="<?= h($item['image']) ?>" alt="<?= h($item['title']) ?> cover">
    <?php else: ?>
        <div class="poster-art"><?= h($item['title']) ?></div>
    <?php endif; ?>
    <div>
        <span class="kicker"><?= h($item['category']) ?> / <?= h($item['format']) ?></span>
        <h1><?= h($item['title']) ?></h1>
        <?php $alternateTitles = array_slice((array)($item['synonyms'] ?? []), 0, 3); ?>
        <?php if ($alternateTitles): ?><p class="alternate-titles">Also known as: <?= h(implode(' · ', $alternateTitles)) ?></p><?php endif; ?>
        <div class="detail-meta">
            <?php if (!empty($item['startDate'])): ?><span><?= h(substr((string)$item['startDate'], 0, 4)) ?></span><?php endif; ?>
            <?php if (!empty($item['score'])): ?><span>★ <?= h((string)$item['score']) ?> / 100</span><?php endif; ?>
            <?php if (!empty($item['mature'])): ?><span class="mature-tag">18+ · mature themes</span><?php endif; ?>
            <?php foreach (array_slice(array_values(array_unique(array_merge((array)($item['genres'] ?? []), (array)($item['topics'] ?? [])))), 0, 8) as $genre): ?><span><?= h($genre) ?></span><?php endforeach; ?>
        </div>
        <p><?= h($item['description'] !== '' ? $item['description'] : 'No description is available.') ?></p>
        <p><?= $item['episodes'] ? h((string)$item['episodes']) . ' episodes' : 'Episode count unavailable' ?></p>
        <?php if ($item['trailer'] !== ''): ?>
            <div class="detail-video">
                <iframe src="https://www.youtube-nocookie.com/embed/<?= h($item['trailer']) ?>" title="<?= h($item['title']) ?> official trailer" allow="accelerometer; encrypted-media; gyroscope; picture-in-picture" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>
            </div>
        <?php endif; ?>
        <?php if ($item['streams']): ?>
            <h2>Episodes &amp; viewing links</h2>
            <ul class="episode-list episode-cards">
                <?php foreach ($item['streams'] as $episode): ?>
                    <li>
                        <a class="episode-card" href="<?= h($episode['url']) ?>" target="_blank" rel="noopener noreferrer">
                            <span class="episode-art">
                                <?php if ($item['image'] !== ''): ?><img src="<?= h($item['image']) ?>" alt="" loading="lazy"><?php else: ?><span><?= h($item['title']) ?></span><?php endif; ?>
                                <span class="episode-play" aria-hidden="true">▶</span>
                            </span>
                            <span class="episode-copy"><strong><?= h($item['title']) ?></strong><span><?= h($episode['title']) ?></span></span>
                            <span class="episode-open" aria-hidden="true">↗</span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <?php if ($item['url'] !== ''): ?><p><a href="<?= h($item['url']) ?>" target="_blank" rel="noopener noreferrer">More information on AniList →</a></p><?php endif; ?>
        <?php if ($catalogData['warning'] !== ''): ?><p class="notice"><?= h($catalogData['warning']) ?></p><?php endif; ?>
        <div class="detail-actions">
            <?php if ($message): ?><p class="watchlist-message <?= $isInWatchlist ? 'saved' : '' ?>" role="status"><?= h($message) ?></p><?php endif; ?>
            <?php if ($currentUser && $isInWatchlist): ?>
                <a class="watchlist-button is-saved" href="watchlist.php"><span aria-hidden="true">✓</span> In your watchlist <span aria-hidden="true">→</span></a>
            <?php elseif ($currentUser): ?>
                <form method="post">
                    <input type="hidden" name="csrf" value="<?= h(token()) ?>">
                    <button class="watchlist-button" type="submit"><span aria-hidden="true">＋</span> Add to watchlist</button>
                </form>
            <?php else: ?>
                <a class="watchlist-button" href="login.php">Sign in to save <span aria-hidden="true">→</span></a>
            <?php endif; ?>
            <a class="detail-back" href="index.php?type=<?= rawurlencode((string)$item['category']) ?>#library"><span aria-hidden="true">←</span> Back to <?= h(strtolower((string)$item['category'])) ?> titles</a>
        </div>
    </div>
</section>
<?php endpage(); ?>
