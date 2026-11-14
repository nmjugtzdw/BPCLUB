<?php
declare(strict_types=1);

namespace App;

use RuntimeException;

/** TMDB trends are candidate leads only; this class never publishes or writes a database. */
final class TrendingCandidates
{
    /** @return array<int, array<string, mixed>> */
    public static function parse(string $json, string $type): array
    {
        if (!in_array($type, ['movie', 'tv'], true)) {
            throw new RuntimeException('invalid_type');
        }
        try {
            $data = json_decode($json, true, 64, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new RuntimeException('invalid_json', 0, $e);
        }
        if (!is_array($data) || !isset($data['results']) || !is_array($data['results'])) {
            throw new RuntimeException('invalid_response');
        }
        $items = [];
        $seen = [];
        foreach ($data['results'] as $row) {
            if (!is_array($row) || ($row['adult'] ?? null) !== false
                || !is_int($row['id'] ?? null) || $row['id'] < 1) {
                continue;
            }
            $name = $row[$type === 'movie' ? 'title' : 'name'] ?? null;
            if (!is_string($name) || trim($name) === '' || mb_strlen($name) > 150) {
                continue;
            }
            if (isset($seen[$row['id']])) {
                continue;
            }
            $seen[$row['id']] = true;
            $date = $row[$type === 'movie' ? 'release_date' : 'first_air_date'] ?? null;
            $items[] = [
                'source' => 'TMDB', 'source_id' => $row['id'],
                'type' => $type === 'tv' ? 'series' : 'movie', 'name' => trim($name),
                'release_date' => is_string($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/D', $date) ? $date : null,
                'source_url' => 'https://www.themoviedb.org/' . ($type === 'tv' ? 'tv' : 'movie') . '/' . $row['id'],
                'review_status' => 'pending',
            ];
        }
        return $items;
    }

    public static function fetch(string $type, string $token): string
    {
        if (!in_array($type, ['movie', 'tv'], true) || $token === '' || preg_match('/[\r\n]/', $token)) {
            throw new RuntimeException('invalid_config');
        }
        if (!function_exists('curl_init')) {
            throw new RuntimeException('curl_unavailable');
        }
        $ch = curl_init('https://api.themoviedb.org/3/trending/' . $type . '/day?language=zh-CN');
        if ($ch === false) {
            throw new RuntimeException('curl_init_failed');
        }
        $body = '';
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token, 'Accept: application/json'],
            CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 15, CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_WRITEFUNCTION => static function ($ch, string $chunk) use (&$body): int {
                if (strlen($body) + strlen($chunk) > 1024 * 1024) { return 0; }
                $body .= $chunk;
                return strlen($chunk);
            },
        ]);
        $ok = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        if ($ok === false || $status !== 200) {
            throw new RuntimeException('request_failed_http_' . $status);
        }
        return $body;
    }
}
