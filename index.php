<?php
require_once __DIR__ . '/config.php';
require_user();
require_once __DIR__ . '/catalog.php';
$catalogData = catalog_data();
$catalog = $catalogData['items'];
$type = (string)($_GET['type'] ?? 'All');
$search = trim((string)($_GET['q'] ?? ''));
$genre = (string)($_GET['genre'] ?? '');
$sort = (string)($_GET['sort'] ?? 'popular');
$season = (string)($_GET['season'] ?? '');
$studio = trim((string)($_GET['studio'] ?? ''));
$status = (string)($_GET['status'] ?? '');
$format = (string)($_GET['format'] ?? '');
$requestedPage = max(1, (int)($_GET['page'] ?? 1));
$requestedScheduleDate = (string)($_GET['schedule_date'] ?? '');
$scheduleType = (string)($_GET['schedule_type'] ?? 'All');
$types = ['All', 'Anime', 'Donghua', 'Manga', 'Manhwa', 'Novel', 'Movie'];
$genres = ['Action', 'Adventure', 'Comedy', 'Drama', 'Ecchi', 'Fantasy', 'Harem', 'Horror', 'Mystery', 'Psychological', 'Romance', 'Sci-Fi', 'Slice of Life', 'Sports', 'Supernatural', 'Thriller', '18+'];
$validSorts = ['popular', 'latest', 'rating'];
$validStatuses = ['RELEASING', 'FINISHED', 'NOT_YET_RELEASED', 'CANCELLED', 'HIATUS'];
$seasonOptions = [];
$studioCounts = [];
$formatOptions = [];
foreach ($catalog as $item) {
    if (!empty($item['seasonKey'])) $seasonOptions[(string)$item['seasonKey']] = (string)$item['seasonKey'];
    foreach ((array)($item['studios'] ?? []) as $studioName) {
        $studioName = trim((string)$studioName);
        if ($studioName !== '') $studioCounts[$studioName] = ($studioCounts[$studioName] ?? 0) + 1;
    }
    $itemFormat = trim((string)($item['format'] ?? ''));
    if ($itemFormat !== '' && $itemFormat !== 'Unknown') $formatOptions[$itemFormat] = $itemFormat;
}
uksort($seasonOptions, static function (string $left, string $right): int {
    [$leftSeason, $leftYear] = array_pad(explode(' ', $left, 2), 2, '0');
    [$rightSeason, $rightYear] = array_pad(explode(' ', $right, 2), 2, '0');
    $yearOrder = (int)$rightYear <=> (int)$leftYear;
    if ($yearOrder !== 0) return $yearOrder;
    $seasonOrder = ['WINTER' => 0, 'SPRING' => 1, 'SUMMER' => 2, 'FALL' => 3];
    return ($seasonOrder[$rightSeason] ?? -1) <=> ($seasonOrder[$leftSeason] ?? -1);
});
arsort($studioCounts);
$studioOptions = array_slice(array_keys($studioCounts), 0, 40);
sort($formatOptions);
if (!in_array($type, $types, true)) $type = 'All';
if (!in_array($sort, $validSorts, true)) $sort = 'popular';
if (!in_array($genre, $genres, true)) $genre = '';
if (!isset($seasonOptions[$season])) $season = '';
if (!in_array($studio, $studioOptions, true)) $studio = '';
if (!in_array($status, $validStatuses, true)) $status = '';
if (!in_array($format, $formatOptions, true)) $format = '';
if (!in_array($scheduleType, ['All', 'Anime', 'Donghua'], true)) $scheduleType = 'All';

$scheduleTimezone = new DateTimeZone('Asia/Manila');
$scheduleToday = (new DateTimeImmutable('now', $scheduleTimezone))->setTime(0, 0);
$scheduleNow = time();
$scheduleEnd = $scheduleToday->modify('+7 days')->getTimestamp();
$airingData = airing_schedule_data($scheduleNow, $scheduleEnd);
$scheduleDays = [];
for ($dayOffset = 0; $dayOffset < 7; $dayOffset++) {
    $scheduleDay = $scheduleToday->modify('+' . $dayOffset . ' days');
    $scheduleDays[$scheduleDay->format('Y-m-d')] = [
        'date' => $scheduleDay,
        'items' => [],
    ];
}
foreach ($airingData['items'] as $item) {
    $airing = $item['nextAiring'] ?? null;
    if (!is_array($airing) || !in_array($item['category'] ?? '', ['Anime', 'Donghua'], true)) continue;
    $airingAt = (int)($airing['at'] ?? 0);
    if ($airingAt < $scheduleNow || $airingAt >= $scheduleEnd) continue;
    $airingDate = (new DateTimeImmutable('@' . $airingAt))
        ->setTimezone($scheduleTimezone)
        ->format('Y-m-d');
    if (!isset($scheduleDays[$airingDate])) continue;
    $item['scheduleAt'] = $airingAt;
    $scheduleDays[$airingDate]['items'][] = $item;
}
$selectedScheduleDate = isset($scheduleDays[$requestedScheduleDate])
    ? $requestedScheduleDate
    : $scheduleToday->format('Y-m-d');
foreach ($scheduleDays as &$scheduleDay) {
    usort($scheduleDay['items'], static fn(array $left, array $right): int => (int)$left['scheduleAt'] <=> (int)$right['scheduleAt']);
}
unset($scheduleDay);
$selectedScheduleItems = array_values(array_filter(
    $scheduleDays[$selectedScheduleDate]['items'],
    static fn(array $item): bool => $scheduleType === 'All' || ($item['category'] ?? '') === $scheduleType
));
$newSeriesByCategory = [];
foreach (['Anime', 'Donghua'] as $category) {
    $randomTitles = array_values(array_filter(
        $catalog,
        static fn(array $item): bool => ($item['category'] ?? '') === $category
    ));
    shuffle($randomTitles);
    $newSeriesByCategory[$category] = array_slice($randomTitles, 0, 3);
}

$matches = array_values(array_filter($catalog, static function (array $item) use ($type, $search, $genre, $season, $studio, $status, $format): bool {
    if ($type === 'Movie' && ($item['format'] ?? '') !== 'MOVIE') return false;
    if ($type !== 'All' && $type !== 'Movie' && ($item['category'] ?? '') !== $type) return false;
    if ($season !== '' && ($item['seasonKey'] ?? '') !== $season) return false;
    if ($studio !== '' && !in_array($studio, (array)($item['studios'] ?? []), true)) return false;
    if ($status !== '' && ($item['status'] ?? '') !== $status) return false;
    if ($format !== '' && ($item['format'] ?? '') !== $format) return false;
    if ($search !== '' && stripos(
        (string)($item['title'] ?? '') . ' ' . (string)($item['description'] ?? '') . ' '
        . implode(' ', (array)($item['genres'] ?? [])) . ' ' . implode(' ', (array)($item['topics'] ?? []))
        . ' ' . implode(' ', (array)($item['synonyms'] ?? [])),
        $search
    ) === false) return false;
    if ($genre === '18+') return !empty($item['mature']);
    if ($genre !== '') {
        $tags = array_map('strtolower', array_merge(
            (array)($item['genres'] ?? []),
            (array)($item['topics'] ?? [])
        ));
        $genreKey = strtolower($genre);
        $hasGenre = in_array($genreKey, $tags, true)
            || ($genreKey === 'harem' && preg_match('/\bharem\b/i', implode(' ', $tags)) === 1);
        if (!$hasGenre) return false;
    }
    return true;
}));

usort($matches, static function (array $left, array $right) use ($sort): int {
    if ($sort === 'latest') {
        $dateOrder = strcmp((string)($right['startDate'] ?? ''), (string)($left['startDate'] ?? ''));
        if ($dateOrder !== 0) return $dateOrder;
    } elseif ($sort === 'rating') {
        $scoreOrder = (int)($right['score'] ?? 0) <=> (int)($left['score'] ?? 0);
        if ($scoreOrder !== 0) return $scoreOrder;
    } else {
        $collectionOrder = (($left['collection'] ?? '') === 'Popular' ? 0 : 1)
            <=> (($right['collection'] ?? '') === 'Popular' ? 0 : 1);
        if ($collectionOrder !== 0) return $collectionOrder;
    }
    return (int)($right['popularity'] ?? 0) <=> (int)($left['popularity'] ?? 0);
});
$perPage = 30;
$totalMatches = count($matches);
$totalPages = max(1, (int)ceil($totalMatches / $perPage));
$pageNumber = min($requestedPage, $totalPages);
$visibleItems = array_slice($matches, ($pageNumber - 1) * $perPage, $perPage);
$queryString = http_build_query(array_filter(
    ['type' => $type, 'q' => $search, 'genre' => $genre, 'sort' => $sort, 'season' => $season, 'studio' => $studio, 'status' => $status, 'format' => $format],
    static fn(string $value): bool => $value !== ''
));
$topLists = [];
foreach (['Anime', 'Donghua'] as $animationCategory) {
    $categoryItems = array_values(array_filter(
        $catalog,
        static fn(array $item): bool => ($item['category'] ?? '') === $animationCategory
    ));
    $popularItems = $categoryItems;
    usort($popularItems, static function (array $left, array $right): int {
        $popularityOrder = (int)($right['popularity'] ?? 0) <=> (int)($left['popularity'] ?? 0);
        return $popularityOrder !== 0
            ? $popularityOrder
            : (int)($right['score'] ?? 0) <=> (int)($left['score'] ?? 0);
    });
    $topLists[$animationCategory] = array_slice($popularItems, 0, 10);
}
page('Discover');
$spotlight = $topLists['Anime'][0] ?? $topLists['Donghua'][0] ?? null;
?>
<section class="hero hero-discover">
    <?php if ($spotlight && $spotlight['image'] !== ''): ?><img class="hero-backdrop" src="<?= h($spotlight['image']) ?>" alt="" fetchpriority="high"><?php endif; ?>
    <div class="hero-vignette" aria-hidden="true"></div>
    <div class="hero-copy">
    <?php $currentUser = me(); ?>
    <?php if ($currentUser): ?>
        <div class="kicker">WELCOME BACK, <?= h($currentUser['username']) ?></div>
    <?php else: ?>
        <div class="kicker">YOUR NEXT WATCH STARTS HERE</div>
    <?php endif; ?>
        <?php if ($spotlight): ?>
            <div class="spotlight-meta"><span><?= h($spotlight['category']) ?> spotlight</span><?php if ($spotlight['score'] > 0): ?><span>★ <?= number_format($spotlight['score'] / 10, 1) ?></span><?php endif; ?></div>
            <h1><?= h($spotlight['title']) ?></h1>
            <p><?= h($spotlight['description'] !== '' ? mb_substr($spotlight['description'], 0, 210) . (mb_strlen($spotlight['description']) > 210 ? '…' : '') : 'Explore popular anime and donghua, discover upcoming releases, and save your favorites.') ?></p>
            <div class="hero-actions">
                <a class="btn" href="detail.php?id=<?= (int)$spotlight['id'] ?>">Explore title <span aria-hidden="true">→</span></a>
                <a class="hero-secondary" href="#schedule">View schedule</a>
                <a class="hero-secondary" href="#library">Browse titles</a>
            </div>
        <?php else: ?>
            <h1>Find your next <em>favorite.</em></h1>
            <p>Discover anime, donghua, manga, manhwa, and novels. Browse the weekly release schedule and save titles to your watchlist.</p>
            <a class="btn" href="#library">Browse titles <span aria-hidden="true">→</span></a>
        <?php endif; ?>
    </div>
    <?php if ($spotlight && $spotlight['image'] !== ''): ?>
        <a class="hero-poster" href="detail.php?id=<?= (int)$spotlight['id'] ?>" aria-label="View <?= h($spotlight['title']) ?>">
            <img src="<?= h($spotlight['image']) ?>" alt="<?= h($spotlight['title']) ?> cover">
        </a>
    <?php endif; ?>
</section>
<section class="top-highlights" aria-label="Popular anime and donghua">
    <?php foreach ($topLists as $category => $items): ?>
        <section class="top-panel" id="top-<?= h(strtolower($category)) ?>" aria-label="Top 10 <?= h($category) ?>">
            <div class="highlight-heading">
                <div><span class="kicker">POPULAR ON ANILIST</span><h2>Top 10 <?= h($category) ?></h2></div>
                <a href="?type=<?= rawurlencode($category) ?>&amp;sort=popular">View all →</a>
            </div>
            <?php if ($items): ?>
                <ol class="rank-grid">
                    <?php foreach ($items as $index => $item): ?>
                        <li>
                            <a class="rank-card" href="detail.php?id=<?= (int)$item['id'] ?>">
                                <span class="rank-number" aria-label="Rank <?= $index + 1 ?>"><?= str_pad((string)($index + 1), 2, '0', STR_PAD_LEFT) ?></span>
                                <?php if ($item['image'] !== ''): ?>
                                    <img src="<?= h($item['image']) ?>" alt="" loading="lazy">
                                <?php else: ?>
                                    <span class="rank-placeholder" aria-hidden="true"><?= h(mb_substr($item['title'], 0, 1)) ?></span>
                                <?php endif; ?>
                                <span class="rank-copy">
                                    <strong><?= h($item['title']) ?></strong>
                                    <span><?= $item['score'] > 0 ? '★ ' . number_format($item['score'] / 10, 1) : 'Score not rated' ?></span>
                                </span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ol>
            <?php else: ?>
                <p class="highlight-empty">No popular titles are available right now.</p>
            <?php endif; ?>
        </section>
    <?php endforeach; ?>
</section>
<section class="weekly-layout" id="schedule" aria-labelledby="schedule-heading">
    <div class="weekly-main">
        <div class="weekly-title">
            <div>
                <span class="kicker">PLAN YOUR NEXT WATCH</span>
                <h2 id="schedule-heading"><span aria-hidden="true">🔥</span> Weekly schedule</h2>
            </div>
            <span class="schedule-timezone">Times: PHT (UTC+8)</span>
        </div>
        <div class="schedule-day-tabs" aria-label="Select a schedule day">
            <?php foreach ($scheduleDays as $dateKey => $day): ?>
                <?php
                $dayItems = $day['items'];
                if ($scheduleType !== 'All') {
                    $dayItems = array_values(array_filter(
                        $dayItems,
                        static fn(array $item): bool => ($item['category'] ?? '') === $scheduleType
                    ));
                }
                $dayQuery = http_build_query(['schedule_date' => $dateKey, 'schedule_type' => $scheduleType]);
                $isToday = $dateKey === $scheduleToday->format('Y-m-d');
                ?>
                <a class="schedule-day <?= $dateKey === $selectedScheduleDate ? 'active' : '' ?>" href="?<?= h($dayQuery) ?>#schedule" <?= $dateKey === $selectedScheduleDate ? 'aria-current="date"' : '' ?>>
                    <strong><?= $isToday ? 'TODAY' : h(strtoupper($day['date']->format('D'))) ?></strong>
                    <span><?= count($dayItems) ?> <?= count($dayItems) === 1 ? 'release' : 'releases' ?></span>
                </a>
            <?php endforeach; ?>
        </div>
        <div class="schedule-category-tabs" aria-label="Filter schedule category">
            <?php foreach (['All', 'Anime', 'Donghua'] as $category): ?>
                <?php $categoryQuery = http_build_query(['schedule_date' => $selectedScheduleDate, 'schedule_type' => $category]); ?>
                <a class="<?= $scheduleType === $category ? 'active' : '' ?>" href="?<?= h($categoryQuery) ?>#schedule"><?= h($category) ?></a>
            <?php endforeach; ?>
        </div>
        <?php if ($selectedScheduleItems): ?>
            <div class="schedule-grid">
                <?php foreach ($selectedScheduleItems as $item): ?>
                    <?php
                    $airingAt = (int)$item['scheduleAt'];
                    $airingDate = (new DateTimeImmutable('@' . $airingAt))
                        ->setTimezone($scheduleTimezone);
                    $airingEpisode = (int)($item['nextAiring']['episode'] ?? 0);
                    ?>
                    <article class="schedule-card">
                        <a class="schedule-art-link" href="detail.php?id=<?= (int)$item['id'] ?>">
                            <?php if ($item['image'] !== ''): ?>
                                <img src="<?= h($item['image']) ?>" alt="<?= h($item['title']) ?> cover" loading="lazy">
                            <?php else: ?>
                                <span class="schedule-art-placeholder"><?= h(mb_substr($item['title'], 0, 1)) ?></span>
                            <?php endif; ?>
                            <span class="schedule-badge"><?= $airingDate->format('Y-m-d') === $scheduleToday->format('Y-m-d') ? 'AIRING TODAY' : h(strtoupper($item['category'])) ?></span>
                        </a>
                        <div class="schedule-card-copy">
                            <a href="detail.php?id=<?= (int)$item['id'] ?>"><strong><?= h($item['title']) ?></strong></a>
                            <span><?= $airingEpisode > 0 ? 'Episode ' . $airingEpisode : 'New episode' ?> · <?= h($airingDate->format('g:i A')) ?></span>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="schedule-empty">
                <strong><?= $airingData['warning'] !== '' ? 'Schedule feed could not be loaded.' : 'No scheduled releases for this day.' ?></strong>
                <span><?= $airingData['warning'] !== '' ? h($airingData['warning']) : 'AniList has not listed a release for this date yet. Try another day or category.' ?></span>
            </div>
        <?php endif; ?>
        <?php if ($airingData['warning'] !== '' && $selectedScheduleItems): ?><p class="schedule-warning" role="status"><?= h($airingData['warning']) ?></p><?php endif; ?>
    </div>
    <aside class="weekly-sidebar" aria-label="Search filters and new series">
        <section class="schedule-filter">
            <div class="sidebar-title"><span aria-hidden="true">🔥</span><h3>Filter search</h3></div>
            <form method="get" action="index.php#library" class="schedule-filter-form">
                <input type="hidden" name="schedule_date" value="<?= h($selectedScheduleDate) ?>">
                <input type="hidden" name="schedule_type" value="<?= h($scheduleType) ?>">
                <label class="schedule-search">
                    <span class="visually-hidden">Search titles</span>
                    <input name="q" value="<?= h($search) ?>" placeholder="Search titles..." aria-label="Search titles">
                </label>
                <label class="schedule-filter-field">
                    <span>Category</span>
                    <select name="type">
                        <?php foreach ($types as $option): ?>
                            <option value="<?= h($option) ?>" <?= $type === $option ? 'selected' : '' ?>><?= h($option) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="schedule-filter-field">
                    <span>Genre</span>
                    <select name="genre">
                        <option value="">All genres</option>
                        <?php foreach ($genres as $option): ?>
                            <option value="<?= h($option) ?>" <?= $genre === $option ? 'selected' : '' ?>><?= h($option === '18+' ? '18+ · mature themes' : $option) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="schedule-filter-field">
                    <span>Season</span>
                    <select name="season">
                        <option value="">All seasons</option>
                        <?php foreach ($seasonOptions as $option): ?>
                            <option value="<?= h($option) ?>" <?= $season === $option ? 'selected' : '' ?>><?= h(ucfirst(strtolower($option))) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="schedule-filter-field">
                    <span>Studio</span>
                    <select name="studio">
                        <option value="">All studios</option>
                        <?php foreach ($studioOptions as $option): ?>
                            <option value="<?= h($option) ?>" <?= $studio === $option ? 'selected' : '' ?>><?= h($option) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="schedule-filter-field">
                    <span>Status</span>
                    <select name="status">
                        <option value="">All statuses</option>
                        <?php foreach ($validStatuses as $option): ?>
                            <option value="<?= h($option) ?>" <?= $status === $option ? 'selected' : '' ?>><?= h(ucwords(strtolower(str_replace('_', ' ', $option)))) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="schedule-filter-field">
                    <span>Format</span>
                    <select name="format">
                        <option value="">All formats</option>
                        <?php foreach ($formatOptions as $option): ?>
                            <option value="<?= h($option) ?>" <?= $format === $option ? 'selected' : '' ?>><?= h(str_replace('_', ' ', $option)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="schedule-filter-field">
                    <span>Order by</span>
                    <select name="sort">
                        <option value="popular" <?= $sort === 'popular' ? 'selected' : '' ?>>Most popular</option>
                        <option value="latest" <?= $sort === 'latest' ? 'selected' : '' ?>>Latest releases</option>
                        <option value="rating" <?= $sort === 'rating' ? 'selected' : '' ?>>Top rated</option>
                    </select>
                </label>
                <button class="filter-submit" type="submit">Search library</button>
            </form>
        </section>
        <section class="new-series-panel">
            <div class="sidebar-title"><span aria-hidden="true">✦</span><h3>New series</h3></div>
            <p class="random-picks-note">Random anime and donghua picks from the catalog.</p>
            <?php foreach ($newSeriesByCategory as $category => $items): ?>
                <div class="random-category">
                    <h4><?= h($category) ?></h4>
                    <?php if ($items): ?>
                        <ul class="new-series-list">
                            <?php foreach ($items as $item): ?>
                                <li>
                                    <a class="new-series-card" href="detail.php?id=<?= (int)$item['id'] ?>">
                                        <?php if ($item['image'] !== ''): ?>
                                            <img src="<?= h($item['image']) ?>" alt="" loading="lazy">
                                        <?php else: ?>
                                            <span class="new-series-placeholder"><?= h(mb_substr($item['title'], 0, 1)) ?></span>
                                        <?php endif; ?>
                                        <span class="new-series-copy">
                                            <strong><?= h($item['title']) ?></strong>
                                            <span><?= h($item['format']) ?><?= $item['score'] > 0 ? ' · ★ ' . number_format($item['score'] / 10, 1) : '' ?></span>
                                            <span><?= h(implode(', ', array_slice((array)($item['genres'] ?? []), 0, 3)) ?: 'Discover this title') ?></span>
                                        </span>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p class="highlight-empty">No <?= h(strtolower($category)) ?> in the catalog.</p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </section>
    </aside>
</section>
<section id="library">
    <div class="section-title">
        <div><span class="kicker"><?= count($catalog) ?> TITLES · FRESH DISCOVERIES</span><h2>Explore the library</h2></div>
        <span class="results-count"><?= $totalMatches ?> matches</span>
    </div>
    <div class="tabs">
        <?php foreach ($types as $tab): ?>
            <?php $tabQuery = http_build_query(array_filter(['type' => $tab, 'q' => $search, 'genre' => $genre, 'sort' => $sort, 'season' => $season, 'studio' => $studio, 'status' => $status, 'format' => $format], static fn(string $value): bool => $value !== '')); ?>
            <a class="<?= $type === $tab ? 'active' : '' ?>" href="?<?= h($tabQuery) ?>"><?= h($tab) ?></a>
        <?php endforeach; ?>
    </div>
    <?php if ($genre === '18+'): ?><p class="mature-note">Mature-theme tags are provided by AniList; explicitly adult titles are excluded.</p><?php endif; ?>
    <div class="cards">
        <?php foreach ($visibleItems as $item): ?>
            <article class="poster">
                <a href="detail.php?id=<?= (int)$item['id'] ?>">
                    <?php if ($item['image'] !== ''): ?>
                        <img class="poster-image" src="<?= h($item['image']) ?>" alt="<?= h($item['title']) ?> cover" loading="lazy">
                    <?php else: ?>
                        <div class="poster-art"><?= h($item['title']) ?></div>
                    <?php endif; ?>
                    <div class="poster-info">
                        <small><?= h($item['category']) ?> · <?= h($item['format']) ?></small>
                        <h3><?= h($item['title']) ?></h3>
                        <div class="poster-tags">
                            <span class="collection-tag <?= ($item['collection'] ?? '') === 'Latest' ? 'latest-tag' : '' ?>"><?= h($item['collection'] ?? 'Popular') ?></span>
                            <?php if (!empty($item['mature'])): ?><span class="mature-tag">18+</span><?php endif; ?>
                            <?php foreach (array_slice(array_values(array_unique(array_merge((array)($item['genres'] ?? []), (array)($item['topics'] ?? [])))), 0, 2) as $itemGenre): ?>
                                <span><?= h($itemGenre) ?></span>
                            <?php endforeach; ?>
                        </div>
                        <span>Explore details →</span>
                    </div>
                </a>
            </article>
        <?php endforeach; ?>
        <?php if ($totalMatches === 0): ?><p class="empty-results">No matching titles. Try a different search or filter.</p><?php endif; ?>
    </div>
    <?php if ($totalPages > 1): ?>
        <nav class="pagination" aria-label="Catalog pages">
            <?php if ($pageNumber > 1): ?><a href="?<?= h($queryString) ?>&amp;page=<?= $pageNumber - 1 ?>">← Previous</a><?php endif; ?>
            <span>Page <?= $pageNumber ?> of <?= $totalPages ?> · showing <?= min($totalMatches, ($pageNumber - 1) * $perPage + 1) ?>–<?= min($totalMatches, $pageNumber * $perPage) ?> of <?= $totalMatches ?></span>
            <?php if ($pageNumber < $totalPages): ?><a href="?<?= h($queryString) ?>&amp;page=<?= $pageNumber + 1 ?>">Next →</a><?php endif; ?>
        </nav>
    <?php endif; ?>
    <?php if ($catalogData['warning'] !== ''): ?><p class="notice" role="status"><?= h($catalogData['warning']) ?></p><?php endif; ?>
    <?php if ($catalogData['error'] !== ''): ?><p class="error" role="alert"><?= h($catalogData['error']) ?></p><?php endif; ?>
</section>
<?php
$videos = array_values(array_filter(
    $catalog,
    static fn(array $item): bool => ($item['trailer'] ?? '') !== '' && in_array($item['category'] ?? '', ['Anime', 'Donghua'], true)
));
$videos = array_slice($videos, 0, 10);
?>
<section class="videos">
    <div class="section-title">
        <div><span class="kicker">OFFICIAL YOUTUBE EMBEDS</span><h2>Anime trailers</h2></div>
        <p><?= count($videos) ?> playable trailers</p>
    </div>
    <?php if ($videos): ?>
        <div class="video-grid">
            <?php foreach ($videos as $video): ?>
                <article class="video-card">
                    <iframe src="https://www.youtube-nocookie.com/embed/<?= h($video['trailer']) ?>" title="<?= h($video['title']) ?> official trailer" loading="lazy" allow="accelerometer; encrypted-media; gyroscope; picture-in-picture" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>
                    <h3><a href="detail.php?id=<?= (int)$video['id'] ?>"><?= h($video['title']) ?></a></h3>
                </article>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p class="notice">No embeddable YouTube trailers were returned by the catalog API.</p>
    <?php endif; ?>
</section>
<?php endpage(); ?>
