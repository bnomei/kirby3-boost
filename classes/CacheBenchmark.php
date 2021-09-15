<?php

declare(strict_types=1);

namespace Bnomei;

use Kirby\Cache\FileCache;
use Kirby\Cache\MemCached;
use Kirby\Toolkit\Str;

final class CacheBenchmark
{
    private static function benchmark($cache, int $seconds = 2, $count = 2000, $contentLength = 128): int
    {
        for ($i = 0; $i < $count; $i++) {
            $cache->set('CacheBenchmark-' . $i, Str::random($contentLength), 0);
        }

        $time = microtime(true);
        $values = [];
        $index = 0;
        while ($time + $seconds > microtime(true)) {
            $values[] = $cache->get(strval($index));
            $index++;
            if ($index >= $count) {
                $index = 0;
            }
        }

        for ($i = 0; $i < $count; $i++) {
            $cache->remove('CacheBenchmark-' . $i);
        }

        return count($values);
    }

    public static function run(array $caches = [], int $seconds = 2, $count = 2000, $contentLength = 128): array
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
