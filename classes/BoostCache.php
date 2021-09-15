<?php

declare(strict_types=1);

namespace Bnomei;

use Kirby\Cache\Cache;
use Kirby\Cache\FileCache;
use Kirby\Cache\ApcuCache;
use Kirby\Cache\MemoryCache;
use Kirby\Cache\MemCached;

final class BoostCache
{
    private static $singleton;
    public static function singleton(): Cache
    {
        if (! self::$singleton) {
            self::$singleton = kirby()->cache('bnomei.boost');
        }
        if (option('debug')) {
            self::$singleton->flush();
        }

        return self::$singleton;
    }

    public static function file(array $options = []): FileCache
    {
        return new FileCache(array_merge([
            'root' => kirby()->cache('bnomei.boost')->root(),
            'prefix' => 'boost',
        ], $options));
    }

    public static function apcu(array $options = []): ApcuCache
    {
        return new ApcuCache(array_merge([
            'prefix' => 'boost',
        ], $options));
    }

    public static function memcached(array $options = []): MemCached
    {
        return new Memcached(array_merge([
            'prefix' => 'boost',
            'host' => '127.0.0.1',
            'port' => 11211,
        ]));
    }

    public static function memory(array $options = []): MemoryCache
    {
        return new MemoryCache(array_merge([
            'prefix' => 'boost',
        ], $options));
    }

    public static function sqlite(array $options = [])//: Cache
    {
        if (class_exists('Bnomei\\SQLiteCache')) {
            $feather = \Bnomei\SQLiteCache::singleton(array_merge([
                'prefix' => 'boost',
            ], $options));
            return $feather;
        }
        return null;
    }

    public static function redis(array $options = [])//: Cache
    {
        if (class_exists('Bnomei\\Redis')) {
            return new \Bnomei\Redis(array_merge([
                'prefix' => 'boost',
                'host'   => '127.0.0.1',
                'port'   => 6379,
            ], $options));
        }
        return null;
    }
}
