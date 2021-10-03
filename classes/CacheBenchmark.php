<?php

declare(strict_types=1);

namespace Bnomei;

use Kirby\Toolkit\Str;

final class CacheBenchmark
{
    private static function benchmark($cache, int $seconds = 1, $count = 1000, $contentLength = 128, $writeRatio = 0.1): array
    {
        if (is_callable([$cache, 'beginTransaction'])) {
            $cache->beginTransaction();
        }

        for ($i = 0; $i < $count; $i++) {
            $cache->set('CacheBenchmark-' . $i, Str::random($contentLength), 0);
        }

        $time = microtime(true);
        $gets = 0;
        $sets = 0;
        $index = 0;
        $write = intval(ceil($count / ($count * $writeRatio)));
        while ($time + $seconds > microtime(true)) {
            if ($v = $cache->get('CacheBenchmark-' . $index)) {
                // $v
            }
            $gets++; // count null caches and fails
            if ($index % $write === 0) {
                $cache->set('CacheBenchmark-' . $index, Str::random($contentLength), 0);
                $sets++;
            }
            $index++;
            if ($index >= $count) {
                $index = 0;
            }
        }

        for ($i = 0; $i < $count; $i++) {
            $cache->remove('CacheBenchmark-' . $i);
        }

        if (is_callable([$cache, 'endTransaction'])) {
            $cache->endTransaction();
        }

        return [
            'gets' => $gets,
            'sets' => $sets,
            'score' => 0, // will be created later
        ];
    }

    public static function run(array $caches = [], int $seconds = 1, $count = 1000, $contentLength = 128, $writeRatio = 0.1): array
    {
        $caches = $caches ?? [ BoostCache::file() ];

        $benchmarks = [];
        $highscore = 0;
        foreach ($caches as $cache) {
            if (!$cache) {
                continue;
            }
            $class = get_class($cache);
            $benchmarks[$class] = static::benchmark($cache, $seconds, $count, $contentLength, $writeRatio);
            if ($benchmarks[$class]['gets'] > $highscore) {
                $highscore = $benchmarks[$class]['gets'];
            }
        }

        $benchmarks = array_map(function ($item) use ($highscore) {
            $item['score'] = intval(ceil($item['gets'] / $highscore * 100)). '/100';
            return $item;
        }, $benchmarks);

        uasort($benchmarks, function ($a, $b) {
            if ($a['gets'] < $b['gets']) {
                return -1;
            }
            if ($a['gets'] > $b['gets']) {
                return 1;
            }
            return 0;
        });
        $benchmarks = array_reverse($benchmarks, true); // DESC

        return [
            'options' => [
               'seconds' => $seconds,
               'count' => $count,
               'contentLength' => $contentLength,
               'writeRatio' => $writeRatio,
            ],
            'results' => $benchmarks,
        ];
    }
}
