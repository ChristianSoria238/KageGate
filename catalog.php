<?php
declare(strict_types=1);

const CATALOG_CACHE_KEY = 'anilist-library-v11';
const CATALOG_CACHE_SECONDS = 21600;
const CATALOG_PREVIOUS_CACHE_KEYS = ['anilist-library-v10', 'anilist-library-v9', 'anilist-library-v8', 'anilist-library-v7', 'anilist-library-v6', 'anilist-library-v5', 'anilist-library-v4', 'anilist-library-v3'];
const CATALOG_LEGACY_CACHE_KEY = 'anilist-jp-cn-v2';
const AIRING_CACHE_KEY = 'anilist-airing-week-v1';
const AIRING_CACHE_SECONDS = 600;

function anilist_graphql(string $query): array {
    $curl = curl_init('https://graphql.anilist.co');
    if ($curl === false) throw new RuntimeException('Could not initialize the catalog API request.');
    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode(['query' => $query], JSON_THROW_ON_ERROR),
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'Content-Type: application/json',
            'User-Agent: KageGate/1.0 (catalog)',
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_MAXFILESIZE => 5000000,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
    ]);
    $body = curl_exec($curl);
    $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $curlError = curl_error($curl);
    curl_close($curl);
    if (!is_string($body)) {
        throw new RuntimeException('Catalog API request failed' . ($curlError !== '' ? ': ' . $curlError : ' (HTTP ' . $status . ').'));
    }

    $response = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
    if ($status !== 200) {
        $detail = trim((string)($response['errors'][0]['message'] ?? ''));
        throw new RuntimeException('Catalog API request failed' . ($detail !== '' ? ': ' . $detail : ' (HTTP ' . $status . ').'));
    }
    if (!empty($response['errors']) || !isset($response['data'])) {
        throw new RuntimeException('Catalog API returned incomplete catalog data.');
    }
    return $response['data'];
}

function anilist_query(array $pages, bool $includeStreams): string {
    $fields = <<<'GRAPHQL'
id
title { english romaji }
description(asHtml: false)
format
episodes
status
coverImage { large }
trailer { id site }
siteUrl
genres
tags { name rank isAdult }
startDate { year month day }
season
seasonYear
averageScore
popularity
nextAiringEpisode { airingAt episode }
isAdult
synonyms
studios { nodes { name } }
GRAPHQL;
    if ($includeStreams) $fields .= "\nstreamingEpisodes { title url site }";

    $selections = [];
    foreach ($pages as $page) {
        $pageParts = [
            'page: ' . (int)$page['page'],
            'perPage: ' . (int)($page['perPage'] ?? 50),
        ];
        $mediaParts = [];
        if (isset($page['type'])) $mediaParts[] = 'type: ' . $page['type'];
        if (isset($page['country'])) $mediaParts[] = 'countryOfOrigin: "' . $page['country'] . '"';
        if (isset($page['format'])) $mediaParts[] = 'format: ' . $page['format'];
        if (isset($page['search'])) $mediaParts[] = 'search: ' . json_encode($page['search'], JSON_THROW_ON_ERROR);
        $mediaParts[] = 'sort: ' . $page['sort'];
        $mediaParts[] = 'isAdult: false';
        $selections[] = $page['alias'] . ': Page(' . implode(', ', $pageParts) . ') {'
            . ' media(' . implode(', ', $mediaParts) . ') { ' . $fields . ' } }';
    }
    return "query {\n" . implode("\n", $selections) . "\n}";
}

function catalog_item(array $media, string $category, string $collection): ?array {
    $id = filter_var($media['id'] ?? null, FILTER_VALIDATE_INT);
    $title = trim((string)($media['title']['english'] ?? ''));
    if ($title === '') $title = trim((string)($media['title']['romaji'] ?? ''));
    if (!$id || $title === '') return null;
    $synonyms = array_values(array_unique(array_filter(
        array_map('strval', (array)($media['synonyms'] ?? [])),
        static fn(string $synonym): bool => $synonym !== '' && strcasecmp($synonym, $title) !== 0
    )));

    $cover = (string)($media['coverImage']['large'] ?? '');
    $coverHost = parse_url($cover, PHP_URL_HOST);
    if (parse_url($cover, PHP_URL_SCHEME) !== 'https'
        || !is_string($coverHost)
        || !preg_match('/^s[1-4]\.anilist\.co$/D', $coverHost)) {
        $cover = '';
    }

    $trailerId = '';
    if (strtolower((string)($media['trailer']['site'] ?? '')) === 'youtube') {
        $candidate = (string)($media['trailer']['id'] ?? '');
        if (preg_match('/^[A-Za-z0-9_-]{11}$/D', $candidate)) $trailerId = $candidate;
    }

    $providers = ['crunchyroll.com', 'bilibili.com', 'bilibili.tv', 'youtube.com', 'youtube-nocookie.com', 'youtu.be', 'netflix.com', 'primevideo.com', 'hulu.com', 'hidive.com', 'iq.com'];
    $streams = [];
    foreach (($media['streamingEpisodes'] ?? []) as $episode) {
        $url = (string)($episode['url'] ?? '');
        $url = preg_replace('/^http:/i', 'https:', $url);
        $host = parse_url($url, PHP_URL_HOST);
        $allowedProvider = is_string($host) && array_filter(
            $providers,
            static fn(string $provider): bool => $host === $provider || str_ends_with($host, '.' . $provider)
        );
        if (parse_url($url, PHP_URL_SCHEME) !== 'https' || !$allowedProvider) continue;
        $streams[] = [
            'title' => trim((string)($episode['title'] ?? 'Official episode')),
            'url' => $url,
            'site' => trim((string)($episode['site'] ?? $host)),
        ];
    }

    $genres = array_values(array_unique(array_filter(
        array_map('strval', (array)($media['genres'] ?? [])),
        static fn(string $genre): bool => $genre !== ''
    )));
    $topics = [];
    $mature = false;
    foreach (($media['tags'] ?? []) as $tag) {
        $name = trim((string)($tag['name'] ?? ''));
        if ($name !== '') $topics[] = $name;
        if (!empty($tag['isAdult'])) $mature = true;
    }
    $mature = $mature || !empty($media['isAdult']);
    $start = $media['startDate'] ?? [];
    $dateParts = array_map('intval', [
        $start['year'] ?? 0,
        $start['month'] ?? 0,
        $start['day'] ?? 0,
    ]);
    $startDate = $dateParts[0] > 0
        ? sprintf('%04d-%02d-%02d', $dateParts[0], max(1, $dateParts[1]), max(1, $dateParts[2]))
        : '';
    $startDateLabel = '';
    if ($dateParts[0] > 0 && $dateParts[1] >= 1 && $dateParts[1] <= 12) {
        $startDateLabel = $dateParts[2] >= 1 && $dateParts[2] <= 31
            ? sprintf('%s %d, %d', date('M', mktime(0, 0, 0, $dateParts[1], 1, $dateParts[0])), $dateParts[2], $dateParts[0])
            : sprintf('%s %d', date('M', mktime(0, 0, 0, $dateParts[1], 1, $dateParts[0])), $dateParts[0]);
    } elseif ($dateParts[0] > 0) {
        $startDateLabel = (string)$dateParts[0];
    }
    $nextAiring = $media['nextAiringEpisode'] ?? null;
    $nextAiringTimestamp = (int)($nextAiring['airingAt'] ?? 0);
    $nextAiringEpisode = $nextAiringTimestamp > time()
        ? [
            'at' => $nextAiringTimestamp,
            'episode' => max(0, (int)($nextAiring['episode'] ?? 0)),
        ]
        : null;
    $season = strtoupper(trim((string)($media['season'] ?? '')));
    $seasonYear = max(0, (int)($media['seasonYear'] ?? 0));
    $seasonKey = in_array($season, ['WINTER', 'SPRING', 'SUMMER', 'FALL'], true) && $seasonYear > 0
        ? $season . ' ' . $seasonYear
        : '';
    $studios = array_values(array_unique(array_filter(
        array_map(
            static fn(array $studio): string => trim((string)($studio['name'] ?? '')),
            (array)($media['studios']['nodes'] ?? [])
        ),
        static fn(string $studio): bool => $studio !== ''
    )));

    $description = trim(html_entity_decode(
        strip_tags((string)($media['description'] ?? '')),
        ENT_QUOTES | ENT_HTML5,
        'UTF-8'
    ));
    $siteUrl = (string)($media['siteUrl'] ?? '');
    if (parse_url($siteUrl, PHP_URL_SCHEME) !== 'https'
        || parse_url($siteUrl, PHP_URL_HOST) !== 'anilist.co') {
        $siteUrl = '';
    }
    return [
        'id' => $id,
        'title' => $title,
        'synonyms' => $synonyms,
        'category' => $category,
        'collection' => $collection,
        'format' => (string)($media['format'] ?? 'Unknown'),
        'status' => (string)($media['status'] ?? ''),
        'season' => $season,
        'seasonYear' => $seasonYear,
        'seasonKey' => $seasonKey,
        'studios' => $studios,
        'episodes' => isset($media['episodes']) ? max(0, (int)$media['episodes']) : null,
        'description' => mb_substr($description, 0, 1200),
        'image' => $cover,
        'trailer' => $trailerId,
        'streams' => $streams,
        'url' => $siteUrl,
        'genres' => $genres,
        'topics' => array_values(array_unique($topics)),
        'mature' => $mature,
        'startDate' => $startDate,
        'startDateLabel' => $startDateLabel,
        'nextAiring' => $nextAiringEpisode,
        'score' => max(0, (int)($media['averageScore'] ?? 0)),
        'popularity' => max(0, (int)($media['popularity'] ?? 0)),
    ];
}

function animation_collection(array $response, string $category, array $featuredMedia = [], int $targetCount = 75): array {
    $popular = array_merge($response['popular']['media'] ?? [], $response['popular2']['media'] ?? []);
    if (count($popular) < $targetCount) throw new RuntimeException('AniList returned fewer than ' . $targetCount . ' popular ' . strtolower($category) . ' titles.');

    $items = [];
    $popularIds = [];
    foreach ($featuredMedia as $media) {
        $item = catalog_item($media, $category, 'Popular');
        if ($item === null || isset($popularIds[$item['id']])) continue;
        $popularIds[$item['id']] = true;
        $items[] = $item;
    }
    foreach ($popular as $media) {
        $item = catalog_item($media, $category, 'Popular');
        if ($item === null || isset($popularIds[$item['id']])) continue;
        $popularIds[$item['id']] = true;
        $items[] = $item;
        if (count($items) === $targetCount) break;
    }
    if (count($items) < $targetCount) throw new RuntimeException('AniList returned duplicate or invalid popular titles for ' . strtolower($category) . '.');

    $latest = [];
    foreach (['recent1', 'recent2', 'recent3'] as $page) {
        foreach (($response[$page]['media'] ?? []) as $media) {
            $id = filter_var($media['id'] ?? null, FILTER_VALIDATE_INT);
            if (!$id || isset($popularIds[$id])) continue;
            $item = catalog_item($media, $category, 'Latest');
            if ($item === null) continue;
            $popularIds[$id] = true;
            $latest[] = $item;
            if (count($latest) === $targetCount) break 2;
        }
    }
    if (count($latest) < $targetCount) throw new RuntimeException('AniList returned fewer than ' . $targetCount . ' distinct recent ' . strtolower($category) . ' titles.');
    return array_merge(array_slice($items, 0, $targetCount), $latest);
}

function fetch_catalog_from_anilist(): array {
    $animePages = static function (string $country): array {
        $pages = [
            ['alias' => 'popular', 'page' => 1, 'perPage' => 50, 'type' => 'ANIME', 'country' => $country, 'sort' => 'POPULARITY_DESC'],
            ['alias' => 'popular2', 'page' => 2, 'perPage' => 50, 'type' => 'ANIME', 'country' => $country, 'sort' => 'POPULARITY_DESC'],
        ];
        foreach ([1, 2, 3] as $number) {
            $pages[] = ['alias' => 'recent' . $number, 'page' => $number, 'perPage' => 50, 'type' => 'ANIME', 'country' => $country, 'sort' => 'START_DATE_DESC'];
        }
        return $pages;
    };

    $japan = anilist_graphql(anilist_query($animePages('JP'), true));
    $china = anilist_graphql(anilist_query($animePages('CN'), true));
    $featuredDonghuaTitles = [
        'Renegade Immortal',
        'Swallowed Star',
        'Perfect World',
        'Fights Break Sphere S1',
        'Tomb of Fallen Gods',
        'Beyond the Timescape',
        'Jade Dynasty',
        'Mu Shen Ji',
        'Soul Land 2: The Peerless Tang Clan',
        'Cang Yuan Tu',
        'Slay the Gods',
        'Jade Dynasty Final Season 4',
        'Golden Curse',
        'Ancient God Sovereign',
        'One Slash to the Heavens',
        'The Sacred Ruins',
        'The Swords: The 13th Sword',
    ];
    $featuredDonghuaPages = [];
    foreach ($featuredDonghuaTitles as $index => $title) {
        $featuredDonghuaPages[] = [
            'alias' => 'featured' . $index,
            'page' => 1,
            'perPage' => 1,
            'type' => 'ANIME',
            'country' => 'CN',
            'search' => $title,
            'sort' => 'POPULARITY_DESC',
        ];
    }
    $featuredDonghua = [];
    foreach (array_chunk($featuredDonghuaPages, 10) as $pageBatch) {
        $batchResponse = anilist_graphql(anilist_query($pageBatch, true));
        foreach ($pageBatch as $page) {
            $featuredDonghua[$page['alias']] = $batchResponse[$page['alias']] ?? [];
        }
    }
    $comics = anilist_graphql(anilist_query([
        ['alias' => 'manga', 'page' => 1, 'type' => 'MANGA', 'country' => 'JP', 'format' => 'MANGA', 'sort' => 'POPULARITY_DESC'],
        ['alias' => 'manhwa', 'page' => 1, 'type' => 'MANGA', 'country' => 'KR', 'format' => 'MANGA', 'sort' => 'POPULARITY_DESC'],
    ], false));
    $novels = anilist_graphql(anilist_query([
        ['alias' => 'novels', 'page' => 1, 'type' => 'MANGA', 'format' => 'NOVEL', 'sort' => 'POPULARITY_DESC'],
    ], false));

    foreach ([
        [$japan, ['popular', 'popular2', 'recent1', 'recent2', 'recent3']],
        [$china, ['popular', 'popular2', 'recent1', 'recent2', 'recent3']],
        [$comics, ['manga', 'manhwa']],
        [$novels, ['novels']],
    ] as [$result, $aliases]) {
        foreach ($aliases as $alias) {
            if (!isset($result[$alias]['media']) || !is_array($result[$alias]['media'])) {
                throw new RuntimeException('AniList returned incomplete ' . $alias . ' catalog data.');
            }
        }
    }
    foreach (array_keys($featuredDonghuaPages) as $index) {
        if (!isset($featuredDonghua['featured' . $index]['media'])) {
            throw new RuntimeException('AniList returned incomplete featured donghua data.');
        }
    }

    $featuredDonghuaMedia = array_values(array_filter(array_map(
        static fn(int $index): ?array => $featuredDonghua['featured' . $index]['media'][0] ?? null,
        array_keys($featuredDonghuaPages)
    )));
    $items = array_merge(
        animation_collection($japan, 'Anime', [], 75),
        animation_collection($china, 'Donghua', $featuredDonghuaMedia, 75)
    );
    foreach (['manga' => 'Manga', 'manhwa' => 'Manhwa', 'novels' => 'Novel'] as $alias => $category) {
        $results = $alias === 'novels' ? $novels[$alias]['media'] : $comics[$alias]['media'];
        if (count($results) < 50) throw new RuntimeException('AniList returned fewer than 50 ' . strtolower($category) . ' titles.');
        foreach (array_slice($results, 0, 50) as $media) {
            $item = catalog_item($media, $category, 'Popular');
            if ($item !== null) $items[] = $item;
        }
    }

    $counts = array_count_values(array_column($items, 'category'));
    foreach (['Anime', 'Donghua', 'Manga', 'Manhwa', 'Novel'] as $category) {
        if (($counts[$category] ?? 0) < 50) throw new RuntimeException('Catalog is missing required ' . strtolower($category) . ' titles.');
    }
    return $items;
}

function fetch_airing_schedule_from_anilist(int $from, int $to): array {
    $selections = [];
    for ($page = 1; $page <= 4; $page++) {
        $selections[] = 'day' . $page . ': Page(page: ' . $page . ', perPage: 50) {'
            . ' airingSchedules(airingAt_greater: ' . $from
            . ', airingAt_lesser: ' . $to
            . ', sort: TIME) { airingAt episode media { id title { english romaji }'
            . ' format status episodes coverImage { large } isAdult countryOfOrigin'
            . ' } } }';
    }

    $response = anilist_graphql("query {\n" . implode("\n", $selections) . "\n}");
    $items = [];
    $seen = [];
    foreach (array_keys($selections) as $pageIndex) {
        foreach (($response['day' . ($pageIndex + 1)]['airingSchedules'] ?? []) as $schedule) {
            $media = $schedule['media'] ?? null;
            if (!is_array($media)) continue;
            $country = (string)($media['countryOfOrigin'] ?? '');
            if ($country !== 'JP' && $country !== 'CN') continue;
            if (!empty($media['isAdult'])) continue;
            $airingAt = (int)($schedule['airingAt'] ?? 0);
            if ($airingAt < $from || $airingAt >= $to) continue;
            $item = catalog_item($media, $country === 'CN' ? 'Donghua' : 'Anime', 'Schedule');
            if ($item === null) continue;
            $key = $item['id'] . ':' . $airingAt . ':' . (int)($schedule['episode'] ?? 0);
            if (isset($seen[$key])) continue;
            $seen[$key] = true;
            $item['scheduleAt'] = $airingAt;
            $item['nextAiring'] = [
                'at' => $airingAt,
                'episode' => max(0, (int)($schedule['episode'] ?? 0)),
            ];
            $items[] = $item;
        }
    }
    usort($items, static fn(array $left, array $right): int => (int)$left['scheduleAt'] <=> (int)$right['scheduleAt']);
    return $items;
}

function airing_schedule_data(int $from, int $to): array {
    static $result = null;
    if (is_array($result)) return $result;

    $statement = db()->prepare('SELECT payload, refreshed_at FROM catalog_cache WHERE cache_key = ?');
    $statement->execute([AIRING_CACHE_KEY]);
    $cached = $statement->fetch();
    $cachedItems = null;
    $refreshedAt = 0;
    if ($cached) {
        try {
            $payload = json_decode((string)$cached['payload'], true, 512, JSON_THROW_ON_ERROR);
            if (is_array($payload)) {
                $cachedItems = $payload;
                $refreshedAt = strtotime((string)$cached['refreshed_at']) ?: 0;
            }
        } catch (JsonException $exception) {
            error_log('KageGate airing schedule cache is invalid: ' . $exception->getMessage());
        }
    }
    if (is_array($cachedItems) && $refreshedAt > time() - AIRING_CACHE_SECONDS) {
        return $result = ['items' => $cachedItems, 'warning' => ''];
    }

    try {
        $items = fetch_airing_schedule_from_anilist($from, $to);
        $update = db()->prepare(
            'INSERT INTO catalog_cache (cache_key, payload, refreshed_at) VALUES (?, ?, CURRENT_TIMESTAMP) '
            . 'ON DUPLICATE KEY UPDATE payload = VALUES(payload), refreshed_at = CURRENT_TIMESTAMP'
        );
        $update->execute([AIRING_CACHE_KEY, json_encode($items, JSON_THROW_ON_ERROR)]);
        return $result = ['items' => $items, 'warning' => ''];
    } catch (RuntimeException | JsonException | PDOException $exception) {
        error_log('KageGate airing schedule refresh failed: ' . $exception->getMessage());
        if (is_array($cachedItems)) {
            return $result = [
                'items' => $cachedItems,
                'warning' => 'The release schedule could not refresh. Showing the last cached schedule.',
            ];
        }
        return $result = [
            'items' => [],
            'warning' => 'The release schedule is temporarily unavailable. Check the database connection and outbound HTTPS access to AniList.',
        ];
    }
}

function catalog_data(): array {
    static $result = null;
    if (is_array($result)) return $result;

    $statement = db()->prepare('SELECT payload, refreshed_at FROM catalog_cache WHERE cache_key = ?');
    $cachedItems = null;
    $refreshedAt = 0;
    $cachedKey = '';
    foreach (array_merge([CATALOG_CACHE_KEY], CATALOG_PREVIOUS_CACHE_KEYS, [CATALOG_LEGACY_CACHE_KEY]) as $key) {
        $statement->execute([$key]);
        $cached = $statement->fetch();
        if (!$cached) continue;
        try {
            $decoded = json_decode((string)$cached['payload'], true, 512, JSON_THROW_ON_ERROR);
            if (is_array($decoded)) {
                $cachedItems = $decoded;
                $refreshedAt = strtotime((string)$cached['refreshed_at']) ?: 0;
                $cachedKey = $key;
                break;
            }
        } catch (JsonException $exception) {
            error_log('KageGate catalog cache is invalid: ' . $exception->getMessage());
        }
    }

    if ($cachedKey === CATALOG_CACHE_KEY && is_array($cachedItems) && $refreshedAt > time() - CATALOG_CACHE_SECONDS
        && count(array_unique(array_column($cachedItems, 'category'))) >= 5) {
        return $result = ['items' => $cachedItems, 'warning' => '', 'error' => ''];
    }

    try {
        $items = fetch_catalog_from_anilist();
        $update = db()->prepare(
            'INSERT INTO catalog_cache (cache_key, payload, refreshed_at) VALUES (?, ?, CURRENT_TIMESTAMP) '
            . 'ON DUPLICATE KEY UPDATE payload = VALUES(payload), refreshed_at = CURRENT_TIMESTAMP'
        );
        $update->execute([CATALOG_CACHE_KEY, json_encode($items, JSON_THROW_ON_ERROR)]);
        return $result = ['items' => $items, 'warning' => '', 'error' => ''];
    } catch (RuntimeException | JsonException | PDOException $exception) {
        error_log('KageGate catalog refresh failed: ' . $exception->getMessage());
        if (is_array($cachedItems)) {
            return $result = [
                'items' => $cachedItems,
                'warning' => 'The catalog API is temporarily unavailable. Showing the last cached catalog.',
                'error' => '',
            ];
        }
        return $result = [
            'items' => [],
            'warning' => '',
            'error' => 'The live catalog could not be loaded. Check the database and outbound HTTPS access to AniList.',
        ];
    }
}

function youtube_embed_id(string $url): string {
    $parts = parse_url($url);
    $host = strtolower((string)($parts['host'] ?? ''));
    $id = '';
    if (in_array($host, ['youtube.com', 'www.youtube.com', 'm.youtube.com', 'youtube-nocookie.com', 'www.youtube-nocookie.com'], true)) {
        if (str_starts_with((string)($parts['path'] ?? ''), '/embed/')) {
            $id = substr((string)$parts['path'], 7);
        } elseif (str_starts_with((string)($parts['path'] ?? ''), '/watch')) {
            parse_str((string)($parts['query'] ?? ''), $query);
            $id = (string)($query['v'] ?? '');
        }
    } elseif ($host === 'youtu.be') {
        $id = trim((string)($parts['path'] ?? ''), '/');
    }
    return preg_match('/^[A-Za-z0-9_-]{11}$/D', $id) ? $id : '';
}
