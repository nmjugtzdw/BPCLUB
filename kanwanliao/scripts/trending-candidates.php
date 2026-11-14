<?php
declare(strict_types=1);
date_default_timezone_set('UTC');
if (PHP_SAPI !== 'cli') { http_response_code(404); exit(1); }
require dirname(__DIR__) . '/app/TrendingCandidates.php';
$token = getenv('TMDB_READ_ACCESS_TOKEN');
if (!is_string($token) || $token === '') {
    fwrite(STDERR, "Missing TMDB_READ_ACCESS_TOKEN. No API request was sent.\n");
    exit(1);
}
try {
    $movies = App\TrendingCandidates::parse(App\TrendingCandidates::fetch('movie', $token), 'movie');
    $series = App\TrendingCandidates::parse(App\TrendingCandidates::fetch('tv', $token), 'tv');
    echo json_encode(['source' => 'TMDB', 'window' => 'day', 'language' => 'zh-CN',
        'retrieved_at_utc' => gmdate('c'), 'candidates' => array_merge($movies, $series)],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), PHP_EOL;
} catch (Throwable $e) {
    // Provider response bodies and authentication values must not appear in logs.
    fwrite(STDERR, "Trending lookup failed: " . $e->getMessage() . "\n");
    exit(1);
}
