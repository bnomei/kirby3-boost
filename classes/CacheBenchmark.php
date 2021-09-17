<?php

declare(strict_types=1);

namespace Bnomei;

use Kirby\Toolkit\Str;

final class CacheBenchmark
{
    private static function benchmark($cache, int $seconds = 1, $count = 1000, $contentLength = 128, $writeRatio = 0.1): int
    {
        for ($i = 0; $i < $count; $i++) {
            $cache->set('CacheBenchmark-' . $i, Str::random($contentLength), 0);
        }

        $time = microtime(true);
        $gets = 0;
        $index = 0;
        $write = intval(ceil($count * $writeRatio));
        while ($time + $seconds > microtime(true)) {
            if ($v = $cache->get('CacheBenchmark-' . $index)) {
                $gets++;
            }
            if ($index % $write === 0) {
                $cache->set('CacheBenchmark-' . $i, Str::random($contentLength), 0);
            }
            $index++;
            if ($index >= $count) {
                $index = 0;
            }
        }

        for ($i = 0; $i < $count; $i++) {
            $cache->remove('CacheBenchmark-' . $i);
        }

        return $gets;
    }

    public static function run(array $caches = [], int $seconds = 1, $count = 1000, $contentLength = 128, $writeRatio = 0.1): array
    {
        $caches = $caches ?? [ BoostCache::file() ];

        $benchmarks = [];
        foreach ($caches as $cache) {
            if (!$cache) {
                continue;
            }
            $benchmarks[$cache::class] = static::benchmark($cache, $seconds, $count, $contentLength);
        }

        asort($benchmarks);
        return array_reverse($benchmarks, true); // DESC
    }
}
