<?php

declare(strict_types=1);

namespace Bnomei;

use Kirby\Cache\FileCache;
use Kirby\Cache\MemCached;
use Kirby\Toolkit\Str;

final class CacheBenchmark
{
    private static function benchmark(int $seconds, $cache, $count = 2000, $contentLength = 128): int
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

    public static function file(int $seconds): int
    {
        $cache = new FileCache([
            'root' => kirby()->cache('bnomei.boost')->root(),
        ]);
        return static::benchmark($seconds, $cache);
    }

    public static function memcached(int $seconds): int
    {
        $cache = new Memcached(option('bnomei.boost.memcached'));
        return static::benchmark($seconds, $cache);
    }

    public static function fastest(int $seconds = 2): string
    {
        $benchmarks = [
            'file' => static::file($seconds),
            'memcached' => static::memcached($seconds),
        ];
        foreach ($benchmarks as $key => $val) {
            $lastkey = $key;
        }
        return $lastkey;
    }
}
