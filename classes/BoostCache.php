<?php

declare(strict_types=1);

namespace Bnomei;

use Kirby\Cache\Cache;
use Kirby\Cache\FileCache;
use Kirby\Cache\MemCached;
use Kirby\Cms\Page;

final class BoostCache
{
    private static $singleton;
    public static function singleton(): Cache
    {
        if (! self::$singleton) {
            $config = [
                'host' => option('bnomei.boost.memcached.host'),
                'port' => option('bnomei.boost.memcached.port'),
                'prefix' => option('bnomei.boost.memcached.prefix'),
            ];
            foreach (array_keys($config) as $key) {
                if (!is_string($config[$key]) && is_callable($config[$key])) {
                    $config[$key] = $config[$key]();
                }
            }
            if (class_exists('Memcached') && option('bnomei.boost.cacheType') === 'memcached') {
                self::$singleton = new Memcached($config);
            } else {
                self::$singleton = kirby()->cache('bnomei.boost');
            }
        }
        if (option('debug')) {
            self::$singleton->flush();
        }

        return self::$singleton;
    }
}
