<?php

declare(strict_types=1);

namespace Bnomei;

use Kirby\Cache\Cache;
use Kirby\Cache\FileCache;
use Kirby\Cache\ApcuCache;
use Kirby\Cache\MemoryCache;
use Kirby\Cache\MemCached;
use Kirby\Cache\NullCache;

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

    public static function beginTransaction()
    {
        if(is_callable([self::singleton(), 'beginTransaction'])) {
            self::singleton()->beginTransaction();
        }
    }

    public static function endTransaction()
    {
        if(is_callable([self::singleton(), 'endTransaction'])) {
            self::singleton()->endTransaction();
        }
    }

    public static function nulld(array $options = []): NullCache
    {
        return new NullCache(array_merge([
        ], $options));
    }

    public static function file(array $options = []): FileCache
    {
        return new FileCache(array_merge([
            'root' => kirby()->roots()->cache(),
        ], $options));
    }

    public static function apcu(array $options = []): ApcuCache
    {
        return new ApcuCache(array_merge([
        ], $options));
    }

    public static function memcached(array $options = []): MemCached
    {
        return new Memcached(array_merge([
            'host' => '127.0.0.1',
            'port' => 11211,
        ]));
    }

    public static function memory(array $options = []): MemoryCache
    {
        return new MemoryCache(array_merge([
        ], $options));
    }

    public static function sqlite(array $options = [])//: Cache
    {
        if (class_exists('Bnomei\\SQLiteCache')) {
            $feather = \Bnomei\SQLiteCache::singleton(array_merge([
            ], $options));
            return $feather;
        }
        return null;
    }

    public static function mysql(array $options = [])//: Cache
    {
        if (class_exists('Bnomei\\MySQLCache')) {
            return \Bnomei\MySQLCache::singleton(array_merge([
            ], $options));
        }
        return null;
    }

    public static function redis(array $options = [])//: Cache
    {
        if (class_exists('Bnomei\\Redis')) {
            return new \Bnomei\Redis(array_merge([
                'host'   => '127.0.0.1',
                'port'   => 6379,
            ], $options));
        }
        return null;
    }
}
